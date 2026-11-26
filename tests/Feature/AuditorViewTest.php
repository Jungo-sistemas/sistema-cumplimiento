<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuditFindingAssignedNotification;
use App\Services\AuditFindingService;
use App\Services\OfficeDocumentConverter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Vista del auditor (menú Auditorías) con datos reales: programar, checklist, resultado y hallazgos.
 * El recorrido completo junto con el auditado está en AuditWorkflowEndToEndTest.
 */
class AuditorViewTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $auditor;
    private User $ana;
    private User $luis;
    private Regulation $regulation;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('private');
        $this->travelTo(Carbon::create(2026, 11, 17, 10, 0));

        $this->group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);

        $this->auditor = $this->user('Daniel Ruiz', 'auditor');
        $this->ana = $this->user('Ana Torres', 'operative');
        $this->luis = $this->user('Luis Ramos', 'operative');

        $type = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $this->regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'P-ALC-300', 'name' => 'Carga de autotanques', 'is_active' => true, 'approval_status' => 'approved',
        ]);
    }

    private function user(string $name, string $slug): User
    {
        return User::factory()->create([
            'name' => $name, 'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id,
        ]);
    }

    private function audit(array $attributes = []): Audit
    {
        $audit = Audit::create($attributes + [
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'regulation_id' => $this->regulation->id,
            'auditor_id' => $this->auditor->id, 'planned_for' => '2026-11-01', 'scheduled_at' => '2026-11-17 09:00:00',
            'started_at' => '2026-11-17 09:05:00', // en curso (el auditor ya la inició)
        ]);
        $audit->auditees()->attach([$this->ana->id, $this->luis->id]);

        return $audit;
    }

    /** Abre el checklist (crea sus criterios) y regresa los criterios. */
    private function openChecklist(Audit $audit)
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))->assertOk();

        return $audit->checklistItems()->get();
    }

    private function finding(array $extra = []): array
    {
        return $extra + ['titulo' => 'Bitácora sin firma', 'descripcion' => 'Falta la firma.', 'severidad' => 'no_critico', 'responsable_id' => $this->ana->id];
    }

    /** Versión vigente del proceso con su archivo (subido en Word o PDF, sin body_html). */
    private function version(string $path, string $name): RegulationVersion
    {
        return RegulationVersion::create([
            'regulation_id' => $this->regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => $path, 'original_name' => $name, 'disk' => 'private',
        ]);
    }

    /** PDF mínimo hecho a mano: una página por arreglo de renglones (texto sin acentos ni paréntesis). */
    private static function pdf(array $pages): string
    {
        $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>'];
        $kids = [];
        foreach ($pages as $lines) {
            $stream = "BT /F1 11 Tf 14 TL 50 780 Td\n" . implode('', array_map(fn ($line) => "({$line}) Tj T*\n", $lines)) . 'ET';
            $page = count($objects) + 2;
            $objects[$page] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 3 0 R >> >> /Contents ' . ($page + 1) . ' 0 R >>';
            $objects[$page + 1] = '<< /Length ' . strlen($stream) . " >>\nstream\n{$stream}\nendstream";
            $kids[] = "{$page} 0 R";
        }
        $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $kids) . '] /Count ' . count($kids) . ' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $xref = "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($objects as $number => $body) {
            $xref .= sprintf("%010d 00000 n \n", strlen($pdf));
            $pdf .= "{$number} 0 obj\n{$body}\nendobj\n";
        }

        return $pdf . $xref . 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . strlen($pdf) . "\n%%EOF";
    }

    public function test_todas_las_pantallas_del_auditor_cargan(): void
    {
        $captura = $this->audit();
        $porIniciar = $this->audit(['started_at' => null]);
        $programada = $this->audit(['scheduled_at' => '2026-11-25 10:00:00', 'started_at' => null]);
        $sinFecha = $this->audit(['scheduled_at' => null, 'started_at' => null]);
        $finalizada = $this->audit(['status' => Audit::STATUS_COMPLETED, 'completed_at' => now(), 'result' => '80 / 100', 'score' => 80]);
        app(AuditFindingService::class)->register($finalizada, $this->ana, 'Hallazgo de prueba', 'Detalle');

        foreach ([
            route('auditorias.home'),
            route('auditorias.audits.index'), route('auditorias.audits.index', ['tab' => 'finalizadas']),
            route('auditorias.audits.checklist', $captura), route('auditorias.audits.checklist', $porIniciar),
            route('auditorias.audits.checklist', $programada),
            route('auditorias.audits.checklist', $sinFecha), route('auditorias.audits.result', $finalizada),
            route('auditorias.findings.index'), route('auditorias.findings.index', ['tab' => 'causas']),
            route('auditorias.findings.index', ['tab' => 'evidencias']),
            route('auditorias.help'),
        ] as $url) {
            $html = $this->actingAs($this->auditor)->get($url)->assertOk()->getContent();
            // Cada <div> que se abre se cierra. Si falta o sobra uno, la página carga pero se acomoda raro.
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), "Hay un <div> de más o de menos en $url");
        }
    }

    public function test_el_auditor_entra_a_su_inicio_de_auditorias(): void
    {
        $this->actingAs($this->auditor)->get(route('gestion.home'))->assertRedirect(route('auditorias.home'));
        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))
            ->assertSee('href="' . route('auditorias.home') . '"', false);
    }

    public function test_el_auditor_no_entra_a_cumplimiento_aunque_tenga_todas_las_areas(): void
    {
        $this->auditor->forceFill(['module_access' => 'all'])->saveQuietly(); // dato viejo
        $this->assertSame(['procesos'], $this->auditor->fresh()->accessibleModules());

        $this->actingAs($this->auditor)->get(route('dashboard'))->assertRedirect();
        $this->actingAs($this->auditor)->get(route('assets.index'))->assertRedirect();
        $this->actingAs($this->auditor)->get(route('auditorias.home'))
            ->assertOk()->assertDontSee('href="' . route('dashboard') . '"', false);

        // Al guardarlo (alta o edición en Usuarios) queda como solo Procesos.
        $this->auditor->update(['module_access' => 'cumplimiento']);
        $this->assertSame('procesos', $this->auditor->fresh()->module_access);
    }

    public function test_al_iniciar_sesion_el_auditor_llega_a_auditorias(): void
    {
        $this->auditor->update(['two_factor_code' => \Illuminate\Support\Facades\Hash::make('123456'), 'two_factor_expires_at' => now()->addMinutes(10)]);

        $this->withSession(['two_factor_pending_user_id' => $this->auditor->id])
            ->post(route('two-factor.store'), ['code' => '123456'])
            ->assertRedirect(route('auditorias.home'));
    }

    public function test_solo_auditores_y_admins_entran_y_cada_auditor_ve_las_suyas(): void
    {
        $audit = $this->audit();
        $otroAuditor = $this->user('Otro Auditor', 'auditor');
        $admin = $this->user('Alma Admin', 'admin');

        $this->actingAs($this->ana)->get(route('auditorias.audits.checklist', $audit))->assertForbidden();
        $this->actingAs($otroAuditor)->get(route('auditorias.audits.checklist', $audit))->assertForbidden();
        $this->actingAs($otroAuditor)->get(route('auditorias.audits.index'))->assertDontSee('Ana Torres, Luis Ramos');
        $this->actingAs($admin)->get(route('auditorias.audits.checklist', $audit))->assertOk()->assertSee('Solo el auditor asignado');
        $this->actingAs($admin)->postJson(route('auditorias.audits.checklist.save', $audit), [])->assertForbidden();
    }

    public function test_una_auditoria_que_no_existe_da_404(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 99))->assertNotFound();
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 99))->assertNotFound();
    }

    public function test_programar_auditoria_y_nadie_se_audita_a_si_mismo(): void
    {
        $this->actingAs($this->auditor)->post(route('auditorias.audits.store'), [
            'regulation_id' => $this->regulation->id, 'auditees' => [$this->auditor->id],
        ])->assertSessionHasErrors('auditees');

        $this->actingAs($this->auditor)->post(route('auditorias.audits.store'), [
            'regulation_id' => $this->regulation->id, 'auditees' => [$this->ana->id], 'fecha' => '2026-11-16', 'hora' => '10:00',
        ])->assertSessionHasErrors('fecha'); // ya pasó

        $this->actingAs($this->auditor)->post(route('auditorias.audits.store'), [
            'regulation_id' => $this->regulation->id, 'auditees' => [$this->ana->id],
        ])->assertSessionHas('success');

        $audit = Audit::sole();
        $this->assertNull($audit->scheduled_at);
        $this->assertSame(Audit::STAGE_UNSCHEDULED, $audit->auditorStage());
        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))->assertSee('Sin fecha')->assertSee('Poner fecha');
    }

    public function test_el_admin_elige_al_auditor(): void
    {
        $admin = $this->user('Alma Admin', 'admin');

        $this->actingAs($admin)->post(route('auditorias.audits.store'), [
            'regulation_id' => $this->regulation->id, 'auditees' => [$this->ana->id], 'auditor_id' => $this->auditor->id,
        ])->assertSessionHas('success');

        $this->assertSame($this->auditor->id, Audit::sole()->auditor_id);
    }

    public function test_solo_la_auditoria_en_curso_se_puede_llenar(): void
    {
        $programada = $this->audit(['scheduled_at' => '2026-11-25 10:00:00', 'started_at' => null]);

        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $programada))
            ->assertOk()->assertSee('Programada')->assertDontSee('Guardar avance')->assertSee(':disabled="true || confirmando"', false);
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.checklist.save', $programada), [])->assertStatus(422);

        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $this->audit()))
            ->assertOk()->assertSee('En curso')->assertSee('Guardar avance')->assertSee('Registrar hallazgo');
    }

    public function test_quien_consulta_ve_los_botones_bloqueados_y_los_hallazgos(): void
    {
        $audit = $this->audit();
        $this->openChecklist($audit);

        // El admin consulta: ve los botones, pero el fieldset los bloquea; ve la lista de hallazgos, sin Editar.
        $this->actingAs($this->user('Alma Admin', 'admin'))->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('Elegir archivos')->assertSee('Tomar foto')->assertSee('Registrar hallazgo')
            ->assertSee(':disabled="true || confirmando"', false)
            ->assertSee('in hallazgos[0]', false)->assertDontSee('editar(0, n)', false);

        // El auditor asignado, con la auditoría en curso, sí los usa.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee(':disabled="false || confirmando"', false)->assertSee('editar(0, n)', false);
    }

    public function test_el_auditor_no_consulta_procesos_desde_gestion(): void
    {
        // Menú de Auditorías sin la sección Procesos.
        $this->actingAs($this->auditor)->get(route('auditorias.home'))
            ->assertOk()
            ->assertDontSee(route('processes.index'), false)
            ->assertDontSee('data-tour="nav-procedimientos"', false);

        // Por liga tampoco: lo regresa a Auditorías.
        $this->actingAs($this->auditor)->get(route('processes.index'))->assertRedirect(route('auditorias.home'));
        $this->actingAs($this->auditor)->get(route('processes.create'))->assertRedirect(route('auditorias.home'));

        // En Mi espacio sí ve sus procedimientos (y abre uno como cualquier auditado).
        $this->actingAs($this->auditor)->get(route('mi-espacio.documents'))->assertOk();
        $this->actingAs($this->auditor)->get(route('processes.show', $this->regulation))->assertOk();

        // Ya no se le pide la guía de Procesos.
        $this->assertFalse($this->auditor->fresh()->needsProcesosGuide());
    }

    public function test_iniciar_la_auditoria_habilita_el_checklist(): void
    {
        $audit = $this->audit(['started_at' => null]); // hoy es el día (17 nov)

        // Por iniciar: sin captura, con el botón.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))
            ->assertOk()->assertSee('Por iniciar')->assertSee('Iniciar auditoría');
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('Por iniciar')->assertSee('Iniciar auditoría')->assertDontSee('Guardar avance');
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.checklist.save', $audit), [])->assertStatus(422);

        // Solo el auditor asignado la inicia.
        $this->actingAs($this->user('Alma Admin', 'admin'))->post(route('auditorias.audits.start', $audit))->assertForbidden();

        // Inicia: queda la hora y abre el checklist; ya no se cambia la fecha.
        $this->actingAs($this->auditor)->post(route('auditorias.audits.start', $audit))
            ->assertRedirect(route('auditorias.audits.checklist', $audit))->assertSessionHas('success');
        $this->assertNotNull($startedAt = $audit->fresh()->started_at);
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('En curso')->assertSee('Iniciada:')->assertSee('Guardar avance')
            ->assertDontSee("\$dispatch('programar-fecha'", false); // sin botón de Cambiar fecha
        $this->actingAs($this->auditor)->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-30', 'hora' => '10:00'])
            ->assertSessionHas('error');

        // Un segundo clic no cambia la hora de inicio.
        $this->travel(5)->minutes();
        $this->actingAs($this->auditor)->post(route('auditorias.audits.start', $audit))->assertRedirect();
        $this->assertEquals($startedAt, $audit->fresh()->started_at);

        // Antes del día no se puede iniciar.
        $futura = $this->audit(['scheduled_at' => '2026-11-25 10:00:00', 'started_at' => null]);
        $this->actingAs($this->auditor)->post(route('auditorias.audits.start', $futura))->assertSessionHas('error');
        $this->assertNull($futura->fresh()->started_at);
    }

    public function test_la_primera_pregunta_es_si_el_auditado_conoce_sus_procedimientos(): void
    {
        $items = $this->openChecklist($this->audit());

        $this->assertSame('El auditado conoce sus procedimientos y sabe dónde consultarlos.', $items->first()->text);
        $this->assertTrue($items->first()->isFixed());
    }

    public function test_los_pasos_se_leen_del_pdf_del_proceso(): void
    {
        Storage::disk('private')->put('procesos/p-alc-300.pdf', self::pdf([
            ['Descripcion del proceso / Actividades', 'Paso 1. Recepcion del carrotanque', 'Responsable: Jefe de patio', 'Que: Recibe el carrotanque.', 'Evidencia requerida: Bitacora firmada.', 'Riesgo de Seguridad y Sanciones: Multa del cliente.'],
            ['Paso 2. Descarga', 'Responsable: Operador', 'Que: Descarga el producto.'],
        ]));
        $this->version('procesos/p-alc-300.pdf', 'P-ALC-300.pdf');
        $audit = $this->audit();

        $items = $this->openChecklist($audit);

        $this->assertSame(['El auditado conoce sus procedimientos y sabe dónde consultarlos.', 'Recepcion del carrotanque', 'Descarga'], $items->pluck('text')->all());
        $this->assertSame('Bitacora firmada.', $items[1]->evidence);
        $this->assertSame('Jefe de patio', $items[1]->activity['responsable']);
        $this->assertSame([['etiqueta' => 'Riesgo de Seguridad y Sanciones', 'texto' => 'Multa del cliente.']], $items[1]->activity['otros']);

        // La tarjeta dice "2. Recepcion…" (no "2. Paso 1. …"); el número del paso va en la actividad.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))->assertOk()
            ->assertSee('2. Recepcion del carrotanque')->assertSee('Paso 1. Recepcion del carrotanque')->assertDontSee('2. Paso 1.');
    }

    public function test_ver_procedimiento_completo_abre_directo_el_documento(): void
    {
        Storage::disk('private')->put('procesos/p-alc-300.pdf', self::pdf([['Paso 1. Recepcion', 'Responsable: Jefe de patio']]));
        $version = $this->version('procesos/p-alc-300.pdf', 'P-ALC-300.pdf');
        $audit = $this->audit();

        // Abre el documento vigente (como "Ver" en Procesos), no la página del proceso; el auditor sí puede verlo.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))->assertOk()
            ->assertSee(route('regulation-versions.preview', $version), false)
            ->assertDontSee(route('processes.show', $this->regulation), false);
        $this->actingAs($this->auditor)->get(route('regulation-versions.preview', $version))->assertOk();
    }

    public function test_un_word_se_convierte_una_sola_vez_para_leer_sus_pasos(): void
    {
        Storage::disk('private')->put('procesos/p-alc-300.docx', 'contenido de Word');
        $this->version('procesos/p-alc-300.docx', 'P-ALC-300.docx');
        $pdf = self::pdf([['Paso 1. Recepcion', 'Responsable: Jefe de patio', 'Que: Recibe el carrotanque.']]);
        $this->mock(OfficeDocumentConverter::class, function ($mock) use ($pdf) {
            $mock->shouldReceive('isConvertible')->andReturn(true);
            $mock->shouldReceive('toPdf')->once()->andReturn($pdf); // la segunda vez usa el PDF guardado
        });
        $audit = $this->audit(['scheduled_at' => '2026-11-25 10:00:00', 'started_at' => null]);

        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))->assertOk()->assertSee('Paso 1. Recepcion');
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))->assertOk()->assertSee('Paso 1. Recepcion');
        Storage::disk('private')->assertExists('procesos/p-alc-300.docx.preview.pdf');
    }

    public function test_si_el_documento_no_se_puede_leer_queda_la_pregunta_fija(): void
    {
        Storage::disk('private')->put('procesos/p-alc-300.pdf', 'esto no es un PDF');
        $this->version('procesos/p-alc-300.pdf', 'P-ALC-300.pdf');

        $this->assertCount(1, $this->openChecklist($this->audit()));
    }

    public function test_criterios_propios_se_agregan_y_quitan_pero_no_los_del_proceso(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);

        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.items.store', $audit), ['text' => ''])->assertStatus(422);
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.items.store', $audit), ['text' => 'Extintor vigente'])->assertOk();
        $propio = $audit->checklistItems()->where('source', AuditChecklistItem::SOURCE_AUDITOR)->sole();

        $this->actingAs($this->auditor)->deleteJson(route('auditorias.audits.items.destroy', [$audit, $fija]))->assertForbidden();
        $this->actingAs($this->auditor)->deleteJson(route('auditorias.audits.items.destroy', [$audit, $propio]))->assertOk();
        $this->assertSame(1, $audit->checklistItems()->count());
    }

    public function test_el_soporte_de_la_revision_se_sube_y_se_quita(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);

        $subido = $this->actingAs($this->auditor)->post(route('auditorias.audits.items.support', [$audit, $fija]), [
            'file' => UploadedFile::fake()->image('image.jpg'), 'name' => 'Foto de las 10.15.jpg',
        ], ['Accept' => 'application/json'])->assertOk()->json();

        $this->assertSame('Foto de las 10.15.jpg', $subido['name']);
        $this->actingAs($this->auditor)->get($subido['url'])->assertOk();
        $this->actingAs($this->ana)->get($subido['url'])->assertForbidden();

        $this->actingAs($this->auditor)->deleteJson(route('auditorias.support.destroy', $subido['id']))->assertOk();
        $this->assertSame(0, $fija->files()->count());
    }

    public function test_los_hallazgos_son_borrador_hasta_finalizar(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);

        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.checklist.save', $audit), [
            'items' => [['id' => $fija->id, 'result' => 'no_cumple', 'findings' => [
                $this->finding(),
                $this->finding(['responsable_id' => $this->auditor->id]), // no es auditado: se descarta
                $this->finding(['severidad' => 'mayor']),                 // clasificación inválida: se descarta
            ]]],
        ])->assertOk();

        $this->assertCount(1, $fija->fresh()->draft_findings);
        $this->assertSame(0, AuditFinding::count());
        Notification::assertNothingSent();
    }

    public function test_para_finalizar_todo_capturado_asistencia_y_hallazgos(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);
        $finalizar = fn (array $data) => $this->actingAs($this->auditor)->postJson(route('auditorias.audits.finalize', $audit), $data);

        $finalizar(['attendance' => [$this->auditor->id, $this->ana->id]])
            ->assertStatus(422)->assertJsonFragment(['Captura el resultado de cada criterio.']);
        $finalizar(['items' => [['id' => $fija->id, 'result' => 'cumple']], 'attendance' => [$this->ana->id]])
            ->assertStatus(422)->assertJsonFragment(['Marca en la lista de asistencia al auditor y al menos a un auditado.']);
        $finalizar(['items' => [['id' => $fija->id, 'result' => 'parcial']], 'attendance' => [$this->auditor->id, $this->ana->id]])
            ->assertStatus(422)->assertJsonFragment(['Registra un hallazgo en cada criterio con No cumple o Parcial.']);

        $finalizar(['items' => [['id' => $fija->id, 'result' => 'parcial', 'findings' => [$this->finding()]]], 'attendance' => [$this->auditor->id, $this->ana->id]])
            ->assertOk();

        $this->assertSame('50 / 100', $audit->fresh()->result);
        Notification::assertSentTo($this->ana, AuditFindingAssignedNotification::class);
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.checklist.save', $audit), [])->assertStatus(422); // ya no se edita
    }

    public function test_no_aplica_no_cuenta_en_la_calificacion(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.items.store', $audit), ['text' => 'Extintor vigente']);
        $propio = $audit->checklistItems()->where('source', AuditChecklistItem::SOURCE_AUDITOR)->sole();

        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.finalize', $audit), [
            'items' => [['id' => $fija->id, 'result' => 'cumple'], ['id' => $propio->id, 'result' => 'no_aplica']],
            'attendance' => [$this->auditor->id, $this->ana->id],
        ])->assertOk();

        $this->assertSame(100, $audit->fresh()->score);
    }

    public function test_el_resultado_muestra_asistencia_conclusiones_y_hallazgos(): void
    {
        $audit = $this->audit();
        [$fija] = $this->openChecklist($audit);
        $this->actingAs($this->auditor)->postJson(route('auditorias.audits.finalize', $audit), [
            'items' => [['id' => $fija->id, 'result' => 'no_cumple', 'observations' => 'No supo dónde consultarlo.', 'findings' => [$this->finding(['severidad' => 'critico'])]]],
            'attendance' => [$this->auditor->id, $this->ana->id],
            'conclusions' => 'Hay que capacitar al equipo.',
        ])->assertOk();

        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', $audit))
            ->assertOk()
            ->assertSee('0 / 100')
            ->assertSee('No supo dónde consultarlo.')
            ->assertSee('Hay que capacitar al equipo.')
            ->assertSee('Bitácora sin firma')->assertSee('Crítico')
            ->assertSee('2 de 3 asistieron')->assertSee('No asistió');
    }

    public function test_los_hallazgos_se_revisan_en_dos_vueltas(): void
    {
        $audit = $this->audit(['status' => Audit::STATUS_COMPLETED, 'completed_at' => now()]);
        $service = app(AuditFindingService::class);
        $finding = $service->register($audit, $this->ana, 'Bitácora sin firma', 'Falta la firma.');

        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['tab' => 'causas']))->assertDontSee('Bitácora sin firma');

        $service->submitRootCause($finding, $this->ana, 'No sabía.', 'Capacitar.');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['tab' => 'causas']))
            ->assertSee('Bitácora sin firma')->assertSee('Revisar causa raíz');
        $this->actingAs($this->auditor)->post(route('auditorias.findings.review', $finding), ['decision' => 'aprobar'])->assertSessionHas('success');

        $service->submitEvidence($finding->fresh(), $this->ana, [UploadedFile::fake()->create('evidencia.pdf', 10, 'application/pdf')]);
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['tab' => 'evidencias']))
            ->assertSee('Revisar evidencia')->assertSee('evidencia.pdf');
        $this->actingAs($this->auditor)->post(route('auditorias.findings.review', $finding), ['decision' => 'aprobar']);

        $this->assertSame(AuditFinding::STATUS_CLOSED, $finding->fresh()->status);
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['seguimiento' => 'cerrada']))->assertSee('Cerrado');
    }

    public function test_hallazgos_marca_los_vencidos_y_filtra(): void
    {
        $audit = $this->audit(['status' => Audit::STATUS_COMPLETED, 'completed_at' => now()]);
        app(AuditFindingService::class)->register($audit, $this->ana, 'Hallazgo viejo', 'Detalle');
        $this->travelTo(Carbon::create(2026, 11, 30, 9, 0));

        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertSee('Hallazgo viejo')->assertSee('Vencido');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['empresa' => 'Otra']))->assertDontSee('Hallazgo viejo');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['q' => 'ana torres']))->assertSee('Hallazgo viejo');
    }
}
