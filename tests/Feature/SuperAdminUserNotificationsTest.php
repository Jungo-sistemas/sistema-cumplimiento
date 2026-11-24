<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Usuarios del superadmin: el auditor solo entra a Procesos y la lista de Notificaciones incluye Auditorías. */
class SuperAdminUserNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private User $superadmin;
    private Group $group;
    private Role $auditor;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $this->group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $this->auditor = Role::firstOrCreate(['slug' => 'auditor'], ['name' => 'Auditor']);

        $this->superadmin = User::factory()->create([
            'scope_level' => 'global', 'status' => 'active',
            'role_id' => Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Superadministrador'])->id,
        ]);
    }

    public function test_la_pantalla_muestra_auditorias_dentro_de_notificaciones(): void
    {
        $this->actingAs($this->superadmin)->get(route('superadmin.users'))
            ->assertOk()
            ->assertSee('Hallazgos críticos y plazos vencidos de los hallazgos del grupo.')
            ->assertDontSee('Recibir los avisos de auditorías');
    }

    public function test_un_auditor_nuevo_queda_solo_en_procesos_aunque_se_mande_ambos(): void
    {
        $this->actingAs($this->superadmin)->post(route('superadmin.users.store'), [
            'name' => 'Ana Auditora', 'email' => 'ana@example.com', 'role_id' => $this->auditor->id,
            'group_id' => $this->group->id, 'module_access' => 'all',
            'notification_modules' => 'procesos', 'receives_audit_alerts' => '1',
        ])->assertRedirect(route('superadmin.users'));

        $user = User::where('email', 'ana@example.com')->sole();
        $this->assertSame('procesos', $user->module_access);
        $this->assertFalse($user->canAccessModule('cumplimiento'));
        $this->assertSame('procesos', $user->notification_modules);
        $this->assertTrue($user->receives_audit_alerts);
    }

    public function test_editar_guarda_las_areas_y_auditorias(): void
    {
        $user = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => $this->auditor->id, 'notification_modules' => 'all',
        ]);

        $this->actingAs($this->superadmin)->patch(route('superadmin.users.update', $user), [
            'role_id' => $this->auditor->id, 'group_id' => $this->group->id, 'module_access' => 'cumplimiento',
            'notification_modules' => 'none', 'receives_audit_alerts' => '1',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('procesos', $user->module_access);
        $this->assertSame('none', $user->notification_modules);
        $this->assertTrue($user->receives_audit_alerts);
    }
}
