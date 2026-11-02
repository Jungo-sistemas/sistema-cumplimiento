<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Cambio de nombre desde la pantalla de usuarios: el admin cambia el de operativos y de solo
 * lectura, y el suyo propio (solo el nombre), pero no el de otros admins.
 */
class UserNameEditTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;
    private Role $operative;
    private Role $readonly;
    private Role $adminRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group     = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company   = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $this->adminRole = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $this->operative = Role::create(['name' => 'Operativo', 'slug' => 'operative']);
        $this->readonly  = Role::create(['name' => 'Solo lectura', 'slug' => 'readonly']);

        $this->admin = $this->makeUser('Laura Admin', $this->adminRole, 'group');
    }

    private function makeUser(string $name, Role $role, string $scope = 'company'): User
    {
        return User::factory()->create([
            'name'        => $name,
            'group_id'    => $this->group->id,
            'company_id'  => $scope === 'company' ? $this->company->id : null,
            'scope_level' => $scope,
            'status'      => 'active',
            'role_id'     => $role->id,
        ]);
    }

    private function updatePayload(User $user, string $name): array
    {
        return [
            'name'          => $name,
            'role_id'       => $user->role_id,
            'company_id'    => [$this->company->id],
            'module_access' => 'all',
        ];
    }

    public function test_admin_cambia_el_nombre_de_operativos_y_solo_lectura(): void
    {
        $op = $this->makeUser('Ana Op', $this->operative);
        $ro = $this->makeUser('Beto Lectura', $this->readonly);

        $this->actingAs($this->admin)->patch(route('users.update', $op), $this->updatePayload($op, 'Ana Operativa'))
            ->assertRedirect(route('users.index'));
        $this->actingAs($this->admin)->patch(route('users.update', $ro), $this->updatePayload($ro, 'Alberto Lectura'))
            ->assertRedirect(route('users.index'));

        $this->assertSame('Ana Operativa', $op->fresh()->name);
        $this->assertSame('Alberto Lectura', $ro->fresh()->name);
    }

    public function test_admin_no_cambia_el_nombre_de_otro_admin(): void
    {
        $other = $this->makeUser('Otro Admin', $this->adminRole, 'group');

        $this->actingAs($this->admin)->patch(route('users.update', $other), $this->updatePayload($other, 'Cambiado'));

        $this->assertSame('Otro Admin', $other->fresh()->name);
    }

    public function test_admin_cambia_solo_su_propio_nombre(): void
    {
        $this->actingAs($this->admin)
            ->patch(route('users.update-own-name'), ['name' => 'Laura Nueva', 'role_id' => $this->operative->id])
            ->assertRedirect(route('users.index'));

        $admin = $this->admin->fresh();
        $this->assertSame('Laura Nueva', $admin->name);
        $this->assertSame($this->adminRole->id, $admin->role_id);
    }

    public function test_quien_no_es_admin_no_puede_usar_el_cambio_de_nombre_propio(): void
    {
        $op = $this->makeUser('Ana Op', $this->operative);

        $this->actingAs($op)->patch(route('users.update-own-name'), ['name' => 'Otra'])->assertForbidden();
        $this->assertSame('Ana Op', $op->fresh()->name);
    }
}
