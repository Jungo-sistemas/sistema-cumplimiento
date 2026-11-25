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
use App\Notifications\AuditCompletedNotification;
use App\Notifications\AuditFindingAlertNotification;
use App\Notifications\AuditFindingAssignedNotification;
use App\Notifications\AuditFindingReviewedNotification;
use App\Notifications\AuditFindingSubmittedNotification;
use App\Notifications\AuditScheduledNotification;
use App\Notifications\AuditUpcomingNotification;
use App\Services\AuditChecklistService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Recorrido completo auditor ↔ auditado, los dos por sus pantallas:
 *
 *  1. El auditor programa la auditoría con fecha (Auditorías › Programar auditoría). El auditado no la ve.
 *  2. 3 días antes le llega el aviso (audits:notify-upcoming) y la ve en Mis auditorías.
 *  3. Día de la auditoría: el auditor captura el checklist (criterios del proceso + uno propio,
 *     soporte, asistencia, hallazgos en borrador) y finaliza → calificación y hallazgos enviados.
 *  4. Auditado: causa raíz y acciones → auditor devuelve → corrige → auditor aprueba.
 *  5. Auditado: evidencia del seguimiento → auditor rechaza → sube otra → auditor cierra.
 *  6. Hallazgo crítico sin atender: aviso de vencido; luego se completa la cadena.
 *  7. Historial por usuario para la siguiente auditoría.
 */
class AuditWorkflowEndToEndTest extends TestCase
{
    use RefreshDatabase;

    private const PROCESS_HTML = '<h2>Descripción del Proceso / Actividades</h2>'
        . '<h3>Paso 1. Conexión a tierra</h3><p><em>Responsable: Operaciones — Operador</em></p>'
        . '<p><strong>Quién:</strong> Operador de llenado.</p><p><strong>Qué:</strong> Conectar la pinza de tierra antes de cargar.</p>'
        . '<p><strong>Cómo:</strong> Con la pinza del andén.</p><p><strong>Cuándo:</strong> Antes de abrir válvulas.</p>'
        . '<p><strong>Dónde:</strong> Andén de carga.</p><p><strong>Evidencia requerida:</strong> F-OPE-001 Lista de verificación.</p>'
        . '<h3>Paso 2. Bitácora de descarga</h3><p><strong>Qué:</strong> Registrar la descarga.</p>'
        . '<p><strong>Evidencia requerida:</strong> Bitácora firmada por el supervisor.</p>'
        . '<h2>Registros</h2><p>Otro texto que no es paso.</p>';

    public function test_los_pasos_del_procedimiento_se_vuelven_criterios(): void
    {
        $steps = AuditChecklistService::parseSteps(self::PROCESS_HTML);

        $this->assertCount(2, $steps);
        $this->assertSame('Conexión a tierra', $steps[0]['titulo']);
        $this->assertSame('Operaciones — Operador', $steps[0]['responsable']);
        $this->assertSame('Conectar la pinza de tierra antes de cargar.', $steps[0]['que']);
        $this->assertSame(['Con la pinza del andén.'], $steps[0]['como']);
        $this->assertSame('F-OPE-001 Lista de verificación.', $steps[0]['evidencia']);
        $this->assertSame('Bitácora firmada por el supervisor.', $steps[1]['evidencia']);
        $this->assertSame([], AuditChecklistService::parseSteps('<p>Documento subido en Word, sin pasos.</p>'));
    }

    public function test_recorrido_completo_de_auditoria_y_hallazgos(): void
    {
        Notification::fake();
        Storage::fake('private');

        // ---- Escenario ---------------------------------------------------------------------
        $this->travelTo(Carbon::create(2026, 11, 12, 9, 0)); // jueves

        $group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $operative = Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id;
        $auditorRole = Role::create(['name' => 'Auditor', 'slug' => 'auditor'])->id;
        $adminRole = Role::create(['name' => 'Admin', 'slug' => 'admin'])->id;

        $make = fn (string $name, int $role = 0, array $extra = []) => User::factory()->create($extra + [
            'name' => $name, 'group_id' => $group->id, 'company_id' => $company->id,
            'scope_level' => 'company', 'status' => 'active', 'role_id' => $role ?: $operative,
        ]);
        $auditor = $make('Pedro Auditor', $auditorRole);
        $admin   = $make('Alma Admin', $adminRole);
        $ana     = $make('Ana Torres');   // responsable del hallazgo no crítico
        $luis    = $make('Luis Ramos');   // responsable del hallazgo crítico
        $olga    = $make('Olga Dueña');   // responsable del procedimiento
        $dana    = $make('Dana Avisos', 0, ['receives_audit_alerts' => true]);

        $type = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-ALC-300', 'name' => 'Carga de autotanques', 'is_active' => true,
            'approval_status' => 'approved', 'impact_level' => 'alto',
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
            'body_html' => self::PROCESS_HTML,
        ]);
        $regulation->responsables()->attach($olga->id);

        // ---- 1. El auditor programa la auditoría para el martes 17 a las 10:00 ----------------
        $this->actingAs($auditor)->get(route('auditorias.audits.index'))
            ->assertOk()->assertSee('Programar auditoría')->assertSee('Carga de autotanques');

        $this->actingAs($auditor)->post(route('auditorias.audits.store'), [
            'regulation_id' => $regulation->id,
            'auditees'      => [$ana->id, $luis->id, $auditor->id], // el auditor no puede auditarse
            'fecha'         => '2026-11-17',
            'hora'          => '10:00',
        ])->assertRedirect(route('auditorias.audits.index'))->assertSessionHas('success');

        $audit = Audit::sole();
        $this->assertSame($auditor->id, $audit->auditor_id);
        $this->assertEqualsCanonicalizing([$ana->id, $luis->id], $audit->auditees()->pluck('users.id')->all());
        $this->assertSame(Audit::STAGE_SCHEDULED, $audit->auditorStage());
        Notification::assertNothingSent(); // faltan 5 días

        // Cambia la hora: todavía no se les había avisado, así que tampoco hay aviso de cambio.
        $this->actingAs($auditor)->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-17', 'hora' => '11:00'])
            ->assertSessionHas('success');
        $this->assertSame('11:00', $audit->fresh()->scheduled_at->format('H:i'));
        Notification::assertNothingSent();

        $this->actingAs($ana)->get(route('mi-espacio.audits'))->assertOk()->assertDontSee('Carga de autotanques');
        $this->actingAs($ana)->get(route('auditorias.audits.index'))->assertForbidden(); // no es auditora

        // Antes del día el checklist solo se consulta: ya muestra los pasos del proceso.
        $this->actingAs($auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('El checklist se captura a partir del día')
            ->assertSee('Paso 1. Conexión a tierra')->assertDontSee('Agregar criterio');
        $this->assertSame(0, AuditChecklistItem::count());
        $this->actingAs($auditor)->postJson(route('auditorias.audits.checklist.save', $audit), [])->assertStatus(422);

        // ---- 2. Sábado 14: faltan 3 días → aviso ---------------------------------------------
        $this->travelTo(Carbon::create(2026, 11, 14, 7, 30));
        $this->artisan('audits:notify-upcoming')->assertSuccessful();

        Notification::assertSentToTimes($ana, AuditUpcomingNotification::class, 1);
        Notification::assertSentToTimes($luis, AuditUpcomingNotification::class, 1);
        $this->actingAs($ana)->get(route('mi-espacio.audits'))
            ->assertOk()->assertSee('Carga de autotanques')->assertSee('17 de noviembre de 2026, 11:00');

        // Ya avisados, un cambio de hora sí les llega.
        $this->actingAs($auditor)->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-17', 'hora' => '10:00']);
        Notification::assertSentTo($ana, AuditScheduledNotification::class);

        // ---- 3. Martes 17: el auditor captura el checklist y finaliza --------------------------
        $this->travelTo(Carbon::create(2026, 11, 17, 10, 30));

        $this->actingAs($admin)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('Solo el auditor asignado'); // el admin solo consulta

        $this->actingAs($auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('En captura')->assertSee('Agregar criterio')->assertSee('Pregunta fija');

        $this->actingAs($auditor)->postJson(route('auditorias.audits.items.store', $audit), [
            'text' => 'El extintor del andén está vigente.', 'evidence' => 'Etiqueta del extintor',
        ])->assertOk();

        [$fija, $paso1, $paso2, $propio] = $audit->checklistItems()->get()->all();
        $this->assertSame(AuditChecklistItem::SOURCE_FIXED, $fija->source);
        $this->assertSame('Paso 1. Conexión a tierra', $paso1->text);
        $this->assertSame('F-OPE-001 Lista de verificación.', $paso1->evidence);
        $this->assertSame('Operador de llenado.', $paso1->activity['quien']);
        $this->assertSame(AuditChecklistItem::SOURCE_AUDITOR, $propio->source);

        $soporte = $this->actingAs($auditor)->post(route('auditorias.audits.items.support', [$audit, $paso1]), [
            'file' => UploadedFile::fake()->image('anden.jpg'),
        ], ['Accept' => 'application/json'])->assertOk()->json();
        $this->actingAs($auditor)->get($soporte['url'])->assertOk();

        $capture = [
            'items' => [
                ['id' => $fija->id,   'result' => 'cumple',    'observations' => 'Explicó el procedimiento.', 'findings' => []],
                ['id' => $paso1->id,  'result' => 'parcial',   'observations' => 'Una carga sin pinza.', 'findings' => [[
                    'titulo' => 'Carga sin pinza de tierra', 'descripcion' => 'Se cargó la unidad 12 sin pinza.',
                    'severidad' => 'critico', 'responsable_id' => $luis->id,
                ]]],
                ['id' => $paso2->id,  'result' => 'no_cumple', 'observations' => 'Bitácora sin firma.', 'findings' => [[
                    'titulo' => 'Bitácora de descarga sin firma', 'descripcion' => 'La bitácora no tiene firma del supervisor.',
                    'severidad' => 'no_critico', 'responsable_id' => $ana->id,
                ]]],
                ['id' => $propio->id, 'result' => 'no_aplica', 'observations' => 'No hay extintor en este andén.', 'findings' => []],
            ],
            'attendance'  => [$auditor->id],
            'conclusions' => 'Hay que reforzar la conexión a tierra y la firma de la bitácora.',
        ];

        // Guardar avance y volver a abrir conserva todo, incluidos los hallazgos en borrador.
        $this->actingAs($auditor)->postJson(route('auditorias.audits.checklist.save', $audit), $capture)->assertOk();
        $this->assertSame('Carga sin pinza de tierra', $paso1->fresh()->draft_findings[0]['titulo']);
        $this->actingAs($auditor)->get(route('auditorias.audits.checklist', $audit))->assertSee('Carga sin pinza de tierra', false);
        Notification::assertNotSentTo($luis, AuditFindingAssignedNotification::class); // borrador: todavía no se envía

        // Sin un auditado en la lista de asistencia no se puede finalizar.
        $this->actingAs($auditor)->postJson(route('auditorias.audits.finalize', $audit), $capture)
            ->assertStatus(422)->assertJsonFragment(['Marca en la lista de asistencia al auditor y al menos a un auditado.']);

        $capture['attendance'] = [$auditor->id, $ana->id, $luis->id];
        $this->actingAs($auditor)->postJson(route('auditorias.audits.finalize', $audit), $capture)
            ->assertOk()->assertJson(['redirect' => route('auditorias.audits.result', $audit)]);

        $audit->refresh();
        $this->assertSame(Audit::STATUS_COMPLETED, $audit->status);
        $this->assertSame(50, $audit->score); // (100 + 50 + 0) / 3; No aplica no cuenta
        $this->assertSame('50 / 100', $audit->result);

        $menor   = AuditFinding::where('responsible_id', $ana->id)->sole();
        $critico = AuditFinding::where('responsible_id', $luis->id)->sole();
        $this->assertSame($paso2->id, $menor->audit_checklist_item_id);
        $this->assertSame('Bitácora de descarga sin firma', $menor->point);
        $this->assertSame('2026-11-20', $menor->due_at->toDateString());   // 3 días hábiles
        $this->assertSame('2026-11-18', $critico->due_at->toDateString()); // 1 día hábil
        $this->assertNull($paso1->fresh()->draft_findings);

        Notification::assertSentTo($ana, AuditFindingAssignedNotification::class);
        Notification::assertSentTo($luis, AuditFindingAssignedNotification::class);
        Notification::assertSentToTimes($olga, AuditFindingAssignedNotification::class, 2);
        Notification::assertSentToTimes($dana, AuditFindingAlertNotification::class, 1); // solo el crítico
        Notification::assertSentTo($ana, AuditCompletedNotification::class);

        $this->actingAs($auditor)->get(route('auditorias.audits.result', $audit))
            ->assertOk()->assertSee('50 / 100')->assertSee('Bitácora de descarga sin firma')
            ->assertSee('Hay que reforzar la conexión a tierra')->assertSee('anden.jpg');
        $this->actingAs($auditor)->get(route('auditorias.audits.checklist', $audit))->assertRedirect(route('auditorias.audits.result', $audit));
        $this->actingAs($ana)->get(route('mi-espacio.audits', ['tab' => 'historial']))->assertSee('50 / 100');

        // ---- 4. Causa raíz: Ana la manda, el auditor la devuelve, Ana corrige, el auditor aprueba ----
        $this->actingAs($ana)->post(route('mi-espacio.findings.root-cause', $menor), [
            'root_cause' => 'Falta de firma.', 'actions' => 'Firmar.',
        ])->assertRedirect();
        Notification::assertSentTo($auditor, AuditFindingSubmittedNotification::class);

        $this->actingAs($auditor)->get(route('auditorias.findings.index', ['tab' => 'causas']))
            ->assertOk()->assertSee('Bitácora de descarga sin firma')->assertSee('Revisar causa raíz')->assertDontSee('Carga sin pinza de tierra');

        $this->actingAs($auditor)->post(route('auditorias.findings.review', $menor), ['decision' => 'rechazar'])
            ->assertSessionHasErrors('comment'); // sin motivo no se devuelve
        $this->actingAs($admin)->post(route('auditorias.findings.review', $menor), ['decision' => 'aprobar'])
            ->assertForbidden(); // solo el auditor asignado decide
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $menor), [
            'decision' => 'rechazar', 'comment' => 'Eso describe el problema, no por qué pasó.',
        ])->assertSessionHas('success');
        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_REJECTED, $menor->fresh()->status);

        $this->actingAs($ana)->get(route('mi-espacio.findings.show', $menor))
            ->assertSee('devolvió tu causa raíz')->assertSee('Eso describe el problema, no por qué pasó.');

        $this->travelTo(Carbon::create(2026, 11, 18, 9, 0));
        $this->actingAs($ana)->post(route('mi-espacio.findings.root-cause', $menor), [
            'root_cause' => 'El supervisor de turno no sabía que debía firmar; el procedimiento no lo indica.',
            'actions'    => "1. Agregar la firma del supervisor al paso 2.\n2. Capacitar a los supervisores.",
        ])->assertRedirect();
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $menor), ['decision' => 'aprobar'])->assertSessionHas('success');

        $menor->refresh();
        $this->assertSame(AuditFinding::STATUS_EVIDENCE_PENDING, $menor->status);
        $this->assertSame('2026-12-02', $menor->due_at->toDateString()); // 10 días hábiles
        $this->actingAs($ana)->get(route('mi-espacio.findings.show', $menor))
            ->assertSee('Plan aprobado por el auditor')->assertSee('Subir evidencia del seguimiento');

        // ---- 5. Seguimiento: evidencia, rechazo, nueva evidencia, cierre ----------------------
        $this->travelTo(Carbon::create(2026, 11, 25, 9, 0));
        $this->actingAs($ana)->post(route('mi-espacio.findings.evidence', $menor), [
            'files' => [UploadedFile::fake()->create('bitacora.pdf', 120, 'application/pdf')], 'comment' => 'Bitácora firmada.',
        ])->assertRedirect();

        $this->actingAs($auditor)->get(route('auditorias.findings.index', ['tab' => 'evidencias']))
            ->assertSee('Revisar evidencia')->assertSee('bitacora.pdf');
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $menor), [
            'decision' => 'rechazar', 'comment' => 'Falta la lista de asistencia de la capacitación.',
        ]);
        $this->assertSame(AuditFinding::STATUS_EVIDENCE_REJECTED, $menor->fresh()->status);
        $this->actingAs($ana)->get(route('mi-espacio.findings.show', $menor))->assertSee('rechazó tu evidencia');

        $this->actingAs($ana)->post(route('mi-espacio.findings.evidence', $menor), [
            'files' => [
                UploadedFile::fake()->create('bitacora.pdf', 120, 'application/pdf'),
                UploadedFile::fake()->create('lista-asistencia.pdf', 80, 'application/pdf'),
            ],
        ])->assertRedirect();
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $menor), ['decision' => 'aprobar']);

        $this->assertSame(AuditFinding::STATUS_CLOSED, $menor->fresh()->status);
        Notification::assertSentToTimes($ana, AuditFindingReviewedNotification::class, 4);
        Notification::assertSentToTimes($auditor, AuditFindingSubmittedNotification::class, 4);
        $this->actingAs($ana)->get(route('mi-espacio.findings', ['tab' => 'concluidas']))->assertSee('Bitácora de descarga sin firma');

        // ---- 6. El crítico de Luis se venció (vencía el 18) ------------------------------------
        $this->artisan('audits:findings-overdue')->assertSuccessful();
        $this->artisan('audits:findings-overdue')->assertSuccessful();
        Notification::assertSentToTimes($luis, AuditFindingAlertNotification::class, 1);
        Notification::assertSentTo($dana, AuditFindingAlertNotification::class,
            fn ($n) => $n->reason === AuditFindingAlertNotification::REASON_OVERDUE);
        $this->actingAs($auditor)->get(route('auditorias.findings.index'))->assertSee('Vencido');

        $this->actingAs($luis)->post(route('mi-espacio.findings.root-cause', $critico), [
            'root_cause' => 'La pinza estaba dañada y no había repuesto.', 'actions' => 'Comprar dos pinzas y revisarlas en cada turno.',
        ])->assertRedirect();
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $critico), ['decision' => 'aprobar']);
        $this->actingAs($luis)->post(route('mi-espacio.findings.evidence', $critico), [
            'files' => [UploadedFile::fake()->image('pinza-nueva.jpg')],
        ])->assertRedirect();
        $this->actingAs($auditor)->post(route('auditorias.findings.review', $critico), ['decision' => 'aprobar']);
        $this->assertSame(AuditFinding::STATUS_CLOSED, $critico->fresh()->status);

        $this->actingAs($auditor)->get(route('auditorias.home'))->assertOk()->assertSee('Auditorías finalizadas');

        // ---- 7. Historial para la siguiente auditoría ----------------------------------------
        $this->assertSame([
            'registrado', 'causa_enviada', 'causa_devuelta', 'causa_enviada', 'causa_aprobada',
            'evidencia_enviada', 'evidencia_rechazada', 'evidencia_enviada', 'evidencia_aceptada',
        ], $menor->events()->reorder()->orderBy('id')->pluck('action')->all());
        $this->assertSame([$auditor->id], $menor->events()->where('action', 'like', '%aprobada')->pluck('user_id')->unique()->values()->all());
        $this->assertContains('plazo_vencido', $critico->events()->pluck('action')->all());

        $this->artisan('audits:historial-hallazgos', ['responsable' => $ana->email, '--detalle' => true])
            ->expectsOutputToContain('Bitácora de descarga sin firma')
            ->expectsOutputToContain('Falta la lista de asistencia de la capacitación.')
            ->assertSuccessful();
    }
}
