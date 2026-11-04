<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Ajustes al flujo de Procesos: el correo no lleva qué cambió, la síntesis se puede cambiar al
 * re-enviar un rechazado, impacto para evaluación en el listado y cancelación de aprobaciones al
 * enviar a la papelera.
 */
class ProcessFlowAdjustmentsTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id, 'show_in_processes' => true]);
        $this->admin   = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
    }

    private function regulation(string $status, ?string $impact = 'bajo'): Regulation
    {
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);

        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => 'ALTA DE PROVEEDORES',
            'is_active' => true, 'approval_status' => $status, 'impact_level' => $impact,
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
            'change_description' => 'Se cambió la sección de alcance',
        ]);

        return $regulation;
    }

    public function test_el_correo_de_aprobacion_no_incluye_que_cambio(): void
    {
        $regulation = $this->regulation('pending_review');

        $html = (string) (new ApprovalRequestedNotification($regulation))->toMail($this->admin)->render();

        $this->assertStringContainsString('ALTA DE PROVEEDORES', $html);
        $this->assertStringNotContainsString('Qué cambió en esta versión', $html);
        $this->assertStringNotContainsString('Se cambió la sección de alcance', $html);
    }

    public function test_al_reenviar_un_rechazado_se_puede_reemplazar_la_sintesis(): void
    {
        Storage::fake('private');
        Notification::fake();
        $regulation = $this->regulation('rejected');

        $this->actingAs($this->admin)
            ->post(route('processes.resubmit', $regulation), [
                'change_summary' => UploadedFile::fake()->create('sintesis.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHas('success');

        $regulation->refresh();
        $this->assertSame('pending_review', $regulation->approval_status);
        $this->assertSame('sintesis.pdf', $regulation->change_summary_original_name);
        Storage::disk('private')->assertExists($regulation->change_summary_path);
    }

    public function test_admin_define_el_impacto_para_evaluacion(): void
    {
        $regulation = $this->regulation('approved');

        $this->actingAs($this->admin)
            ->patchJson(route('processes.setEvaluationImpact', $regulation), ['evaluation_impact' => 'alto'])
            ->assertOk();
        $this->assertSame('alto', $regulation->fresh()->evaluation_impact);

        $this->actingAs($this->admin)
            ->patchJson(route('processes.setEvaluationImpact', $regulation), ['evaluation_impact' => 'medio_alto'])
            ->assertStatus(422);

        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Impacto');
    }

    private function liderWithFlow(Regulation $regulation): User
    {
        $lider = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id,
        ]);
        $lider->jobPositions()->attach(JobPosition::create(['group_id' => $this->group->id, 'slug' => 'lider', 'name' => 'Líder'])->id);
        $regulation->update(['flow_locked' => true]);

        return $lider;
    }

    public function test_admin_marca_aprobado_sin_flujo_sin_avisar_a_aprobadores(): void
    {
        Notification::fake();
        $regulation = $this->regulation('pending_review');
        $lider = $this->liderWithFlow($regulation);
        app(\App\Services\ApprovalFlowService::class)->resubmit($regulation->fresh(), notify: false);
        $this->assertSame(1, $regulation->approvals()->where('status', 'pending')->count());

        $this->actingAs($this->admin)
            ->patch(route('processes.setFlow', $regulation), ['direct_approval' => 1])
            ->assertSessionHas('success');

        $regulation->refresh();
        $this->assertSame('approved', $regulation->approval_status);
        $this->assertSame($this->admin->id, $regulation->direct_approved_by);
        $this->assertSame('bajo', $regulation->impact_level);
        $this->assertSame(0, $regulation->approvals()->whereIn('status', ['pending', 'waiting'])->count());
        Notification::assertNothingSentTo($lider);
    }

    public function test_mantener_flujo_tras_editar_avisa_hasta_que_el_admin_decide(): void
    {
        Notification::fake();
        $regulation = $this->regulation('approved');
        $lider = $this->liderWithFlow($regulation);

        app(\App\Services\ApprovalFlowService::class)->resubmit($regulation->fresh(), notify: false);
        Notification::assertNothingSentTo($lider);

        $this->actingAs($this->admin)->post(route('processes.keepFlow', $regulation))
            ->assertRedirect(route('processes.show', $regulation));
        Notification::assertSentTo($lider, ApprovalRequestedNotification::class);
    }

    public function test_aprobado_sin_flujo_editado_ofrece_mantenerlo_aprobado(): void
    {
        // Cargado ya aprobado (sin nivel de flujo) y luego editado: queda en revisión sin aprobadores.
        $regulation = $this->regulation('pending_review', impact: null);

        $this->actingAs($this->admin)
            ->get(route('processes.show', ['regulation' => $regulation->id, 'review_flow' => 1]))
            ->assertOk()
            ->assertSee('Mantener aprobado')
            ->assertSee('Asignar flujo')
            ->assertDontSee('Mantener flujo actual');

        $this->actingAs($this->admin)
            ->patch(route('processes.setFlow', $regulation), ['direct_approval' => 1]);
        $this->assertSame('approved', $regulation->fresh()->approval_status);
    }

    public function test_la_carga_guarda_el_impacto_para_evaluacion(): void
    {
        Storage::fake('private');
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);

        $this->actingAs($this->admin)->post(route('processes.storeCargar'), [
            'company_id' => $this->company->id, 'process_type_id' => $type->id, 'document_type' => \App\Models\Regulation::DOCUMENT_TYPES[0],
            'evaluation_impact' => 'medio', 'nombre' => 'Manual cargado', 'codigo' => 'ALC-900',
            'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto',
            'file' => UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
            'issued_at' => now()->toDateString(), 'approval_mode' => 'approved', 'valid_until' => now()->addYear()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertSame('medio', Regulation::where('code', 'ALC-900')->value('evaluation_impact'));
    }

    public function test_enviar_a_papelera_cancela_las_aprobaciones_abiertas(): void
    {
        $regulation = $this->regulation('pending_review');
        $approver   = User::factory()->create([
            'group_id' => $this->group->id, 'status' => 'active', 'role_id' => $this->admin->role_id,
        ]);

        $position = JobPosition::create(['group_id' => $this->group->id, 'slug' => 'lider', 'name' => 'Líder']);

        $pending = RegulationApproval::create([
            'regulation_id' => $regulation->id, 'step_number' => 1, 'user_id' => $approver->id,
            'job_position_id' => $position->id, 'requires_all' => true, 'status' => 'pending',
        ]);
        $waiting = RegulationApproval::create([
            'regulation_id' => $regulation->id, 'step_number' => 1, 'user_id' => $this->admin->id,
            'job_position_id' => $position->id, 'requires_all' => true, 'status' => 'waiting',
        ]);

        $this->actingAs($this->admin)
            ->post(route('processes.trash.move'), ['regulation_ids' => [$regulation->id]])
            ->assertSessionHas('success');

        $this->assertSoftDeleted($regulation);
        $this->assertSame('cancelled', $pending->fresh()->status);
        $this->assertSame('cancelled', $waiting->fresh()->status);
    }
}
