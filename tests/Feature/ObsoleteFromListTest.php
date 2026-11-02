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
 * "Enviar a obsoleto" desde la selección del listado: solo los desactualizados salen de
 * Procedimientos y pasan a Obsoleto; los desactualizados tampoco se pueden mandar a flujo.
 */
class ObsoleteFromListTest extends TestCase
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

    private function regulation(string $name, ?string $status): Regulation
    {
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);

        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'is_active' => true, 'approval_status' => $status,
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);

        return $regulation;
    }

    public function test_solo_los_desactualizados_se_envian_a_obsoleto_y_salen_del_listado(): void
    {
        $outdated = $this->regulation('MANUAL VIEJO', 'outdated');
        $approved = $this->regulation('ALTA DE PROVEEDORES', 'approved');

        $this->actingAs($this->admin)
            ->post(route('processes.obsoleto.move'), ['regulation_ids' => [$outdated->id, $approved->id]])
            ->assertSessionHas('success');

        $this->assertNotNull($outdated->fresh()->obsoleted_at);
        $this->assertNull($approved->fresh()->obsoleted_at);

        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertSee('ALTA DE PROVEEDORES')
            ->assertDontSee('MANUAL VIEJO');

        $this->actingAs($this->admin)->get(route('processes.obsoleto'))
            ->assertOk()
            ->assertSee('Desactualizados enviados a obsoleto')
            ->assertSee('MANUAL VIEJO');

        $this->actingAs($this->admin)->post(route('processes.obsoleto.restore', $outdated));
        $this->assertNull($outdated->fresh()->obsoleted_at);
    }

    public function test_un_desactualizado_no_ofrece_ni_acepta_flujo_de_aprobacion(): void
    {
        $outdated = $this->regulation('MANUAL VIEJO', 'outdated');

        $this->actingAs($this->admin)->get(route('processes.index', ['company_id' => $this->company->id]))
            ->assertSee('MANUAL VIEJO')
            ->assertDontSee('— Asignar flujo —');

        $this->actingAs($this->admin)
            ->patch(route('processes.setFlow', $outdated), ['impact_level' => 'bajo'])
            ->assertSessionHas('error');
        $this->assertNull($outdated->fresh()->impact_level);
    }
}
