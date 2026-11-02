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
 * "Cargar anexo" desde la pestaña Anexos (sin la casilla "Es un anexo" en el formulario) y la
 * síntesis de cambios visible en el detalle del flujo.
 */
class ProcessUploadAndFlowDetailTest extends TestCase
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

    public function test_el_boton_de_carga_cambia_segun_la_pestana(): void
    {
        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertOk()->assertSee('Cargar proceso')->assertDontSee('Cargar anexo');

        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id, 'anexos' => 1]))
            ->assertOk()->assertSee('Cargar anexo')
            ->assertSee(route('processes.cargar', ['company_id' => $this->company->id, 'anexo' => 1]));
    }

    public function test_el_formulario_de_carga_ya_no_pregunta_si_es_anexo(): void
    {
        $this->actingAs($this->admin)->get(route('processes.cargar', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Cargar proceso existente')
            ->assertDontSee('Es un anexo')
            ->assertDontSee('name="is_annex"', false);

        $this->actingAs($this->admin)->get(route('processes.cargar', ['company_id' => $this->company->id, 'anexo' => 1]))
            ->assertOk()
            ->assertSee('Cargar anexo existente')
            ->assertSee('Guardar anexo')
            ->assertDontSee('Es un anexo')
            ->assertSee('<input type="hidden" name="is_annex" value="1">', false);
    }

    public function test_el_detalle_del_flujo_muestra_la_sintesis_de_cambios(): void
    {
        $type = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-001', 'name' => 'ALTA DE PROVEEDORES', 'is_active' => true,
            'impact_level' => 'bajo', 'approval_status' => 'pending_review',
            'change_summary_path' => 'regulations/test/sintesis.pdf',
            'change_summary_original_name' => 'sintesis-cambios.pdf',
            'change_summary_uploaded_by' => $this->admin->id,
            'change_summary_uploaded_at' => now(),
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'body_html' => '<p>Contenido</p>', 'issued_at' => now()->toDateString(),
        ]);

        $this->actingAs($this->admin)->get(route('processes.flow', $regulation))
            ->assertOk()
            ->assertSee('Síntesis de cambios')
            ->assertSee('sintesis-cambios.pdf')
            ->assertSee(route('processes.changeSummary.show', $regulation), false);
    }
}
