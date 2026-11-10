<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vista de auditado (Mi espacio): los roles que no son admin solo tienen esta vista; los admins
 * cambian entre Gestión y Auditado desde el encabezado.
 */
class AuditeeViewTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;
    private User $operative;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);

        $this->admin = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
        $this->operative = User::factory()->create([
            'name' => 'Ana Torres', 'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'scope_level' => 'company', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id,
        ]);
    }

    private function regulation(string $name, array $attributes = [], ?string $validUntil = '+1 year'): Regulation
    {
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);

        $regulation = Regulation::create($attributes + [
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'is_active' => true, 'approval_status' => 'approved',
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(),
            'valid_until' => $validUntil ? now()->modify($validUntil)->toDateString() : null,
        ]);

        return $regulation;
    }

    public function test_el_operativo_entra_directo_a_su_vista_de_auditado(): void
    {
        $this->actingAs($this->operative)->get(route('dashboard'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($this->operative)->get(route('processes.dashboard'))->assertRedirect(route('mi-espacio.home'));

        // Sin el cambio de vista: el operativo no tiene Gestión.
        $this->actingAs($this->operative)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSee('Hola, Ana.')
            ->assertDontSee('Vista de auditado');
        $this->actingAs($this->operative)->get(route('view-mode.switch', 'gestion'))
            ->assertRedirect(route('mi-espacio.home'));
    }

    public function test_mis_documentos_lista_solo_los_procesos_publicados_de_sus_empresas(): void
    {
        $this->regulation('Control de maniobras y seguridad');
        $this->regulation('Gestión de proveedores', [], '-1 day');
        $this->regulation('Borrador sin aprobar', ['approval_status' => 'pending_review'], null);
        $this->regulation('Formato anexo', ['is_annex' => true]);
        $otherCompany = Company::create(['name' => 'BIOMEX', 'group_id' => $this->group->id]);
        $this->regulation('Proceso de otra empresa', ['company_id' => $otherCompany->id]);

        $this->actingAs($this->operative)->get(route('mi-espacio.documents'))
            ->assertOk()
            ->assertSee('Control de maniobras y seguridad')
            ->assertSee('Liberado')
            ->assertSee('Gestión de proveedores')
            ->assertSee('Obsoleto')
            ->assertDontSee('Borrador sin aprobar')
            ->assertDontSee('Formato anexo')
            ->assertDontSee('Proceso de otra empresa');

        $this->actingAs($this->operative)->get(route('mi-espacio.documents', ['q' => 'maniobras']))
            ->assertSee('Control de maniobras y seguridad')
            ->assertDontSee('Gestión de proveedores');

        // La tarjeta de Inicio cuenta solo los liberados.
        $this->actingAs($this->operative)->get(route('mi-espacio.home'))
            ->assertSeeInOrder(['Mis documentos', '1', 'Procesos relacionados con tu trabajo.']);
    }

    public function test_todas_las_pantallas_de_mi_espacio_cargan(): void
    {
        foreach ([
            route('mi-espacio.requests'), route('mi-espacio.documents'), route('mi-espacio.help'),
            route('mi-espacio.audits'), route('mi-espacio.audits', ['tab' => 'historial']),
            route('mi-espacio.activities'), route('mi-espacio.activities', ['tab' => 'concluidas']),
        ] as $url) {
            $this->actingAs($this->operative)->get($url)->assertOk();
        }
    }

    public function test_el_admin_cambia_entre_gestion_y_auditado(): void
    {
        $this->actingAs($this->admin)->get(route('view-mode.switch', 'auditado'))
            ->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($this->admin)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSee('Vista de auditado')
            ->assertSee('Mis actividades');

        // Consultar un proceso desde Mi espacio conserva el menú de auditado...
        $regulation = $this->regulation('Control de evidencias operativas');
        $this->actingAs($this->admin)->get(route('processes.show', $regulation))
            ->assertOk()
            ->assertSee('Mis documentos');

        // ...y "Gestión" lleva al lado del auditor, donde sigue el cambio de vista.
        $this->actingAs($this->admin)->get(route('view-mode.switch', 'gestion'))
            ->assertRedirect(route('auditorias.home'));
        $this->actingAs($this->admin)->get(route('auditorias.home'))
            ->assertOk()
            ->assertSee('Vista de auditado')
            ->assertSee('Hallazgos');
    }

    public function test_desde_auditado_el_admin_elige_modulo_y_las_aprobaciones_quedan_en_procesos(): void
    {
        $this->actingAs($this->admin)->get(route('view-mode.switch', 'auditado'));

        $this->actingAs($this->admin)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSee(route('view-mode.switch', ['mode' => 'gestion', 'modulo' => 'procesos']), false)
            ->assertSee(route('view-mode.switch', ['mode' => 'gestion', 'modulo' => 'cumplimiento']), false)
            ->assertDontSee('Mis aprobaciones');

        $this->actingAs($this->admin)->get(route('view-mode.switch', ['mode' => 'gestion', 'modulo' => 'procesos']))
            ->assertRedirect(route('processes.dashboard'));
        $this->assertSame('gestion', session('view_mode'));

        // Quien solo es auditado no ve el selector de módulos.
        $this->actingAs($this->operative)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertDontSee(route('view-mode.switch', ['mode' => 'gestion', 'modulo' => 'procesos']), false);
    }

    public function test_el_cambio_de_vista_no_aparece_en_procesos(): void
    {
        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Procedimientos')
            ->assertDontSee('Vista de auditado')
            ->assertDontSee('Mis actividades');
    }
}
