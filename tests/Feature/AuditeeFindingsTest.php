<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuditCompletedNotification;
use App\Notifications\AuditFindingAlertNotification;
use App\Notifications\AuditFindingAssignedNotification;
use App\Notifications\AuditFindingReviewedNotification;
use App\Notifications\AuditFindingSubmittedNotification;
use App\Services\AuditFindingService;
use App\Support\BusinessDays;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Tests\TestCase;

/**
 * Mis hallazgos (rol auditado), flujo de la lámina del cliente: el auditor documenta → el
 * responsable manda causa raíz y acciones → el auditor las aprueba o las devuelve → el responsable
 * sube la evidencia del seguimiento → el auditor la verifica (cierra) o la rechaza. Plazos en días
 * hábiles por severidad (crítico / no crítico).
 */
class AuditeeFindingsTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private User $auditor;
    private User $ana;
    private User $luis;
    private User $owner;
    private Regulation $regulation;
    private Audit $audit;
    private AuditFindingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        Storage::fake('private');
        // Martes 17 de noviembre de 2026 (el lunes 16 fue día inhábil: tercer lunes de noviembre).
        $this->travelTo(Carbon::create(2026, 11, 17, 9, 0));
        $this->service = app(AuditFindingService::class);

        $this->group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $roleId = Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id;

        foreach (['auditor' => 'Pedro Auditor', 'ana' => 'Ana Torres', 'luis' => 'Luis Otro', 'owner' => 'Olga Dueña'] as $prop => $name) {
            $this->{$prop} = User::factory()->create([
                'name' => $name, 'group_id' => $this->group->id, 'company_id' => $company->id,
                'scope_level' => 'company', 'status' => 'active', 'role_id' => $roleId,
            ]);
        }

        $type = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $this->regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-300', 'name' => 'Carga de autotanques', 'is_active' => true, 'approval_status' => 'approved',
        ]);
        // Olga es responsable del procedimiento; Ana también.
        $this->regulation->responsables()->attach([$this->owner->id, $this->ana->id]);

        $this->audit = Audit::create([
            'group_id' => $this->group->id, 'company_id' => $company->id, 'regulation_id' => $this->regulation->id,
            'auditor_id' => $this->auditor->id, 'planned_for' => '2026-11-01', 'scheduled_at' => '2026-11-12 10:00:00',
        ]);
        $this->audit->auditees()->attach([$this->ana->id, $this->luis->id]);
    }

    private function finding(string $severity = 'no_critico', ?User $responsible = null, string $point = 'Paso 4: bitácora de descarga'): AuditFinding
    {
        return $this->service->register($this->audit, $responsible ?? $this->ana, $point, 'No se llenó la bitácora.', $severity);
    }

    private function sendRootCause(AuditFinding $finding, array $extra = [])
    {
        return $this->actingAs($this->ana)->post(route('mi-espacio.findings.root-cause', $finding), $extra + [
            'root_cause' => 'El supervisor no sabía que debía firmar.',
            'actions'    => 'Agregar la firma al formato y capacitar a los supervisores.',
        ]);
    }

    private function upload(AuditFinding $finding, int $count = 2, array $extra = [])
    {
        $files = collect(range(1, $count))->map(fn ($i) => UploadedFile::fake()->create("evidencia{$i}.pdf", 100, 'application/pdf'))->all();

        return $this->actingAs($this->ana)->post(route('mi-espacio.findings.evidence', $finding), ['files' => $files] + $extra);
    }

    /** Lleva el hallazgo hasta el seguimiento (causa raíz aprobada). */
    private function toFollowUp(AuditFinding $finding): AuditFinding
    {
        $this->sendRootCause($finding)->assertSessionHasNoErrors();
        $this->service->review($finding->fresh(), true);

        return $finding->fresh();
    }

    public function test_dias_habiles_saltan_fines_de_semana_y_dias_de_la_lft(): void
    {
        // Viernes 13 nov + 1 día hábil = martes 17 (sábado, domingo y el lunes 16 de noviembre no cuentan).
        $this->assertSame('2026-11-17', BusinessDays::add(Carbon::create(2026, 11, 13), 1)->toDateString());
        $this->assertSame('2026-12-01', BusinessDays::add(Carbon::create(2026, 11, 17), 10)->toDateString());
        $this->assertFalse(BusinessDays::isBusinessDay(Carbon::create(2026, 12, 25)));
        $this->assertFalse(BusinessDays::isBusinessDay(Carbon::create(2030, 10, 1)));
    }

    public function test_el_hallazgo_avisa_al_responsable_y_a_los_responsables_del_procedimiento(): void
    {
        $finding = $this->finding();

        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_PENDING, $finding->status);
        // No crítico: 3 días hábiles para la causa raíz.
        $this->assertSame('2026-11-20', $finding->due_at->toDateString());

        Notification::assertSentTo($this->ana, AuditFindingAssignedNotification::class, fn ($n) => ! $n->asProcessOwner);
        Notification::assertSentTo($this->owner, AuditFindingAssignedNotification::class, fn ($n) => $n->asProcessOwner);
        Notification::assertSentToTimes($this->ana, AuditFindingAssignedNotification::class, 1); // no le llega doble
        Notification::assertNotSentTo($this->luis, AuditFindingAssignedNotification::class);
        Notification::assertNothingSentTo($this->auditor);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))
            ->assertOk()->assertSee('Paso 4: bitácora de descarga')->assertSee('Escribe la causa raíz')->assertSee('20/11/2026');
        $this->actingAs($this->luis)->get(route('mi-espacio.findings'))
            ->assertOk()->assertDontSee('Paso 4: bitácora de descarga');

        // Inicio, menú y campana lo cuentan.
        $this->actingAs($this->ana)->get(route('mi-espacio.home'))
            ->assertSeeInOrder(['Mis hallazgos', '1'])
            ->assertSee('Hallazgos por atender')
            ->assertSee('Escribir causa raíz');

        // La responsable del procedimiento puede abrirlo (sin formulario).
        $this->actingAs($this->owner)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertSee('Responsable:')->assertDontSee('Enviar al auditor');
    }

    public function test_hallazgo_critico_tiene_plazos_cortos_y_avisa_a_quien_recibe_avisos_de_auditorias(): void
    {
        $alerts = User::factory()->create(['group_id' => $this->group->id, 'status' => 'active', 'role_id' => $this->ana->role_id, 'receives_audit_alerts' => true]);
        $otherGroup = Group::create(['name' => 'Otro', 'slug' => 'otro', 'is_active' => true]);
        $alienAlerts = User::factory()->create(['group_id' => $otherGroup->id, 'status' => 'active', 'role_id' => $this->ana->role_id, 'receives_audit_alerts' => true]);

        $finding = $this->finding('critico');

        $this->assertSame('2026-11-18', $finding->due_at->toDateString()); // 1 día hábil
        Notification::assertSentTo($alerts, AuditFindingAlertNotification::class, fn ($n) => $n->reason === 'critico');
        Notification::assertNotSentTo($alienAlerts, AuditFindingAlertNotification::class);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertSee('Hallazgo crítico')->assertSee('contención inmediata')->assertSee('6 días hábiles');

        // Un no crítico no les avisa.
        $this->finding('no_critico', point: 'Otro punto');
        Notification::assertSentToTimes($alerts, AuditFindingAlertNotification::class, 1);
    }

    public function test_el_responsable_tiene_que_ser_un_auditado_y_la_severidad_valida(): void
    {
        try {
            $this->service->register($this->audit, $this->auditor, 'Punto', 'Algo');
            $this->fail('Debía rechazar a un responsable que no es auditado.');
        } catch (InvalidArgumentException) {
        }

        $this->expectException(InvalidArgumentException::class);
        $this->service->register($this->audit, $this->ana, 'Punto', 'Algo', 'mayor');
    }

    public function test_envia_causa_raiz_y_queda_en_validacion_del_auditor(): void
    {
        $finding = $this->finding();

        // Sin evidencia todavía: primero va la causa raíz.
        $this->upload($finding, 1)->assertRedirect();
        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_PENDING, $finding->fresh()->status);

        $this->sendRootCause($finding, ['root_cause' => ''])->assertSessionHasErrors('root_cause');
        $this->sendRootCause($finding)->assertRedirect(route('mi-espacio.findings.show', $finding));

        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_REVIEW, $finding->status);
        $this->assertSame('2026-11-19', $finding->due_at->toDateString()); // validar: 2 días hábiles
        $this->assertSame('El supervisor no sabía que debía firmar.', $finding->latestSubmission->root_cause);
        Notification::assertSentTo($this->auditor, AuditFindingSubmittedNotification::class, fn ($n) => $n->submission->isRootCause());

        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertSee('Causa raíz en revisión')->assertDontSee('Enviar al auditor')
            ->assertSee('Envió la causa raíz y las acciones');
    }

    public function test_el_auditor_devuelve_la_causa_raiz_y_se_corrige(): void
    {
        $finding = $this->finding();
        $this->sendRootCause($finding);

        $this->service->review($finding->fresh(), false, 'Describe el problema, no por qué pasó.');
        Notification::assertSentTo($this->ana, AuditFindingReviewedNotification::class, fn ($n) => $n->submission->isRootCause() && $n->submission->status === 'rechazada');

        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_REJECTED, $finding->status);
        $this->assertSame('2026-11-20', $finding->due_at->toDateString()); // la etapa vuelve a contar completa

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))
            ->assertSee('Causa raíz devuelta')->assertSee('Describe el problema, no por qué pasó.');
        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertSee('devolvió tu causa raíz')->assertSee('Corregir causa raíz y acciones')
            ->assertSee('El supervisor no sabía que debía firmar.'); // el texto anterior queda para corregirlo

        $this->sendRootCause($finding, ['root_cause' => 'No había quién revisara la bitácora en el turno de noche.'])->assertSessionHasNoErrors();
        $this->assertSame(AuditFinding::STATUS_ROOT_CAUSE_REVIEW, $finding->fresh()->status);
        $this->assertSame(2, $finding->submissions()->count());
    }

    public function test_causa_aprobada_inicia_el_seguimiento_y_la_evidencia_cierra(): void
    {
        $finding = $this->toFollowUp($this->finding());

        $this->assertSame(AuditFinding::STATUS_EVIDENCE_PENDING, $finding->status);
        $this->assertSame('2026-12-01', $finding->due_at->toDateString()); // corregir: 10 días hábiles
        Notification::assertSentTo($this->ana, AuditFindingReviewedNotification::class, fn ($n) => $n->submission->isRootCause() && $n->submission->status === 'aceptada');

        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))
            ->assertSee('Seguimiento: sube evidencia')->assertSee('Plan aprobado por el auditor')->assertSee('Subir evidencia del seguimiento');

        $this->upload($finding, 6)->assertSessionHasErrors('files');
        $this->upload($finding, 3, ['comment' => 'Adjunto la bitácora firmada'])->assertSessionHasNoErrors();

        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_EVIDENCE_REVIEW, $finding->status);
        $this->assertCount(3, $finding->latestSubmission->files);
        Storage::disk('private')->assertExists($finding->latestSubmission->files->first()->path);
        Notification::assertSentTo($this->auditor, AuditFindingSubmittedNotification::class, fn ($n) => ! $n->submission->isRootCause());

        // El auditor la rechaza (rebote) y luego la verifica.
        $this->service->review($finding, false, 'La foto no muestra la fecha.');
        $this->assertSame(AuditFinding::STATUS_EVIDENCE_REJECTED, $finding->fresh()->status);
        $this->actingAs($this->ana)->get(route('mi-espacio.findings.show', $finding))->assertSee('rechazó tu evidencia')->assertSee('Subir nueva evidencia');

        $this->upload($finding, 1)->assertSessionHasNoErrors();
        $this->service->review($finding->fresh(), true);

        $finding->refresh();
        $this->assertSame(AuditFinding::STATUS_CLOSED, $finding->status);
        $this->assertNotNull($finding->closed_at);
        $this->assertNull($finding->due_at);

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))->assertDontSee('Paso 4: bitácora de descarga');
        $this->actingAs($this->ana)->get(route('mi-espacio.findings', ['tab' => 'concluidas']))
            ->assertSee('Paso 4: bitácora de descarga')->assertSee('Cerrado el 17/11/2026');

        // La bitácora guarda todo el recorrido.
        $this->assertSame(
            ['registrado', 'causa_enviada', 'causa_aprobada', 'evidencia_enviada', 'evidencia_rechazada', 'evidencia_enviada', 'evidencia_aceptada'],
            $finding->events()->pluck('action')->all()
        );
    }

    public function test_solo_el_responsable_envia(): void
    {
        $finding = $this->finding();

        $this->actingAs($this->luis)
            ->post(route('mi-espacio.findings.root-cause', $finding), ['root_cause' => 'x', 'actions' => 'y'])
            ->assertForbidden();

        // Luis es auditado de la misma auditoría: puede ver el hallazgo, pero sin formulario.
        $this->actingAs($this->luis)->get(route('mi-espacio.findings.show', $finding))
            ->assertOk()->assertDontSee('Enviar al auditor');

        // Alguien ajeno no lo ve.
        $outsider = User::factory()->create(['group_id' => $this->group->id, 'status' => 'active', 'role_id' => $this->ana->role_id, 'scope_level' => 'group']);
        $this->actingAs($outsider)->get(route('mi-espacio.findings.show', $finding))->assertForbidden();
    }

    public function test_devolver_o_rechazar_sin_motivo_no_se_permite(): void
    {
        $finding = $this->finding();
        $this->sendRootCause($finding);

        $this->expectException(InvalidArgumentException::class);
        $this->service->review($finding->fresh(), false, '  ');
    }

    public function test_plazo_vencido_avisa_una_vez_por_etapa(): void
    {
        $alerts = User::factory()->create(['group_id' => $this->group->id, 'status' => 'active', 'role_id' => $this->ana->role_id, 'receives_audit_alerts' => true]);
        $finding = $this->finding(); // causa raíz para el viernes 20

        $this->artisan('audits:findings-overdue')->assertSuccessful();
        Notification::assertNotSentTo($this->ana, AuditFindingAlertNotification::class);

        $this->travelTo(Carbon::create(2026, 11, 23, 8, 15));
        $this->artisan('audits:findings-overdue')->assertSuccessful();
        $this->artisan('audits:findings-overdue')->assertSuccessful(); // no repite

        Notification::assertSentToTimes($this->ana, AuditFindingAlertNotification::class, 1);
        Notification::assertSentTo($alerts, AuditFindingAlertNotification::class, fn ($n) => $n->reason === 'vencido');
        $this->assertSame('plazo_vencido', $finding->events()->reorder()->latest('id')->value('action'));

        $this->actingAs($this->ana)->get(route('mi-espacio.findings'))->assertSee('Vencido');

        // En la etapa del auditor, el aviso le llega a él.
        $this->sendRootCause($finding);
        $this->travelTo(Carbon::create(2026, 11, 30, 8, 15));
        $this->artisan('audits:findings-overdue')->assertSuccessful();
        Notification::assertSentTo($this->auditor, AuditFindingAlertNotification::class);
    }

    public function test_quien_recibe_avisos_de_auditorias_se_configura_en_usuarios(): void
    {
        $admin = User::factory()->create([
            'group_id' => $this->group->id, 'status' => 'active', 'scope_level' => 'group', 'module_access' => 'all',
            'role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador'])->id,
        ]);

        $this->actingAs($admin)->patch(route('users.update', $this->luis), [
            'role_id' => $this->luis->role_id, 'module_access' => 'all', 'receives_audit_alerts' => '1',
        ])->assertRedirect();
        $this->assertTrue($this->luis->fresh()->receives_audit_alerts);

        // Un formulario sin el campo no lo cambia; con 0 lo quita.
        $this->actingAs($admin)->patch(route('users.update', $this->luis), ['role_id' => $this->luis->role_id, 'module_access' => 'all']);
        $this->assertTrue($this->luis->fresh()->receives_audit_alerts);
        $this->actingAs($admin)->patch(route('users.update', $this->luis), ['role_id' => $this->luis->role_id, 'module_access' => 'all', 'receives_audit_alerts' => '0']);
        $this->assertFalse($this->luis->fresh()->receives_audit_alerts);
    }

    public function test_la_calificacion_sale_en_historial_con_sus_hallazgos(): void
    {
        $this->finding();
        $this->finding('no_critico', $this->luis, 'Paso 7: firmas');

        $this->artisan('audits:complete', ['audit' => $this->audit->id, 'calificacion' => '85 / 100'])->assertSuccessful();
        Notification::assertSentTo([$this->ana, $this->luis], AuditCompletedNotification::class);

        $this->actingAs($this->ana)->get(route('mi-espacio.audits', ['tab' => 'historial']))
            ->assertOk()
            ->assertSee('Calificación')->assertSee('85 / 100')
            ->assertSee('2 hallazgos')->assertSee('2 abiertos')
            ->assertSee('Ver mis hallazgos (1)');
    }

    public function test_comandos_de_simulacion_del_auditor_e_historial(): void
    {
        $this->artisan('audits:add-finding', [
            'audit' => $this->audit->id, 'responsable' => $this->ana->email,
            'punto' => 'Paso 2: EPP', 'descripcion' => 'Sin casco', '--severidad' => 'critico',
        ])->assertSuccessful();

        $finding = AuditFinding::where('point', 'Paso 2: EPP')->sole();
        $this->assertTrue($finding->isCritical());
        $this->assertSame('2026-11-18', $finding->due_at->toDateString());

        $this->artisan('audits:review-finding', ['hallazgo' => $finding->id, 'decision' => 'aprobar'])->assertFailed(); // nada en revisión
        $this->sendRootCause($finding);
        $this->artisan('audits:review-finding', ['hallazgo' => $finding->id, 'decision' => 'rechazar'])->assertFailed(); // falta motivo
        $this->artisan('audits:review-finding', ['hallazgo' => $finding->id, 'decision' => 'aprobar'])->assertSuccessful();
        $this->assertSame(AuditFinding::STATUS_EVIDENCE_PENDING, $finding->fresh()->status);
        $this->assertSame('2026-11-19', $finding->fresh()->due_at->toDateString()); // crítico: 2 días hábiles

        $this->artisan('audits:historial-hallazgos', ['responsable' => $this->ana->email, '--detalle' => true])
            ->expectsOutputToContain('Paso 2: EPP')
            ->expectsOutputToContain('Aprobó la causa raíz')
            ->assertSuccessful();
    }
}
