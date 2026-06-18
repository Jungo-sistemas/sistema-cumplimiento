<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationRejectedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

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

        $adminRole        = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $opRole           = Role::create(['name' => 'Operativo',     'slug' => 'operative']);
        $this->opRoleId   = $opRole->id;

        foreach (['ejecutivo_reglamentos', 'lider', 'gerente', 'direccion_general', 'director_finanzas'] as $slug) {
            $pos = JobPosition::create([
                'group_id' => $this->group->id,
                'slug'     => $slug,
                'name'     => ucfirst(str_replace('_', ' ', $slug)),
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

    // ─── BAJO: 1 paso AND ────────────────────────────────────────────────────

    public function test_bajo_inicia_con_un_solo_pending_para_ejecutivo(): void
    {
        $reg = $this->makeRegulation('bajo');

        $this->assertDatabaseCount('regulation_approvals', 1);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 1,
            'user_id'       => $this->users['ejecutivo_reglamentos']->id,
            'status'        => 'pending',
        ]);
    }

    public function test_bajo_ejecutivo_aprueba_y_documento_queda_aprobado(): void
    {
        $reg      = $this->makeRegulation('bajo');
        $approval = $reg->pendingApprovals()->firstOrFail();

        $this->flow->processApproval($approval, 'approved');

        $this->assertEquals('approved', $reg->fresh()->approval_status);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'status'        => 'pending',
        ]);
    }

    // ─── MEDIO: paso 1 AND → paso 2 OR ──────────────────────────────────────

    public function test_medio_paso1_aprobado_crea_paso2_con_lider_y_gerente(): void
    {
        $reg      = $this->makeRegulation('medio');
        $approval = $reg->pendingApprovals()->where('user_id', $this->users['ejecutivo_reglamentos']->id)->firstOrFail();

        $this->flow->processApproval($approval, 'approved');

        $reg->refresh();
        $this->assertEquals('pending_authorization', $reg->approval_status);

        $paso2Ids = $reg->approvalStep(2)->where('status', 'pending')->pluck('user_id')->sort()->values();
        $expected = collect([$this->users['lider']->id, $this->users['gerente']->id])->sort()->values();
        $this->assertEquals($expected, $paso2Ids);
    }

    public function test_medio_paso2_or_lider_aprueba_y_cancela_gerente(): void
    {
        $reg = $this->makeRegulation('medio');
        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'approved');

        $reg->refresh();
        $this->flow->processApproval(
            $reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(),
            'approved'
        );

        $this->assertEquals('approved', $reg->fresh()->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'user_id'       => $this->users['gerente']->id,
            'status'        => 'cancelled',
        ]);
    }

    // ─── ALTO: paso 1 AND (lider+ejecutivo) → paso 2 AND (dirección) ────────

    public function test_alto_paso1_no_avanza_si_solo_aprueba_uno(): void
    {
        $reg = $this->makeRegulation('alto');

        $this->flow->processApproval(
            $reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(),
            'approved'
        );

        $reg->refresh();
        $this->assertEquals('pending_review', $reg->approval_status);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 2,
        ]);
    }

    public function test_alto_paso1_completo_crea_paso2_para_direccion(): void
    {
        $reg = $this->makeRegulation('alto');
        $this->flow->processApproval($reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['ejecutivo_reglamentos']->id)->firstOrFail(), 'approved');

        $reg->refresh();
        $this->assertEquals('pending_authorization', $reg->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 2,
            'user_id'       => $this->users['direccion_general']->id,
            'status'        => 'pending',
        ]);
    }

    public function test_alto_flujo_completo_tres_aprobaciones(): void
    {
        $reg = $this->makeRegulation('alto');
        $this->flow->processApproval($reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['ejecutivo_reglamentos']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['direccion_general']->id)->firstOrFail(), 'approved');

        $this->assertEquals('approved', $reg->fresh()->approval_status);
    }

    // ─── MEDIO-ALTO: paso 2 AND (dirección + finanzas) ──────────────────────

    public function test_medio_alto_paso2_no_completa_si_falta_uno(): void
    {
        $reg = $this->makeRegulation('medio_alto');
        $this->flow->processApproval($reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['ejecutivo_reglamentos']->id)->firstOrFail(), 'approved');

        $reg->refresh();
        $this->flow->processApproval($reg->pendingApprovals()->where('user_id', $this->users['direccion_general']->id)->firstOrFail(), 'approved');

        $this->assertEquals('pending_authorization', $reg->fresh()->approval_status);
    }

    public function test_medio_alto_flujo_completo_cuatro_aprobaciones(): void
    {
        $reg = $this->makeRegulation('medio_alto');
        $this->flow->processApproval($reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['ejecutivo_reglamentos']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['direccion_general']->id)->firstOrFail(), 'approved');
        $this->flow->processApproval($reg->fresh()->pendingApprovals()->where('user_id', $this->users['director_finanzas']->id)->firstOrFail(), 'approved');

        $this->assertEquals('approved', $reg->fresh()->approval_status);
    }

    // ─── RECHAZO ─────────────────────────────────────────────────────────────

    public function test_rechazo_en_paso1_cancela_todos_los_pending(): void
    {
        $reg = $this->makeRegulation('alto'); // 2 pending en paso 1

        $this->flow->processApproval(
            $reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(),
            'rejected',
            'Información incompleta'
        );

        $reg->refresh();
        $this->assertEquals('rejected', $reg->approval_status);
        $this->assertDatabaseMissing('regulation_approvals', [
            'regulation_id' => $reg->id,
            'status'        => 'pending',
        ]);
    }

    public function test_rechazo_en_paso2_cancela_los_demas_del_paso(): void
    {
        $reg = $this->makeRegulation('medio');
        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'approved');
        $reg->refresh();

        $this->flow->processApproval(
            $reg->pendingApprovals()->where('user_id', $this->users['lider']->id)->firstOrFail(),
            'rejected',
            'No procede'
        );

        $this->assertEquals('rejected', $reg->fresh()->approval_status);
    }

    // ─── RESUBMIT ────────────────────────────────────────────────────────────

    public function test_resubmit_tras_rechazo_reinicia_paso1(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'rejected', 'x');

        $this->flow->resubmit($reg->fresh());

        $reg->refresh();
        $this->assertEquals('pending_review', $reg->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $reg->id,
            'step_number'   => 1,
            'user_id'       => $this->users['ejecutivo_reglamentos']->id,
            'status'        => 'pending',
        ]);
    }

    // ─── NOTIFICACIONES ──────────────────────────────────────────────────────

    public function test_initflow_notifica_al_aprobador_del_paso1(): void
    {
        $this->makeRegulation('bajo');

        Notification::assertSentTo(
            $this->users['ejecutivo_reglamentos'],
            ApprovalRequestedNotification::class
        );
    }

    public function test_aprobacion_final_notifica_al_creador(): void
    {
        $creator = User::factory()->create(['group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'role_id' => $this->opRoleId]);
        $reg     = $this->makeRegulation('bajo', $creator->id);

        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'approved');

        Notification::assertSentTo($creator, RegulationApprovedNotification::class);
    }

    public function test_rechazo_notifica_al_creador(): void
    {
        $creator = User::factory()->create(['group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'role_id' => $this->opRoleId]);
        $reg     = $this->makeRegulation('bajo', $creator->id);

        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'rejected', 'Motivo');

        Notification::assertSentTo($creator, RegulationRejectedNotification::class);
    }

    // ─── HTTP: autorización ──────────────────────────────────────────────────

    public function test_aprobar_sin_pending_da_403(): void
    {
        $reg      = $this->makeRegulation('bajo');
        $intruso  = User::factory()->create([
            'group_id'    => $this->group->id,
            'scope_level' => 'group',
            'status'      => 'active',
            'role_id'     => $this->opRoleId,
        ]);

        $this->actingAs($intruso)
            ->post(route('processes.approve', $reg))
            ->assertStatus(403);
    }

    public function test_rechazar_sin_comentario_falla_validacion(): void
    {
        $reg      = $this->makeRegulation('bajo');
        $ejecutivo = $this->users['ejecutivo_reglamentos'];

        $this->actingAs($ejecutivo)
            ->post(route('processes.reject', $reg), ['comments' => ''])
            ->assertSessionHasErrors('comments');
    }

    public function test_rechazar_con_comentario_funciona(): void
    {
        $reg      = $this->makeRegulation('bajo');
        $ejecutivo = $this->users['ejecutivo_reglamentos'];

        $this->actingAs($ejecutivo)
            ->post(route('processes.reject', $reg), ['comments' => 'Motivo válido'])
            ->assertRedirect();

        $this->assertEquals('rejected', $reg->fresh()->approval_status);
    }

    public function test_resubmit_sin_ser_admin_da_403(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'rejected', 'x');

        $this->actingAs($this->users['ejecutivo_reglamentos'])
            ->post(route('processes.resubmit', $reg->fresh()))
            ->assertStatus(403);
    }

    public function test_resubmit_como_admin_funciona(): void
    {
        $reg = $this->makeRegulation('bajo');
        $this->flow->processApproval($reg->pendingApprovals()->firstOrFail(), 'rejected', 'x');

        $this->actingAs($this->users['admin'])
            ->post(route('processes.resubmit', $reg->fresh()))
            ->assertRedirect();

        $this->assertEquals('pending_review', $reg->fresh()->approval_status);
    }
}
