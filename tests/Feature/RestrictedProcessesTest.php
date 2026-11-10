<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Carpeta confidencial de Procesos: los procesos de un tipo restringido solo los ven el puesto
 * Consejo y los admins cuyo único acceso es Procesos. Para los demás (Dirección incluida) no existen.
 */
class RestrictedProcessesTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private ProcessType $restrictedType;
    private ProcessType $normalType;
    private User $adminAll;
    private User $adminProcesos;
    private User $consejo;
    private User $direccion;
    private User $lider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id, 'show_in_processes' => true]);

        $this->normalType     = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $this->restrictedType = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Dirección', 'is_active' => true, 'is_restricted' => true]);

        $adminRole     = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $operativeRole = Role::create(['name' => 'Operativo', 'slug' => 'operative']);
        $base = ['group_id' => $this->group->id, 'status' => 'active'];

        $this->adminAll      = User::factory()->create($base + ['name' => 'Laura Todas', 'scope_level' => 'group', 'role_id' => $adminRole->id, 'module_access' => 'all']);
        $this->adminProcesos = User::factory()->create($base + ['name' => 'Pedro Procesos', 'scope_level' => 'group', 'role_id' => $adminRole->id, 'module_access' => 'procesos']);
        $this->consejo       = User::factory()->create($base + ['name' => 'Carmen Consejo', 'scope_level' => 'company', 'company_id' => $this->company->id, 'role_id' => $operativeRole->id]);
        $this->direccion     = User::factory()->create($base + ['name' => 'Diana Dirección', 'scope_level' => 'company', 'company_id' => $this->company->id, 'role_id' => $operativeRole->id]);
        $this->lider         = User::factory()->create($base + ['name' => 'Luis Líder', 'scope_level' => 'company', 'company_id' => $this->company->id, 'role_id' => $operativeRole->id]);

        $position = fn (string $slug, string $name, int $order) => JobPosition::create(['group_id' => $this->group->id, 'slug' => $slug, 'name' => $name, 'sort_order' => $order])->id;
        $this->consejo->jobPositions()->attach($position('consejo', 'Consejo', 5));
        $this->direccion->jobPositions()->attach($position('direccion', 'Dirección', 4));
        $this->lider->jobPositions()->attach($position('lider', 'Líder', 1));
    }

    private function regulation(string $name, ProcessType $type, array $attributes = []): Regulation
    {
        $regulation = Regulation::create($attributes + [
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'is_active' => true, 'approval_status' => 'approved',
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);

        return $regulation;
    }

    public function test_quien_ve_procesos_confidenciales(): void
    {
        $this->assertTrue($this->consejo->canSeeRestrictedProcesses());
        $this->assertTrue($this->adminProcesos->canSeeRestrictedProcesses());
        $this->assertFalse($this->direccion->canSeeRestrictedProcesses());
        $this->assertFalse($this->adminAll->canSeeRestrictedProcesses());
        $this->assertFalse($this->lider->canSeeRestrictedProcesses());
    }

    public function test_para_quien_no_tiene_acceso_el_proceso_confidencial_no_existe(): void
    {
        $this->regulation('Manual de operaciones', $this->normalType);
        $secret  = $this->regulation('Plan de sucesión directiva', $this->restrictedType);
        $version = $secret->currentVersion;

        // Admin con todas las áreas: no lo ve en el listado, ni por URL, ni su versión, ni el tipo.
        $this->actingAs($this->adminAll)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Manual de operaciones')
            ->assertDontSee('Plan de sucesión directiva');
        $this->actingAs($this->adminAll)->get(route('processes.show', $secret))->assertNotFound();
        $this->actingAs($this->adminAll)->get(route('regulation-versions.preview', $version))->assertNotFound();
        $this->actingAs($this->adminAll)->get(route('processes.create'))->assertOk()->assertDontSee('Dirección</option>', false);
        $this->actingAs($this->adminProcesos)->get(route('processes.create'))->assertOk()->assertSee('Dirección</option>', false);

        // Operativo sin el puesto Dirección: tampoco en Mis documentos.
        $this->actingAs($this->lider)->get(route('mi-espacio.documents'))
            ->assertOk()
            ->assertSee('Manual de operaciones')
            ->assertDontSee('Plan de sucesión directiva');
        $this->actingAs($this->lider)->get(route('processes.show', $secret))->assertNotFound();

        // Dirección tampoco: la carpeta es solo de Consejo.
        $this->actingAs($this->direccion)->get(route('processes.show', $secret))->assertNotFound();
    }

    public function test_consejo_y_admin_de_procesos_si_lo_ven(): void
    {
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedType);

        $this->actingAs($this->adminProcesos)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Plan de sucesión directiva')
            ->assertSee('Confidencial');
        $this->actingAs($this->adminProcesos)->get(route('processes.show', $secret))->assertOk();

        $this->actingAs($this->consejo)->get(route('processes.show', $secret))->assertOk();
        $this->actingAs($this->consejo)->get(route('mi-espacio.documents'))
            ->assertSee('Plan de sucesión directiva');
    }

    public function test_sin_acceso_no_se_puede_crear_un_proceso_confidencial(): void
    {
        Storage::fake('private');

        $this->actingAs($this->adminAll)->post(route('processes.storeCargar'), [
            'company_id' => $this->company->id, 'process_type_id' => $this->restrictedType->id,
            'document_type' => Regulation::DOCUMENT_TYPES[0], 'evaluation_impact' => 'medio',
            'nombre' => 'Intento colado', 'codigo' => 'ALC-901', 'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto',
            'file' => UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
            'issued_at' => now()->toDateString(), 'approval_mode' => 'approved', 'valid_until' => now()->addYear()->toDateString(),
        ])->assertForbidden();

        $this->assertFalse(Regulation::withoutGlobalScopes()->where('code', 'ALC-901')->exists());
    }

    public function test_el_flujo_de_un_confidencial_solo_lleva_a_consejo(): void
    {
        Notification::fake();
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedType, [
            'approval_status' => 'pending_review', 'impact_level' => 'alto',
        ]);

        app(ApprovalFlowService::class)->initFlow($secret);

        $this->assertSame([$this->consejo->id], $secret->approvals()->pluck('user_id')->all());
        $this->assertCount(1, ApprovalFlowService::flowFor($secret));

        // Al aprobar Consejo, el proceso queda aprobado: no hay más pasos.
        $this->actingAs($this->consejo)->post(route('processes.approve', $secret))->assertSessionHas('success');
        $this->assertSame('approved', $secret->fresh()->approval_status);
    }

    public function test_en_un_proceso_normal_direccion_y_consejo_comparten_paso_y_basta_uno(): void
    {
        Notification::fake();
        foreach (['jefe' => 2, 'gerente' => 3] as $slug => $order) {
            User::factory()->create([
                'group_id' => $this->group->id, 'status' => 'active', 'scope_level' => 'company',
                'company_id' => $this->company->id, 'role_id' => $this->lider->role_id, 'name' => ucfirst($slug),
            ])->jobPositions()->attach(JobPosition::create(['group_id' => $this->group->id, 'slug' => $slug, 'name' => ucfirst($slug), 'sort_order' => $order])->id);
        }

        $regulation = $this->regulation('Manual de operaciones', $this->normalType, [
            'approval_status' => 'pending_review', 'impact_level' => 'alto',
        ]);
        app(ApprovalFlowService::class)->initFlow($regulation);

        foreach ([1, 2, 3] as $step) {
            $approver = $regulation->approvalStep($step)->firstOrFail()->user;
            $this->actingAs($approver)->post(route('processes.approve', $regulation))->assertSessionHas('success');
        }

        // Paso 4: Dirección y Consejo pendientes a la vez; con que apruebe Dirección se cierra.
        $this->assertEqualsCanonicalizing(
            [$this->direccion->id, $this->consejo->id],
            $regulation->approvalStep(4)->where('status', 'pending')->pluck('user_id')->all()
        );
        $this->actingAs($this->direccion)->post(route('processes.approve', $regulation))->assertSessionHas('success');

        $this->assertSame('approved', $regulation->fresh()->approval_status);
        $this->assertSame('cancelled', $regulation->approvalStep(4)->where('user_id', $this->consejo->id)->value('status'));
    }

    public function test_el_tablero_no_mezcla_numeros_entre_quien_ve_y_quien_no(): void
    {
        $this->regulation('Manual de operaciones', $this->normalType);
        $this->regulation('Plan de sucesión directiva', $this->restrictedType);

        // Primero quien sí ve (llena su caché), luego quien no: no debe heredar el 2.
        $this->actingAs($this->adminProcesos)->get(route('processes.dashboard'))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
        $this->actingAs($this->adminAll)->get(route('processes.dashboard'))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
    }

    public function test_un_confidencial_no_tiene_enlace_publico(): void
    {
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedType);
        $token  = $secret->ensurePublicShareToken();

        auth()->logout();
        $this->get(route('processes.public-view', [$secret, $token]))->assertNotFound();
        $this->actingAs($this->adminProcesos)->get(route('processes.qr', $secret))->assertForbidden();
    }
}
