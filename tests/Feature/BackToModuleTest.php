<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * En Usuarios y Mi perfil, "Volver a…" regresa al módulo en el que andaba el usuario y que tiene
 * autorizado — antes decía siempre "Volver a Cumplimiento", aunque solo tuviera Procesos.
 */
class BackToModuleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $moduleAccess = 'all'): User
    {
        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);

        return User::factory()->create([
            'group_id' => $group->id, 'scope_level' => 'group', 'status' => 'active', 'module_access' => $moduleAccess,
            'procesos_guide_completed_at' => now(),
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
    }

    public function test_quien_solo_tiene_procesos_regresa_a_procesos(): void
    {
        $this->actingAs($this->admin('procesos'))->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Volver a Procesos')
            ->assertDontSee('Volver a Cumplimiento');
    }

    public function test_regresa_al_ultimo_modulo_visitado(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('processes.index'))->assertOk();
        $this->actingAs($admin)->get(route('users.index'))
            ->assertOk()
            ->assertSee('Volver a Procesos');

        $this->actingAs($admin)->get(route('documents.index'))->assertOk();
        $this->actingAs($admin)->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Volver a Cumplimiento');
    }
}
