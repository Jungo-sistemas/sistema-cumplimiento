<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Jefe de Auditoría (JD-925): rol propio para quien planea el programa anual de auditorías y
 * asigna a los auditores. Igual que el auditor, solo lo da un admin con Procesos y siempre queda
 * "Solo Procesos".
 */
class JefeAuditoriaTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Role $adminRole;
    private Role $jefeRole;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->group     = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->adminRole = Role::create(['name' => 'Administrador', 'slug' => 'admin']);

        // No se crea aquí: lo trae la migración, igual que en producción.
        $this->jefeRole = Role::where('slug', 'jefe_auditoria')->sole();
    }

    private function admin(string $access): User
    {
        return User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => $this->adminRole->id, 'module_access' => $access,
        ]);
    }

    public function test_el_rol_llega_con_las_migraciones(): void
    {
        $this->assertSame('Jefe de Auditoría', $this->jefeRole->name);
    }

    public function test_un_admin_de_procesos_crea_y_edita_jefes_de_auditoria_solo_de_procesos(): void
    {
        $admin = $this->admin('all');

        // El formulario ofrece el rol y explica para qué es.
        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => $roles->contains('slug', 'jefe_auditoria'))
            ->assertSee('Planea el programa anual');

        // Aunque llegue "Ambos módulos", queda en Procesos.
        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Jefa Nueva', 'email' => 'jefa@test.mx', 'role_id' => $this->jefeRole->id, 'module_access' => 'all',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('procesos', User::where('email', 'jefa@test.mx')->sole()->module_access);

        // Lo mismo al cambiarle el rol a alguien que ya existe.
        $operativo = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'module_access' => 'all',
            'role_id' => Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id,
        ]);

        $this->actingAs($admin)->patch(route('users.update', $operativo), [
            'role_id' => $this->jefeRole->id, 'module_access' => 'all',
        ])->assertRedirect(route('users.index'));

        $this->assertSame('procesos', $operativo->fresh()->module_access);
        $this->assertSame('jefe_auditoria', $operativo->fresh()->role->slug);
    }

    public function test_un_admin_de_cumplimiento_no_crea_jefes_de_auditoria(): void
    {
        $admin = $this->admin('cumplimiento');

        $this->actingAs($admin)->get(route('users.create'))
            ->assertOk()
            ->assertViewHas('roles', fn ($roles) => ! $roles->contains('slug', 'jefe_auditoria'))
            ->assertDontSee('Planea el programa anual');

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Jefa Nueva', 'email' => 'jefa@test.mx', 'role_id' => $this->jefeRole->id,
        ])->assertForbidden();
    }

    public function test_el_jefe_se_suma_a_los_roles_de_procesos_sin_quitar_al_auditor(): void
    {
        $auditorRole = Role::create(['name' => 'Auditor', 'slug' => 'auditor']);
        $admin = $this->admin('all');

        $this->actingAs($admin)->get(route('users.create'))
            ->assertViewHas('roles', fn ($roles) => $roles->contains('slug', 'auditor') && $roles->contains('slug', 'jefe_auditoria'));

        $this->actingAs($admin)->post(route('users.store'), [
            'name' => 'Auditor Nuevo', 'email' => 'auditor@test.mx', 'role_id' => $auditorRole->id,
        ])->assertRedirect(route('users.index'));
    }
}
