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
 * Vista de auditado (Mi espacio): quien no tiene Gestión (solo lectura) solo tiene esta vista;
 * admins, auditores y operativos cambian entre Gestión y Mi espacio desde el encabezado.
 */
class AuditeeViewTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;
    private User $operative;
    private User $readonly;

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
        $this->readonly = User::factory()->create([
            'name' => 'Rosa Lectura', 'group_id' => $this->group->id, 'company_id' => $this->company->id,
            'scope_level' => 'company', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Solo lectura', 'slug' => 'readonly'])->id,
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

    public function test_solo_lectura_entra_directo_a_su_vista_de_auditado(): void
    {
        $this->actingAs($this->readonly)->get(route('dashboard'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($this->readonly)->get(route('gestion.home'))->assertRedirect(route('mi-espacio.home'));

        // Sin el cambio de vista: solo lectura no tiene Gestión.
        $this->actingAs($this->readonly)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSee('Hola, Rosa.')
            ->assertDontSee(route('view-mode.switch', 'gestion'), false);
        $this->actingAs($this->readonly)->get(route('view-mode.switch', 'gestion'))
            ->assertRedirect(route('mi-espacio.home'));
    }

    public function test_el_operativo_tiene_gestion_de_procesos_sin_indicadores_ni_auditorias(): void
    {
        $this->actingAs($this->operative)->get(route('view-mode.switch', 'gestion'))
            ->assertRedirect(route('gestion.home'));

        $this->actingAs($this->operative)->get(route('gestion.home'))
            ->assertOk()
            ->assertSee(route('processes.index'), false)
            ->assertSee(route('my-approvals.index'), false)
            ->assertSee(route('view-mode.switch', 'auditado'), false)
            ->assertDontSee('Indicadores de procesos')
            ->assertDontSee('Hallazgos');

        // Indicadores de procesos es solo de admins.
        $this->actingAs($this->operative)->get(route('processes.dashboard'))->assertRedirect(route('gestion.home'));
        $this->actingAs($this->operative)->get(route('auditorias.indicators'))->assertForbidden();
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
            ->assertSee(route('view-mode.switch', 'gestion'), false)
            ->assertSee('Mis actividades');

        // Consultar un proceso desde Mi espacio conserva el menú de auditado...
        $regulation = $this->regulation('Control de evidencias operativas');
        $this->actingAs($this->admin)->get(route('processes.show', $regulation))
            ->assertOk()
            ->assertSee('Mis documentos');

        // ...y "Gestión" lleva al Inicio de Procesos y Auditorías, con los dos lados en el menú.
        $this->actingAs($this->admin)->get(route('view-mode.switch', 'gestion'))
            ->assertRedirect(route('gestion.home'));
        $this->actingAs($this->admin)->get(route('gestion.home'))
            ->assertOk()
            ->assertSee(route('view-mode.switch', 'auditado'), false)
            ->assertSee('Indicadores de procesos')
            ->assertSee('Indicadores de auditorías')
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
            ->assertRedirect(route('gestion.home'));
        $this->assertSame('gestion', session('view_mode'));

        // Quien solo es auditado no ve el selector de módulos.
        $this->actingAs($this->readonly)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertDontSee(route('view-mode.switch', ['mode' => 'gestion', 'modulo' => 'procesos']), false);
    }

    public function test_el_cambio_de_vista_sale_en_procesos_y_no_en_cumplimiento(): void
    {
        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Procedimientos')
            ->assertSee(route('view-mode.switch', 'auditado'), false)
            ->assertDontSee('Mis actividades');

        $this->actingAs($this->admin)->get(route('documents.index'))
            ->assertOk()
            ->assertDontSee(route('view-mode.switch', 'auditado'), false);
    }

    public function test_mis_documentos_incluye_los_de_otra_empresa_donde_lo_mencionan(): void
    {
        $otherCompany = Company::create(['name' => 'BIOMEX', 'group_id' => $this->group->id]);
        $mentioned = $this->regulation('Recepción de producto', ['company_id' => $otherCompany->id]);
        $notMentioned = $this->regulation('Proceso ajeno', ['company_id' => $otherCompany->id]);

        $mention = '<p>Revisa <span data-type="person-mention" class="mention-tag mention-person" data-id="' . $this->operative->id . '" data-label="Ana Torres">@Ana Torres</span></p>';
        $mentioned->currentVersion()->update(['body_html' => $mention]);
        // Una mención #documento con el mismo número no cuenta como mención a la persona.
        $notMentioned->currentVersion()->update(['body_html' => '<p><a data-type="documento" data-id="' . $this->operative->id . '">#X</a></p>']);

        $this->actingAs($this->operative)->get(route('mi-espacio.documents'))
            ->assertOk()
            ->assertSee('Recepción de producto')
            ->assertSee('Te mencionan')
            ->assertSee('BIOMEX')
            ->assertDontSee('Proceso ajeno');

        // Lo puede abrir aunque no sea de su empresa.
        $this->actingAs($this->operative)->get(route('processes.show', $mentioned))->assertOk();
        $this->actingAs($this->operative)->get(route('processes.show', $notMentioned))->assertForbidden();
    }
}
