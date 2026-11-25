<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    private function jefe(): User
    {
        return User::factory()->create([
            'name' => 'Patricia Vega', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => $this->jefeRole->id, 'module_access' => 'procesos',
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

    // ── Su menú y sus pantallas ──

    public function test_el_jefe_entra_a_su_inicio_y_ve_su_menu(): void
    {
        $jefe = $this->jefe();

        // Al iniciar sesión, quien es "Solo Procesos" llega a Gestión; al Jefe de ahí lo manda a su Inicio.
        $this->actingAs($jefe)->get(route('gestion.home'))->assertRedirect(route('auditorias.jefe.home'));

        $this->actingAs($jefe)->get(route('auditorias.jefe.home'))
            ->assertOk()
            ->assertSee('Planeación')
            ->assertSee('href="' . route('auditorias.jefe.home') . '"', false)
            ->assertSee('href="' . route('auditorias.jefe.program') . '"', false)
            ->assertSee('href="' . route('auditorias.jefe.workload') . '"', false)
            ->assertSee('href="' . route('auditorias.jefe.reschedules') . '"', false)
            ->assertSee('href="' . route('processes.index') . '"', false)
            // No aprueba procesos ni audita: sin "Mis aprobaciones" ni la vista del auditor.
            ->assertDontSee('href="' . route('my-approvals.index') . '"', false)
            ->assertDontSee('href="' . route('auditorias.audits.index') . '"', false)
            ->assertDontSee('href="' . route('auditorias.help') . '"', false);
    }

    public function test_todas_las_pantallas_del_jefe_cargan(): void
    {
        $jefe = $this->jefe();

        foreach (['auditorias.jefe.home', 'auditorias.jefe.program', 'auditorias.jefe.workload', 'auditorias.jefe.reschedules'] as $ruta) {
            $html = $this->actingAs($jefe)->get(route($ruta))->assertOk()->getContent();
            // Cada <div> que se abre se cierra. Si falta o sobra uno, la página carga pero se acomoda raro.
            $this->assertSame(substr_count($html, '<div'), substr_count($html, '</div>'), "Hay un <div> de más o de menos en $ruta");
        }
    }

    public function test_solo_el_jefe_entra_a_la_planeacion_y_no_audita(): void
    {
        $auditor = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active', 'module_access' => 'procesos',
            'role_id' => Role::create(['name' => 'Auditor', 'slug' => 'auditor'])->id,
        ]);

        // Ni un admin ni un auditor entran a la planeación.
        $this->actingAs($this->admin('all'))->get(route('auditorias.jefe.home'))->assertForbidden();
        $this->actingAs($auditor)->get(route('auditorias.jefe.program'))->assertForbidden();

        // El Jefe no audita: la vista del auditor no es para él.
        $this->actingAs($this->jefe())->get(route('auditorias.audits.index'))->assertForbidden();
    }

    // ── Su Inicio, con las auditorías reales del grupo ──

    public function test_el_inicio_resume_el_programa_del_anio_y_las_auditorias_del_mes(): void
    {
        $this->travelTo(Carbon::create(2026, 11, 17, 10, 0));

        $empresa = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $tipo = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $otroGrupo = Group::create(['name' => 'Otro', 'slug' => 'otro', 'is_active' => true]);
        $auditor = User::factory()->create([
            'name' => 'Daniel Ruiz', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Auditor', 'slug' => 'auditor'])->id, 'module_access' => 'procesos',
        ]);

        // Una auditoría por proceso, para reconocerla por su nombre en la página.
        $auditorias = [
            // Del año y vigentes: 1 realizada de 3.
            'Carga de autotanques'  => ['status' => 'realizada', 'planned_for' => '2026-03-01', 'scheduled_at' => '2026-03-10 09:00:00'],
            'Inventario de diésel'  => ['planned_for' => '2026-10-01', 'scheduled_at' => '2026-11-20 10:00:00'], // la movieron a este mes
            'Control de accesos'    => ['planned_for' => '2026-11-01'],                                          // sin fecha, este mes
            // No cuentan: cancelada, de otro año y de otro grupo.
            'Limpieza de muelles'   => ['status' => 'cancelada', 'planned_for' => '2026-11-01'],
            'Bitácora de 2025'      => ['status' => 'realizada', 'planned_for' => '2025-11-01', 'scheduled_at' => '2025-11-12 09:00:00'],
            'Proceso de otro grupo' => ['group_id' => $otroGrupo->id, 'planned_for' => '2026-11-01'],
        ];
        $n = 0;
        foreach ($auditorias as $proceso => $datos) {
            $regulation = Regulation::create([
                'group_id' => $this->group->id, 'company_id' => $empresa->id, 'process_type_id' => $tipo->id,
                'code' => 'P-' . ++$n, 'name' => $proceso, 'is_active' => true, 'approval_status' => 'approved',
            ]);
            Audit::create($datos + [
                'group_id' => $this->group->id, 'company_id' => $empresa->id, 'regulation_id' => $regulation->id, 'auditor_id' => $auditor->id,
            ]);
        }

        $this->actingAs($this->jefe())->get(route('auditorias.jefe.home'))
            ->assertOk()
            ->assertViewHas('total', 3)
            ->assertViewHas('realizadas', 1)
            ->assertViewHas('avance', 33)
            ->assertViewHas('sinFecha', 1)
            ->assertSee('Este mes · noviembre de 2026')
            // Las del mes, por fecha: la que aún no tiene día (cuenta el mes del plan) y la que movieron al 20.
            ->assertSeeInOrder(['Control de accesos', 'ALCOM · Daniel Ruiz · Sin fecha', 'Inventario de diésel', '20 de noviembre de 2026, 10:00', 'Programada'])
            ->assertDontSee('Carga de autotanques')
            ->assertDontSee('Limpieza de muelles')
            ->assertDontSee('Bitácora de 2025')
            ->assertDontSee('Proceso de otro grupo');
    }

    public function test_sin_auditorias_el_inicio_lo_dice_y_el_mes_es_el_de_mexico(): void
    {
        // 30 de noviembre, 9 de la noche en México: en UTC ya es 1 de diciembre, pero aquí sigue siendo noviembre.
        $this->travelTo(Carbon::create(2026, 12, 1, 3, 0));

        $this->actingAs($this->jefe())->get(route('auditorias.jefe.home'))
            ->assertOk()
            ->assertViewHas('total', 0)
            ->assertViewHas('avance', 0)
            ->assertSee('Este mes · noviembre de 2026')
            ->assertSee('No hay auditorías este mes.');
    }
}
