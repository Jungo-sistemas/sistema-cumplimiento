<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dentro de una empresa los procesos se ven en lista, con el tipo de proceso como filtro (las
 * carpetas por tipo se quitaron el 26-nov-2026). Solo quedan las carpetas de empresa y DIRECCIÓN.
 */
class ProcessFoldersTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;
    private ProcessType $finanzas;
    private ProcessType $compras;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group    = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company  = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id, 'show_in_processes' => true]);
        $this->admin    = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id'  => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
        $this->finanzas = ProcessType::create(['group_id' => $this->group->id, 'name' => 'FINANZAS Y TESORERÍA', 'sort_order' => 5, 'is_active' => true]);
        $this->compras  = ProcessType::create(['group_id' => $this->group->id, 'name' => 'COMPRAS', 'sort_order' => 8, 'is_active' => true]);
        ProcessType::create(['group_id' => $this->group->id, 'name' => 'Clientes', 'sort_order' => 2, 'is_active' => false]);
    }

    private function regulation(string $name, ?ProcessType $type, string $documentType = 'Procedimiento'): Regulation
    {
        return Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type?->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'document_type' => $documentType, 'is_active' => true,
        ]);
    }

    public function test_la_empresa_muestra_la_lista_con_el_filtro_de_tipo_de_proceso(): void
    {
        $this->regulation('PAGO A PROVEEDORES', $this->finanzas);
        $this->regulation('ALTA DE PROVEEDORES', $this->compras);

        // Ya no hay carpetas: la lista sale directo, con todos los tipos y el filtro arriba.
        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Tipo de proceso')
            ->assertSee('Tipo de documento')
            ->assertSee('05 · FINANZAS Y TESORERÍA')
            ->assertDontSee('Clientes')
            ->assertSee('PAGO A PROVEEDORES')
            ->assertSee('ALTA DE PROVEEDORES');
    }

    public function test_se_filtra_por_tipo_de_proceso_tipo_de_documento_y_se_busca(): void
    {
        $this->regulation('PAGO A PROVEEDORES', $this->finanzas);
        $this->regulation('POLITICA DE GASTOS', $this->finanzas, 'Política');
        $this->regulation('ALTA DE PROVEEDORES', $this->compras);

        $company = ['company_id' => $this->company->id];

        $this->actingAs($this->admin)->get(route('processes.index', $company + ['process_type_id' => $this->finanzas->id]))
            ->assertOk()
            ->assertSee('PAGO A PROVEEDORES')
            ->assertSee('POLITICA DE GASTOS')
            ->assertDontSee('ALTA DE PROVEEDORES');

        $this->actingAs($this->admin)->get(route('processes.index', $company + ['process_type_id' => $this->finanzas->id, 'document_type' => 'Política']))
            ->assertSee('POLITICA DE GASTOS')
            ->assertDontSee('PAGO A PROVEEDORES');

        // El buscador busca en todos los tipos si no se elige uno.
        $this->actingAs($this->admin)->get(route('processes.index', $company + ['q' => 'proveedores']))
            ->assertSee('PAGO A PROVEEDORES')
            ->assertSee('ALTA DE PROVEEDORES')
            ->assertDontSee('POLITICA DE GASTOS');
    }

    public function test_el_comando_lista_los_que_no_quedaron_en_ninguna_carpeta(): void
    {
        $this->regulation('PAGO A PROVEEDORES', $this->finanzas);

        $this->artisan('procesos:revisar-carpetas')
            ->expectsOutput('Todos están en una carpeta.')
            ->assertSuccessful();

        $this->regulation('ATENCION A CLIENTES', ProcessType::where('name', 'Clientes')->first());
        $hidden = Company::create(['name' => 'TI', 'group_id' => $this->group->id, 'show_in_processes' => false]);
        Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $hidden->id, 'process_type_id' => $this->compras->id,
            'code' => 'TI-001', 'name' => 'RESPALDOS', 'document_type' => 'Procedimiento', 'is_active' => true,
        ]);

        $this->artisan('procesos:revisar-carpetas')
            ->expectsOutput('2 no están en ninguna carpeta:')
            ->assertFailed();
    }
}
