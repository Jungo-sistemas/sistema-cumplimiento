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

    public function test_al_dar_de_alta_se_eligen_los_modulos_uno_por_uno(): void
    {
        $this->actingAs($this->admin)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Módulos visibles')
            ->assertSee('name="modules[]" value="objetivos"', false)
            ->assertSee('name="modules[]" value="cursos"', false);

        $opRole = Role::firstOrCreate(['slug' => 'operative'], ['name' => 'Operativo']);
        $alta = fn (string $email, array $modules) => $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Persona', 'email' => $email, 'role_id' => $opRole->id, 'modules' => $modules,
        ]);

        $alta('fiscal@test.mx', ['fiscal', 'procesos'])->assertRedirect(route('users.index'));
        $alta('todo@test.mx', array_keys(PortalModules::LABELS))->assertRedirect(route('users.index'));
        $alta('objetivos@test.mx', ['objetivos'])->assertRedirect(route('users.index'));
        $alta('nada@test.mx', [])->assertSessionHasErrors('modules');

        $fiscal = User::where('email', 'fiscal@test.mx')->sole();
        $this->assertSame(['all', ['fiscal']], [$fiscal->module_access, $fiscal->portal_modules]);
        $this->assertSame(['fiscal', 'procesos'], $fiscal->portalModules());

        $todo = User::where('email', 'todo@test.mx')->sole();
        $this->assertSame(['all', null], [$todo->module_access, $todo->portal_modules]);

        $objetivos = User::where('email', 'objetivos@test.mx')->sole();
        $this->assertSame(['cumplimiento', ['objetivos']], [$objetivos->module_access, $objetivos->portal_modules]);
        $this->assertFalse($objetivos->canAccessModule('procesos'));
    }

    public function test_sin_procesos_y_auditorias_igual_entra_a_mi_espacio_para_ser_auditado(): void
    {
        foreach (['operative', 'admin'] as $slug) {
            $user = User::factory()->create([
                'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
                'module_access' => 'cumplimiento', 'portal_modules' => ['regulatorio'],
                'role_id' => Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id,
            ]);

            // Mi espacio: sus auditorías, hallazgos y procedimientos.
            foreach (['mi-espacio.home', 'mi-espacio.audits', 'mi-espacio.findings', 'mi-espacio.documents'] as $route) {
                $this->actingAs($user)->get(route($route))->assertOk();
            }

            // La gestión de procesos y auditorías no: lo regresa a Mi espacio.
            $this->actingAs($user)->get(route('gestion.home'))->assertRedirect(route('mi-espacio.home'));
            $this->actingAs($user)->get(route('auditorias.audits.index'))->assertRedirect(route('mi-espacio.home'));
        }
    }

    public function test_las_notificaciones_se_eligen_por_modulo(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Admin N', 'email' => 'adminn@test.mx', 'role_id' => $this->admin->role_id,
            'modules' => ['fiscal', 'regulatorio', 'cursos'],
            // Procesos no cuenta: no tendrá ese módulo.
            'notify_modules' => ['fiscal', 'cursos', 'procesos'], 'receives_audit_alerts' => '1',
        ])->assertRedirect(route('users.index'));

        $nuevo = User::where('email', 'adminn@test.mx')->sole();
        $this->assertSame(['fiscal', 'cursos'], $nuevo->notify_modules);
        $this->assertSame('cumplimiento', $nuevo->notification_modules);
        $this->assertTrue($nuevo->wantsModuleNotifications('cursos'));
        $this->assertFalse($nuevo->wantsModuleNotifications('regulatorio'));
        // Los avisos que ya existen siguen por área.
        $this->assertTrue($nuevo->wantsNotificationsFor('cumplimiento'));
        $this->assertFalse($nuevo->wantsNotificationsFor('procesos'));

        // Ninguna pastilla: sin avisos de administración.
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Admin M', 'email' => 'adminm@test.mx', 'role_id' => $this->admin->role_id,
            'modules' => ['fiscal', 'procesos'],
        ] + ['notify_modules' => []])->assertRedirect(route('users.index'));
        $this->assertSame('none', User::where('email', 'adminm@test.mx')->sole()->notification_modules);

        // Usuarios de antes (sin elección por módulo): se toma su área.
        $this->assertTrue($this->admin->wantsModuleNotifications('objetivos'));
    }

    public function test_solo_entra_a_los_modulos_que_tiene_marcados(): void
    {
        $fiscal = $this->operative('all');
        $fiscal->update(['portal_modules' => ['fiscal']]);

        $this->actingAs($fiscal)->get(route('documents.index'))
            ->assertOk()
            ->assertSee('Cumplimiento Fiscal')->assertSee('Procesos y Auditorías')
            ->assertDontSee('Cumplimiento Regulatorio')->assertDontSee('Objetivos');

        $this->actingAs($fiscal)->get(route('assets.index'))->assertRedirect(route('documents.index'));
        $this->actingAs($fiscal)->get(route('coming-soon', 'cursos'))->assertRedirect(route('documents.index'));
        $this->actingAs($fiscal)->get(route('investment-requests.index'))->assertRedirect(route('documents.index'));
        $this->actingAs($fiscal)->get(route('gestion.home'))->assertOk();
    }

    public function test_un_admin_solo_da_y_edita_los_modulos_que_tiene(): void
    {
        $soloFiscal = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'module_access' => 'cumplimiento', 'portal_modules' => ['fiscal'], 'role_id' => $this->admin->role_id,
        ]);

        $this->actingAs($soloFiscal)->get(route('users.create'))
            ->assertOk()
            ->assertSee('name="modules[]" value="fiscal"', false)
            ->assertDontSee('name="modules[]" value="regulatorio"', false);

        $opRole = Role::firstOrCreate(['slug' => 'operative'], ['name' => 'Operativo']);
        $this->actingAs($soloFiscal)->post(route('users.store'), [
            'name' => 'Intruso', 'email' => 'intruso@test.mx', 'role_id' => $opRole->id, 'modules' => ['regulatorio'],
        ])->assertSessionHasErrors('modules.0');

        // Sin casillas (forma anterior) tampoco le da lo que no tiene.
        $this->actingAs($soloFiscal)->post(route('users.store'), [
            'name' => 'Nuevo', 'email' => 'nuevo@test.mx', 'role_id' => $opRole->id,
        ])->assertRedirect(route('users.index'));
        $this->assertSame(['fiscal'], User::where('email', 'nuevo@test.mx')->sole()->portal_modules);

        // A quien tiene Regulatorio no lo puede editar.
        $this->assertFalse($soloFiscal->canManageUser($this->operative('cumplimiento')));
    }

    public function test_el_superadmin_guarda_los_modulos_marcados(): void
    {
        $superadmin = User::factory()->create([
            'scope_level' => 'global', 'status' => 'active',
            'role_id' => Role::firstOrCreate(['slug' => 'superadmin'], ['name' => 'Superadministrador'])->id,
        ]);
        $user = $this->operative('all');

        $this->actingAs($superadmin)->patch(route('superadmin.users.update', $user), [
            'role_id' => $user->role_id, 'group_id' => $this->group->id, 'modules' => ['flujos', 'cursos'],
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame(['cumplimiento', ['flujos', 'cursos']], [$user->module_access, $user->portal_modules]);

        // Notificaciones en pastillas: solo cuentan los módulos que tiene.
        $this->actingAs($superadmin)->patch(route('superadmin.users.update', $user), [
            'role_id' => $user->role_id, 'group_id' => $this->group->id, 'modules' => ['flujos', 'procesos'],
            'notify_modules' => ['flujos', 'cursos'], 'notification_modules' => 'all', 'receives_audit_alerts' => '1',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame([['flujos'], 'cumplimiento', true], [$user->notify_modules, $user->notification_modules, $user->receives_audit_alerts]);
        $this->assertSame(['flujos'], $user->notifyModuleList());

        // La pantalla ya no usa la lista desplegable y abre la edición con lo que tiene elegido.
        $this->actingAs($superadmin)->get(route('superadmin.users'))
            ->assertOk()
            ->assertSee("fiscal: 'Fiscal', regulatorio: 'Regulatorio'", false)
            ->assertDontSee('Ninguna (solo avisos personales)');
    }

    public function test_quien_solo_tiene_cumplimiento_ve_todo_menos_la_gestion_de_procesos(): void
    {
        $cumplimiento = $this->operative('cumplimiento');

        $this->actingAs($cumplimiento)->get(route('coming-soon', 'cursos'))
            ->assertOk()
            ->assertSee('Cumplimiento Fiscal')->assertSee('Objetivos')->assertSee('Flujos');
    }
}
