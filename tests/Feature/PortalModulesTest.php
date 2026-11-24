<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Support\PortalModules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Módulos de Jungo Cumple en el menú: Fiscal, Regulatorio, Objetivos, Procesos y Auditorías, Flujos y Cursos. */
class PortalModulesTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $this->admin = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Administrador'])->id,
        ]);
    }

    private function operative(string $moduleAccess): User
    {
        return User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'module_access' => $moduleAccess,
            'role_id' => Role::firstOrCreate(['slug' => 'operative'], ['name' => 'Operativo'])->id,
        ]);
    }

    public function test_el_selector_muestra_los_seis_modulos_en_orden(): void
    {
        $this->actingAs($this->admin)->get(route('documents.index'))
            ->assertOk()
            ->assertSeeInOrder(['Cumplimiento Fiscal', 'Cumplimiento Regulatorio', 'Objetivos', 'Procesos y Auditorías', 'Flujos', 'Cursos']);
    }

    public function test_fiscal_tiene_documentos_generales_y_los_espacios_del_expediente_y_el_agente(): void
    {
        $this->actingAs($this->admin)->get(route('documents.index'))
            ->assertOk()
            ->assertSeeInOrder(['Documentos generales', 'Expediente Fiscal y Legal', 'Agente InfoSAT y MP12'])
            ->assertDontSee('Fase 0')
            ->assertDontSee('Fase 1')
            ->assertSee(route('coming-soon', 'expediente-fiscal'), false)
            ->assertDontSee('Solicitud de Inversiones');
    }

    public function test_flujos_tiene_inversiones_compras_y_tarjeta_clara(): void
    {
        $this->actingAs($this->admin)->get(route('coming-soon', 'compras'))
            ->assertOk()
            ->assertSee('Esta sección está en construcción')
            ->assertSee(route('investment-requests.index'), false)
            ->assertSee('Administración de Gastos de Tarjeta Clara')
            ->assertDontSee('Documentos generales');
    }

    public function test_objetivos_y_regulatorio_tienen_sus_opciones(): void
    {
        $this->actingAs($this->admin)->get(route('coming-soon', 'hoshin'))
            ->assertOk()->assertSeeInOrder(['Planeación Estratégica', 'Cascadeo', 'Hoshin']);

        $this->actingAs($this->admin)->get(route('coming-soon', 'marcas'))
            ->assertOk()->assertSeeInOrder(['Tablero', 'Activos y Actividades', 'Marcas', 'Terrenos']);
    }

    public function test_todas_las_secciones_pendientes_cargan(): void
    {
        foreach (array_keys(PortalModules::COMING_SOON) as $section) {
            $this->actingAs($this->admin)->get(route('coming-soon', $section))->assertOk();
        }
        $this->actingAs($this->admin)->get('/proximamente/no-existe')->assertNotFound();
    }

    public function test_quien_solo_tiene_procesos_no_ve_ni_entra_a_lo_demas(): void
    {
        $procesos = $this->operative('procesos');

        $this->actingAs($procesos)->get(route('coming-soon', 'cursos'))->assertRedirect();
        $this->actingAs($procesos)->get(route('gestion.home'))
            ->assertOk()
            ->assertSee('Procesos y Auditorías')
            ->assertDontSee('Cumplimiento Fiscal')
            ->assertDontSee('Objetivos');
    }

    public function test_quien_solo_tiene_cumplimiento_ve_todo_menos_la_gestion_de_procesos(): void
    {
        $cumplimiento = $this->operative('cumplimiento');

        $this->actingAs($cumplimiento)->get(route('coming-soon', 'cursos'))
            ->assertOk()
            ->assertSee('Cumplimiento Fiscal')->assertSee('Objetivos')->assertSee('Flujos');
    }
}
