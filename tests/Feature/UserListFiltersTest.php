<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Filtros de Empresa y Rol en la lista de usuarios del admin. */
class UserListFiltersTest extends TestCase
{
    use RefreshDatabase;

    public function test_filtra_por_empresa_y_por_rol(): void
    {
        $group  = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $alcom  = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $biomex = Company::create(['name' => 'BIOMEX', 'group_id' => $group->id]);
        $adminRole    = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $operative    = Role::create(['name' => 'Operativo', 'slug' => 'operative']);
        $readonly     = Role::create(['name' => 'Solo lectura', 'slug' => 'readonly']);

        $admin = User::factory()->create(['name' => 'Laura Admin', 'group_id' => $group->id, 'scope_level' => 'group',
            'status' => 'active', 'role_id' => $adminRole->id]);
        $base = ['group_id' => $group->id, 'status' => 'active'];
        User::factory()->create($base + ['name' => 'Ana Alcom', 'company_id' => $alcom->id, 'scope_level' => 'company', 'role_id' => $operative->id]);
        User::factory()->create($base + ['name' => 'Beto Biomex', 'company_id' => $biomex->id, 'scope_level' => 'company', 'role_id' => $readonly->id]);
        $multi = User::factory()->create($base + ['name' => 'Carla Varias', 'scope_level' => 'companies', 'role_id' => $operative->id]);
        $multi->companies()->attach([$alcom->id, $biomex->id]);

        $this->actingAs($admin)->get(route('users.index', ['company_id' => $alcom->id]))
            ->assertOk()
            ->assertSee('Ana Alcom')
            ->assertSee('Carla Varias')
            ->assertDontSee('Beto Biomex');

        $this->actingAs($admin)->get(route('users.index', ['role_id' => $readonly->id]))
            ->assertSee('Beto Biomex')
            ->assertDontSee('Ana Alcom')
            ->assertDontSee('Carla Varias');

        $this->actingAs($admin)->get(route('users.index', ['company_id' => $biomex->id, 'role_id' => $operative->id]))
            ->assertSee('Carla Varias')
            ->assertDontSee('Beto Biomex')
            ->assertDontSee('Ana Alcom');
    }
}
