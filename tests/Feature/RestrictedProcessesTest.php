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
 * Carpeta confidencial de Procesos: la empresa DIRECCIÓN (is_restricted), al nivel de las demás
 * empresas, solo la ven el puesto Consejo y los admins cuyo único acceso es Procesos. Para los
 * demás (Dirección incluida) ni la carpeta ni sus procesos existen. Dentro tiene las mismas
 * carpetas por tipo que cualquier empresa.
 */
class RestrictedProcessesTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private Company $restrictedCompany;
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
        $this->restrictedCompany = Company::create(['name' => 'DIRECCIÓN', 'group_id' => $this->group->id, 'show_in_processes' => true, 'is_restricted' => true]);

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

    private function regulation(string $name, Company $company, array $attributes = []): Regulation
    {
        $regulation = Regulation::create($attributes + [
            'group_id' => $this->group->id, 'company_id' => $company->id, 'process_type_id' => $this->normalType->id,
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
        $this->regulation('Manual de operaciones', $this->company);
        $secret  = $this->regulation('Plan de sucesión directiva', $this->restrictedCompany);
        $version = $secret->currentVersion;

        // Admin con todas las áreas: no ve la carpeta DIRECCIÓN entre las empresas, ni entra a
        // ella, ni ve sus procesos en búsquedas, por URL o su versión.
        $this->actingAs($this->adminAll)->get(route('processes.index'))
            ->assertOk()
            ->assertSee('ALCOM')
            ->assertDontSee('DIRECCIÓN');
        $this->actingAs($this->adminAll)->get(route('processes.index', ['company_id' => $this->restrictedCompany->id, 'process_type_id' => $this->normalType->id]))
            ->assertDontSee('Plan de sucesión directiva');
        $this->actingAs($this->adminAll)->get(route('processes.index', ['q' => 'Plan']))
            ->assertOk()
            ->assertDontSee('Plan de sucesión directiva');
        $this->actingAs($this->adminAll)->get(route('processes.show', $secret))->assertNotFound();
        $this->actingAs($this->adminAll)->get(route('regulation-versions.preview', $version))->assertNotFound();
        $this->actingAs($this->adminAll)->get(route('processes.create'))->assertOk()
            ->assertViewHas('companies', fn ($companies) => ! $companies->contains('id', $this->restrictedCompany->id));
        $this->actingAs($this->adminProcesos)->get(route('processes.create'))->assertOk()
            ->assertViewHas('companies', fn ($companies) => $companies->contains('id', $this->restrictedCompany->id));

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
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedCompany);

        // La carpeta DIRECCIÓN sale junto a las empresas y dentro tiene las carpetas por tipo.
        $this->actingAs($this->adminProcesos)->get(route('processes.index'))
            ->assertOk()
            ->assertSeeInOrder(['DIRECCIÓN', 'Confidencial', 'ALCOM']);
        $this->actingAs($this->adminProcesos)->get(route('processes.index', ['company_id' => $this->restrictedCompany->id]))
            ->assertOk()
            ->assertSee('Confidencial')
            ->assertSee($this->normalType->name)
            ->assertSee('1 procedimiento');
        $this->actingAs($this->adminProcesos)->get(route('processes.index', ['company_id' => $this->restrictedCompany->id, 'process_type_id' => $this->normalType->id]))
            ->assertOk()
            ->assertSee('Plan de sucesión directiva');
        $this->actingAs($this->adminProcesos)->get(route('processes.show', $secret))->assertOk();

        $this->actingAs($this->consejo)->get(route('processes.show', $secret))->assertOk();
        $this->actingAs($this->consejo)->get(route('mi-espacio.documents'))
            ->assertSee('Plan de sucesión directiva');
    }

    public function test_sin_acceso_no_se_puede_crear_un_proceso_confidencial(): void
    {
        Storage::fake('private');

        $this->actingAs($this->adminAll)->post(route('processes.storeCargar'), [
            'company_id' => $this->restrictedCompany->id, 'process_type_id' => $this->normalType->id,
            'document_type' => Regulation::DOCUMENT_TYPES[0], 'evaluation_impact' => 'medio',
            'nombre' => 'Intento colado', 'codigo' => 'ALC-901', 'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto',
            'file' => UploadedFile::fake()->create('manual.pdf', 50, 'application/pdf'),
            'issued_at' => now()->toDateString(), 'approval_mode' => 'approved', 'valid_until' => now()->addYear()->toDateString(),
        ])->assertNotFound(); // para él la carpeta DIRECCIÓN no existe

        $this->assertFalse(Regulation::withoutGlobalScopes()->where('code', 'ALC-901')->exists());
    }

    public function test_el_flujo_de_un_confidencial_solo_lleva_a_consejo(): void
    {
        Notification::fake();
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedCompany, [
            'approval_status' => 'pending_review', 'impact_level' => 'alto',
        ]);

        app(ApprovalFlowService::class)->initFlow($secret);

        $this->assertSame([$this->consejo->id], $secret->approvals()->pluck('user_id')->all());
        $this->assertCount(1, ApprovalFlowService::flowFor($secret));

        // Al aprobar Consejo, el proceso queda aprobado: no hay más pasos.
        $this->actingAs($this->consejo)->post(route('processes.approve', $secret))->assertSessionHas('success');
        $this->assertSame('approved', $secret->fresh()->approval_status);
    }

    public function test_en_un_proceso_normal_direccion_y_consejo_comparten_paso_y_aprueban_en_orden(): void
    {
        Notification::fake();
        foreach (['jefe' => 2, 'gerente' => 3] as $slug => $order) {
            User::factory()->create([
                'group_id' => $this->group->id, 'status' => 'active', 'scope_level' => 'company',
                'company_id' => $this->company->id, 'role_id' => $this->lider->role_id, 'name' => ucfirst($slug),
            ])->jobPositions()->attach(JobPosition::create(['group_id' => $this->group->id, 'slug' => $slug, 'name' => ucfirst($slug), 'sort_order' => $order])->id);
        }

        $regulation = $this->regulation('Manual de operaciones', $this->company, [
            'approval_status' => 'pending_review', 'impact_level' => 'alto',
        ]);
        app(ApprovalFlowService::class)->initFlow($regulation);

        foreach ([1, 2, 3] as $step) {
            $approver = $regulation->approvalStep($step)->firstOrFail()->user;
            $this->actingAs($approver)->post(route('processes.approve', $regulation))->assertSessionHas('success');
        }

        // Paso 4: primero Dirección y luego Consejo; al aprobar Dirección, a Consejo ya no se le cancela.
        $this->assertSame([$this->direccion->id], $regulation->approvalStep(4)->where('status', 'pending')->pluck('user_id')->all());
        $this->assertSame('waiting', $regulation->approvalStep(4)->where('user_id', $this->consejo->id)->value('status'));
        $this->actingAs($this->direccion)->post(route('processes.approve', $regulation))->assertSessionHas('success');

        $this->assertNotSame('approved', $regulation->fresh()->approval_status);
        $this->assertSame('pending', $regulation->approvalStep(4)->where('user_id', $this->consejo->id)->value('status'));
        $this->actingAs($this->consejo)->post(route('processes.approve', $regulation))->assertSessionHas('success');

        $this->assertSame('approved', $regulation->fresh()->approval_status);
    }

    public function test_el_tablero_no_mezcla_numeros_entre_quien_ve_y_quien_no(): void
    {
        $this->regulation('Manual de operaciones', $this->company);
        $this->regulation('Plan de sucesión directiva', $this->restrictedCompany);

        // Primero quien sí ve (llena su caché), luego quien no: no debe heredar el 2.
        $this->actingAs($this->adminProcesos)->get(route('processes.dashboard'))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 2);
        $this->actingAs($this->adminAll)->get(route('processes.dashboard'))
            ->assertViewHas('stats', fn ($stats) => $stats['total'] === 1);
    }

    public function test_el_tablero_lista_los_pendientes_de_cada_persona_con_sus_dias(): void
    {
        Notification::fake();
        $manual = $this->regulation('Manual de operaciones', $this->company, ['approval_status' => 'pending_review', 'impact_level' => 'bajo']);
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedCompany, ['approval_status' => 'pending_review', 'impact_level' => 'bajo']);
        app(ApprovalFlowService::class)->initFlow($manual);
        app(ApprovalFlowService::class)->initFlow($secret);
        $manual->approvals()->update(['created_at' => now()->subDays(5)]);

        $this->actingAs($this->adminAll)->get(route('processes.dashboard'))
            ->assertViewHas('chartData', function ($data) use ($manual) {
                $lider = collect($data['pendingByUser'])->firstWhere('name', 'Luis Líder');

                return $lider['count'] === 1
                    && $lider['items'][0]['code'] === $manual->code
                    && $lider['items'][0]['days'] === 5
                    && collect($data['pendingByUser'])->pluck('items')->flatten(1)->pluck('name')->doesntContain('Plan de sucesión directiva');
            })
            ->assertSee('Pendientes de Luis Líder')
            ->assertSee('5 días');
    }

    public function test_un_confidencial_no_tiene_enlace_publico(): void
    {
        $secret = $this->regulation('Plan de sucesión directiva', $this->restrictedCompany);
        $token  = $secret->ensurePublicShareToken();

        auth()->logout();
        $this->get(route('processes.public-view', [$secret, $token]))->assertNotFound();
        $this->actingAs($this->adminProcesos)->get(route('processes.qr', $secret))->assertForbidden();
    }

    public function test_consejo_con_una_sola_empresa_tambien_llega_a_la_carpeta_direccion(): void
    {
        $this->regulation('Manual de operaciones', $this->company);
        $this->regulation('Plan de sucesión directiva', $this->restrictedCompany);
        $otra = Company::create(['name' => 'BIOMEX', 'group_id' => $this->group->id, 'show_in_processes' => true]);

        // Ve la pantalla de empresas con solo su empresa y DIRECCIÓN.
        $this->actingAs($this->consejo)->get(route('processes.index'))
            ->assertOk()
            ->assertViewHas('cardView', true)
            ->assertSeeInOrder(['DIRECCIÓN', 'ALCOM'])
            ->assertDontSee('BIOMEX');
        $this->actingAs($this->consejo)->get(route('processes.index', ['company_id' => $this->restrictedCompany->id, 'process_type_id' => $this->normalType->id]))
            ->assertOk()
            ->assertSee('Plan de sucesión directiva');

        // Pero no otras empresas.
        $this->actingAs($this->consejo)->get(route('processes.index', ['company_id' => $otra->id]))->assertForbidden();

        // Un operativo normal sigue entrando directo a su empresa, sin DIRECCIÓN.
        $this->actingAs($this->lider)->get(route('processes.index'))
            ->assertOk()
            ->assertViewHas('folderView', true)
            ->assertDontSee('DIRECCIÓN');
        $this->actingAs($this->lider)->get(route('processes.index', ['company_id' => $this->restrictedCompany->id, 'process_type_id' => $this->normalType->id]))
            ->assertDontSee('Plan de sucesión directiva');
    }
}
