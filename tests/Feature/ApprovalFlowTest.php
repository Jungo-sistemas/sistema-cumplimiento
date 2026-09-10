<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalFlowMemberNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\ApprovalStepUnassignedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationRejectedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Cubre App\Services\ApprovalFlowService::FLOWS tal como está definido HOY (lider → jefe →
 * gerente → direccion). Antes de 2026-09 este archivo probaba un diseño de flujo viejo
 * (posiciones "ejecutivo_reglamentos"/"direccion_general"/"director_finanzas", pasos con 2
 * aprobadores en paralelo) que ya no existe en el código — JobPositionSeeder renombró/eliminó
 * esos puestos hace tiempo y ApprovalFlowService se rediseñó a 4 puestos jerárquicos, pero nadie
 * actualizó estos tests: quedaron 12/20 en rojo, sin que nadie lo notara, así que dejaron de
 * servir como red de seguridad real. Reescrito para reflejar el diseño actual.
 */
class ApprovalFlowTest extends TestCase
{
    use RefreshDatabase;

    private ApprovalFlowService $flow;
    private Group $group;
    private Company $company;
    private ProcessType $processType;
    private array $users     = [];
    private array $positions = [];
    private int $opRoleId;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->flow = app(ApprovalFlowService::class);
        $this->setUpFixtures();
    }

    // ─── Setup ───────────────────────────────────────────────────────────────

    private function setUpFixtures(): void
    {
        $this->group       = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company     = Company::create(['name' => 'Demo', 'group_id' => $this->group->id]);
        $this->processType = ProcessType::create([
            'group_id' => $this->group->id,
            'name'     => 'Operaciones',
            'is_active' => true,
        ]);

        $adminRole      = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $opRole         = Role::create(['name' => 'Operativo',     'slug' => 'operative']);
        $this->opRoleId = $opRole->id;

        // Puestos jerárquicos canónicos — ver JobPositionSeeder::POSITIONS. Un usuario por puesto
        // por defecto; test_alto_paso1_sequential_dos_aprobadores agrega un segundo a 'lider'.
        foreach (['lider', 'jefe', 'gerente', 'direccion'] as $slug) {
            $pos = JobPosition::create([
                'group_id' => $this->group->id,
                'slug'     => $slug,
                'name'     => ucfirst($slug),
            ]);
            $this->positions[$slug] = $pos;

            $user = User::factory()->create([
                'group_id'    => $this->group->id,
                'scope_level' => 'group',
                'role_id'     => $opRole->id,
                'status'      => 'active',
            ]);
            $user->jobPositions()->attach($pos->id);
            $this->users[$slug] = $user;
        }

        $this->users['admin'] = User::factory()->create([
            'group_id'    => $this->group->id,
            'scope_level' => 'group',
            'role_id'     => $adminRole->id,
            'status'      => 'active',
        ]);
    }

    private function makeRegulation(string $level, ?int $createdBy = null): Regulation
    {
        $reg = Regulation::create([
            'group_id'        => $this->group->id,
            'company_id'      => $this->company->id,
            'process_type_id' => $this->processType->id,
            'name'            => 'TEST-' . strtoupper($level),
            'impact_level'    => $level,
            'approval_status' => 'pending_review',
            'flow_locked'     => true,
            'details'         => [],
            'is_active'       => true,
            'created_by'      => $createdBy,
        ]);
        $this->flow->initFlow($reg);
        return $reg->fresh();
    }

    private function approveAs(Regulation $regulation, User $user, string $status = 'approved', ?string $comments = null): void
    {
        $approval = $regulation->fresh()->pendingApprovals()->where('user_id', $user->id)->firstOrFail();
        $this->flow->processApproval($approval, $status, $comments);
    }

    // ─── BAJO: 1 paso, 1 puesto (lider) ─────────────────────────────────────

    public function test_bajo_inicia_con_un_solo_pending_para_lider(): void
    {
        $reg = $this->makeRegulation('bajo');

        $this->assertDatabaseCount('regulation_approvals', 1);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 1,
            'user_id'       => $this->users['lider']->id,
            'status'        => 'pending',
        ]);
    }

    public function test_bajo_lider_aprueba_y_documento_queda_aprobado(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->approveAs($reg, $this->users['lider']);

        $this->assertEquals('approved', $reg->fresh()->approval_status);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'status'        => 'pending',
        ]);
    }

    // ─── MEDIO: paso1 AND[lider] → paso2 OR[jefe, gerente] ──────────────────

    public function test_medio_paso1_aprobado_crea_paso2_con_jefe_y_gerente(): void
    {
        $reg = $this->makeRegulation('medio');
        $this->approveAs($reg, $this->users['lider']);

        $reg->refresh();
        $this->assertEquals('pending_authorization', $reg->approval_status);

        $paso2Ids = $reg->approvalStep(2)->where('status', 'pending')->pluck('user_id')->sort()->values();
        $expected = collect([$this->users['jefe']->id, $this->users['gerente']->id])->sort()->values();
        $this->assertEquals($expected, $paso2Ids);
    }

    public function test_medio_paso2_or_jefe_aprueba_y_cancela_gerente(): void
    {
        $reg = $this->makeRegulation('medio');
        $this->approveAs($reg, $this->users['lider']);
        $this->approveAs($reg, $this->users['jefe']);

        $this->assertEquals('approved', $reg->fresh()->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'user_id'       => $this->users['gerente']->id,
            'status'        => 'cancelled',
        ]);
    }

    // ─── ALTO: paso1 AND[lider] → paso2 AND[jefe] → paso3 AND[gerente] → paso4 AND[direccion] ──

    public function test_alto_flujo_completo_cuatro_aprobaciones_en_orden(): void
    {
        $reg = $this->makeRegulation('alto');

        $this->approveAs($reg, $this->users['lider']);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);

        $this->approveAs($reg, $this->users['jefe']);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);

        $this->approveAs($reg, $this->users['gerente']);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);

        $this->approveAs($reg, $this->users['direccion']);
        $this->assertEquals('approved', $reg->fresh()->approval_status);
    }

    public function test_alto_no_se_puede_aprobar_fuera_de_orden(): void
    {
        $reg = $this->makeRegulation('alto');

        // 'gerente' no tiene aprobación pendiente todavía (paso 3 aún no existe) — el 403 real de
        // approve() (getPendingApprovalForUser devuelve null) es la única barrera contra saltarse
        // pasos; probarlo aquí en vez de solo confiar en que el flujo "nunca lo intentaría".
        $this->assertNull($this->flow->getPendingApprovalForUser($reg, $this->users['gerente']->id));
    }

    /**
     * Puesto con MÁS de un usuario asignado en un paso "requires_all": deben aprobar uno a la vez
     * (secuencial), no en paralelo — ver ApprovalFlowService::createStepRecords()/
     * promoteNextWaitingApprover(). Sin este test, una regresión a "todos pending desde el
     * inicio" pasaría inadvertida porque ningún otro test tiene más de un usuario por puesto.
     */
    public function test_alto_paso1_con_dos_lideres_es_secuencial_no_paralelo(): void
    {
        $segundoLider = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group',
            'role_id' => $this->opRoleId, 'status' => 'active',
        ]);
        $segundoLider->jobPositions()->attach($this->positions['lider']->id);

        $reg = $this->makeRegulation('alto');

        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id, 'user_id' => $this->users['lider']->id, 'status' => 'pending',
        ]);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id, 'user_id' => $segundoLider->id, 'status' => 'waiting',
        ]);

        // El segundo líder no debe recibir el correo accionable todavía — solo le toca cuando el
        // primero decide.
        Notification::assertNotSentTo($segundoLider, ApprovalRequestedNotification::class);

        $this->approveAs($reg, $this->users['lider']);

        // El paso 1 sigue sin completarse: el segundo líder ahora sí está pending.
        $this->assertEquals('pending_review', $reg->fresh()->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id, 'user_id' => $segundoLider->id, 'status' => 'pending',
        ]);
        Notification::assertSentTo($segundoLider, ApprovalRequestedNotification::class);

        $this->approveAs($reg, $segundoLider);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);
    }

    // ─── MEDIO-ALTO: paso1 AND[lider] → paso2 AND[gerente] → paso3 AND[direccion] ──

    public function test_medio_alto_flujo_completo_tres_aprobaciones(): void
    {
        $reg = $this->makeRegulation('medio_alto');

        $this->approveAs($reg, $this->users['lider']);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);

        $this->approveAs($reg, $this->users['gerente']);
        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);

        $this->approveAs($reg, $this->users['direccion']);
        $this->assertEquals('approved', $reg->fresh()->approval_status);
    }

    /**
     * Bug real encontrado (2026-09): si un puesto de un paso no tiene ningún usuario asignado en
     * la empresa (o flow_user_map apunta a alguien inexistente/inactivo), createStepRecords()
     * creaba 0 registros para ese paso — el reglamento quedaba en "pending_authorization" para
     * siempre, sin ninguna aprobación pendiente sobre la cual nadie pudiera actuar, y sin avisar
     * a nadie. Fix: ApprovalStepUnassignedNotification avisa a los admins de Procesos apenas pasa.
     */
    public function test_paso_sin_usuarios_asignados_notifica_a_admins_en_vez_de_quedar_atorado_en_silencio(): void
    {
        // 'direccion' existe como puesto pero nadie de este grupo está asignado a él.
        $this->users['direccion']->jobPositions()->detach($this->positions['direccion']->id);

        $reg = $this->makeRegulation('medio_alto');
        $this->approveAs($reg, $this->users['lider']);
        $this->approveAs($reg, $this->users['gerente']);

        $reg->refresh();
        $this->assertEquals('pending_authorization', $reg->approval_status);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 3,
        ]);

        Notification::assertSentTo($this->users['admin'], ApprovalStepUnassignedNotification::class);
    }

    // ─── RECHAZO ─────────────────────────────────────────────────────────────

    public function test_rechazo_en_paso1_cancela_incluso_a_quien_esperaba_turno(): void
    {
        $segundoLider = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group',
            'role_id' => $this->opRoleId, 'status' => 'active',
        ]);
        $segundoLider->jobPositions()->attach($this->positions['lider']->id);

        $reg = $this->makeRegulation('alto'); // 1 pending + 1 waiting en paso 1

        $approval = $reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail();
        $this->flow->processApproval($approval, 'rejected', 'Información incompleta');

        $reg->refresh();
        $this->assertEquals('rejected', $reg->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id, 'user_id' => $segundoLider->id, 'status' => 'cancelled',
        ]);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'status'        => 'pending',
        ]);
    }

    public function test_rechazo_en_paso2_or_cancela_al_otro_aprobador_del_paso(): void
    {
        $reg = $this->makeRegulation('medio');
        $this->approveAs($reg, $this->users['lider']);

        $reg->refresh();
        $jefeApproval = $reg->pendingApprovals()->where('user_id', $this->users['jefe']->id)->firstOrFail();
        $this->flow->processApproval($jefeApproval, 'rejected', 'No procede');

        $this->assertEquals('rejected', $reg->fresh()->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id, 'user_id' => $this->users['gerente']->id, 'status' => 'cancelled',
        ]);
    }

    // ─── RESUBMIT ────────────────────────────────────────────────────────────

    public function test_resubmit_tras_rechazo_reinicia_paso1(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->approveAs($reg, $this->users['lider'], 'rejected', 'x');

        $this->flow->resubmit($reg->fresh());

        $reg->refresh();
        $this->assertEquals('pending_review', $reg->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 1,
            'user_id'       => $this->users['lider']->id,
            'status'        => 'pending',
        ]);
    }

    // ─── NOTIFICACIONES ──────────────────────────────────────────────────────

    public function test_initflow_notifica_al_aprobador_del_paso1(): void
    {
        $this->makeRegulation('bajo');

        Notification::assertSentTo($this->users['lider'], ApprovalRequestedNotification::class);
    }

    public function test_initflow_notifica_de_forma_informativa_a_puestos_de_pasos_futuros(): void
    {
        $this->makeRegulation('alto');

        // jefe/gerente/direccion participan en pasos 2-4 — deben enterarse desde ya (informativo),
        // sin que eso los ponga "pending" antes de tiempo.
        Notification::assertSentTo($this->users['jefe'], ApprovalFlowMemberNotification::class);
        Notification::assertSentTo($this->users['gerente'], ApprovalFlowMemberNotification::class);
        Notification::assertSentTo($this->users['direccion'], ApprovalFlowMemberNotification::class);
        Notification::assertNotSentTo($this->users['jefe'], ApprovalRequestedNotification::class);
    }

    public function test_aprobacion_final_notifica_al_creador_y_a_todos_los_que_aprobaron(): void
    {
        $creator = User::factory()->create(['group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'role_id' => $this->opRoleId]);
        $reg     = $this->makeRegulation('medio_alto', $creator->id);

        $this->approveAs($reg, $this->users['lider']);
        $this->approveAs($reg, $this->users['gerente']);
        $this->approveAs($reg, $this->users['direccion']);

        Notification::assertSentTo($creator, RegulationApprovedNotification::class);
        Notification::assertSentTo($this->users['lider'], RegulationApprovedNotification::class);
        Notification::assertSentTo($this->users['gerente'], RegulationApprovedNotification::class);
        Notification::assertSentTo($this->users['direccion'], RegulationApprovedNotification::class);
    }

    public function test_rechazo_notifica_al_creador(): void
    {
        $creator = User::factory()->create(['group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'role_id' => $this->opRoleId]);
        $reg     = $this->makeRegulation('bajo', $creator->id);

        $this->approveAs($reg, $this->users['lider'], 'rejected', 'Motivo');

        Notification::assertSentTo($creator, RegulationRejectedNotification::class);
    }

    // ─── HTTP: autorización ──────────────────────────────────────────────────

    public function test_aprobar_sin_pending_da_403(): void
    {
        $reg     = $this->makeRegulation('bajo');
        $intruso = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group',
            'status' => 'active', 'role_id' => $this->opRoleId,
        ]);

        $this->actingAs($intruso)
            ->post(route('processes.approve', $reg))
            ->assertStatus(403);
    }

    public function test_rechazar_sin_comentario_falla_validacion(): void
    {
        $reg = $this->makeRegulation('bajo');

        $this->actingAs($this->users['lider'])
            ->post(route('processes.reject', $reg), ['comments' => ''])
            ->assertSessionHasErrors('comments');
    }

    public function test_rechazar_con_comentario_funciona(): void
    {
        $reg = $this->makeRegulation('bajo');

        $this->actingAs($this->users['lider'])
            ->post(route('processes.reject', $reg), ['comments' => 'Motivo válido'])
            ->assertRedirect();

        $this->assertEquals('rejected', $reg->fresh()->approval_status);
    }

    public function test_resubmit_sin_ser_admin_da_403(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->approveAs($reg, $this->users['lider'], 'rejected', 'x');

        $this->actingAs($this->users['lider'])
            ->post(route('processes.resubmit', $reg->fresh()))
            ->assertStatus(403);
    }

    public function test_resubmit_como_admin_funciona(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->approveAs($reg, $this->users['lider'], 'rejected', 'x');

        $this->actingAs($this->users['admin'])
            ->post(route('processes.resubmit', $reg->fresh()))
            ->assertRedirect();

        $this->assertEquals('pending_review', $reg->fresh()->approval_status);
    }
}
