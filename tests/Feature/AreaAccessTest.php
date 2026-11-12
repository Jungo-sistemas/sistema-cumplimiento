<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Áreas (nov 2026): todos entran a Procesos y Auditorías, pero solo quien tiene acceso a Procesos
 * tiene Gestión; un admin u operativo "Solo Cumplimiento" ahí solo tiene Mi espacio. En Usuarios,
 * cada admin solo da, quita, edita o elimina lo de su propia área.
 */
class AreaAccessTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private Role $adminRole;
    private Role $opRole;
    private Role $auditorRole;
    private User $cumplimientoAdmin;
    private User $procesosAdmin;
    private User $bothAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);

        $this->adminRole   = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $this->opRole      = Role::create(['name' => 'Operativo', 'slug' => 'operative']);
        $this->auditorRole = Role::create(['name' => 'Auditor', 'slug' => 'auditor']);
        Role::create(['name' => 'Solo lectura', 'slug' => 'readonly']);

        $this->cumplimientoAdmin = $this->admin('Carla Cumplimiento', 'cumplimiento');
        $this->procesosAdmin     = $this->admin('Pablo Procesos', 'procesos');
        $this->bothAdmin         = $this->admin('Ana Ambos', 'all');
    }

    private function admin(string $name, string $access): User
    {
        return User::factory()->create([
            'name' => $name, 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => $this->adminRole->id, 'module_access' => $access,
        ]);
    }

    private function operative(string $access): User
    {
        return User::factory()->create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'scope_level' => 'company',
            'status' => 'active', 'role_id' => $this->opRole->id, 'module_access' => $access,
        ]);
    }

    private function regulation(string $name): Regulation
    {
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);

        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'is_active' => true, 'approval_status' => 'approved',
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);

        return $regulation;
    }

    // ── Gestión contra Mi espacio ──

    public function test_admin_de_cumplimiento_solo_tiene_mi_espacio_en_procesos_y_auditorias(): void
    {
        $admin = $this->cumplimientoAdmin;

        // Ni Gestión de Procesos ni de Auditorías: todo lo regresa a Mi espacio.
        $this->actingAs($admin)->get(route('gestion.home'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($admin)->get(route('processes.index'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($admin)->get(route('processes.create'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($admin)->get(route('auditorias.audits.index'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($admin)->get(route('auditorias.indicators'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($admin)->post(route('processes.trash.move'))->assertForbidden();

        // En Mi espacio: sus procedimientos y sus auditorías como auditado, sin botón a Gestión.
        $this->actingAs($admin)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSee('Mis documentos')
            ->assertSee('Mis auditorías')
            ->assertDontSee(route('view-mode.switch', 'gestion'), false);
    }

    public function test_operativo_de_cumplimiento_tiene_auditorias_solo_en_mi_espacio(): void
    {
        $operative = $this->operative('cumplimiento');

        $this->actingAs($operative)->get(route('auditorias.audits.index'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($operative)->get(route('mi-espacio.audits'))->assertOk();
    }

    public function test_admin_de_cumplimiento_consulta_sus_procesos_sin_poder_editarlos(): void
    {
        $regulation = $this->regulation('Control de maniobras');

        $this->actingAs($this->cumplimientoAdmin)->get(route('mi-espacio.documents', ['tab' => 'empresa', 'q' => 'maniobras']))
            ->assertOk()->assertSee('Control de maniobras');
        $this->actingAs($this->cumplimientoAdmin)->get(route('mi-espacio.documents.show', $regulation))
            ->assertOk()->assertSee('Control de maniobras')->assertDontSee('Subir nueva versión');

        $this->actingAs($this->cumplimientoAdmin)->get(route('processes.show', $regulation))
            ->assertOk()
            ->assertSee('Mis documentos')               // menú de Mi espacio
            ->assertDontSee('Subir nueva versión')
            ->assertDontSee('Compartir')
            ->assertDontSee(route('processes.editBasic', $regulation), false);

        $this->assertFalse($regulation->isEditableBy($this->cumplimientoAdmin));
        $this->actingAs($this->cumplimientoAdmin)->get(route('processes.edit', $regulation))
            ->assertRedirect(route('mi-espacio.home'));
    }

    public function test_admin_de_cumplimiento_sigue_en_cumplimiento_y_los_de_procesos_en_gestion(): void
    {
        // (El tablero usa SQL de PostgreSQL; Documentos generales basta para ver que no lo manda a Mi espacio.)
        $this->actingAs($this->cumplimientoAdmin)->get(route('documents.index'))->assertOk()->assertSee('Documentos generales');

        $this->actingAs($this->bothAdmin)->get(route('gestion.home'))->assertOk();
        $this->actingAs($this->procesosAdmin)->get(route('gestion.home'))->assertOk();

        // Cada quien en su sección, admins incluidos: Solo Procesos no entra a Cumplimiento.
        $this->actingAs($this->procesosAdmin)->get(route('assets.index'))->assertRedirect(route('gestion.home'));
    }

    public function test_operativo_de_cumplimiento_tampoco_tiene_gestion_de_procesos(): void
    {
        $operative = $this->operative('cumplimiento');

        $this->assertFalse($operative->hasProcesosGestion());
        $this->assertFalse($operative->isAuditeeOnly()); // en Cumplimiento sigue trabajando normal
        $this->actingAs($operative)->get(route('gestion.home'))->assertRedirect(route('mi-espacio.home'));
        $this->actingAs($operative)->get(route('mi-espacio.documents'))->assertOk();
    }

    // ── Usuarios por área ──

    public function test_admin_de_cumplimiento_solo_crea_usuarios_de_cumplimiento(): void
    {
        $consejo = JobPosition::create(['group_id' => $this->group->id, 'slug' => 'consejo', 'name' => 'Consejo']);

        // El formulario ni ofrece lo de Procesos.
        $this->actingAs($this->cumplimientoAdmin)->get(route('users.create'))
            ->assertOk()
            ->assertSee('Solo Cumplimiento')
            ->assertDontSee('Puesto en la organización')
            ->assertDontSee('Ambos módulos')
            ->assertDontSee('Evalúa y califica procedimientos');
        $this->actingAs($this->bothAdmin)->get(route('users.create'))
            ->assertOk()->assertSee('Puesto en la organización')->assertSee('Ambos módulos');

        $this->actingAs($this->cumplimientoAdmin)->post(route('users.store'), [
            'name' => 'Intruso', 'email' => 'intruso@test.mx', 'role_id' => $this->opRole->id,
            'company_id' => [$this->company->id], 'module_access' => 'procesos',
        ])->assertSessionHasErrors('module_access');

        $this->actingAs($this->cumplimientoAdmin)->post(route('users.store'), [
            'name' => 'Auditor', 'email' => 'auditor@test.mx', 'role_id' => $this->auditorRole->id,
        ])->assertForbidden();

        // Sin module_access queda en su área; el puesto (de Procesos) se ignora.
        $this->actingAs($this->cumplimientoAdmin)->post(route('users.store'), [
            'name' => 'Nuevo', 'email' => 'nuevo@test.mx', 'role_id' => $this->opRole->id,
            'company_id' => [$this->company->id], 'job_position_id' => $consejo->id,
        ])->assertRedirect(route('users.index'));

        $nuevo = User::where('email', 'nuevo@test.mx')->sole();
        $this->assertSame('cumplimiento', $nuevo->module_access);
        $this->assertCount(0, $nuevo->jobPositions);
    }

    public function test_los_admins_nuevos_quedan_en_el_area_de_quien_los_crea_y_eligen_sus_avisos(): void
    {
        $this->actingAs($this->procesosAdmin)->post(route('users.store'), [
            'name' => 'Admin P', 'email' => 'adminp@test.mx', 'role_id' => $this->adminRole->id,
            'receive_notifications' => '1',
        ])->assertRedirect(route('users.index'));

        $this->actingAs($this->cumplimientoAdmin)->post(route('users.store'), [
            'name' => 'Admin C', 'email' => 'adminc@test.mx', 'role_id' => $this->adminRole->id,
        ])->assertRedirect(route('users.index'));

        $adminP = User::where('email', 'adminp@test.mx')->sole();
        $adminC = User::where('email', 'adminc@test.mx')->sole();

        $this->assertSame(['procesos', 'procesos'], [$adminP->module_access, $adminP->notification_modules]);
        $this->assertSame(['cumplimiento', 'none'], [$adminC->module_access, $adminC->notification_modules]);
    }

    public function test_usuarios_de_otra_area_o_de_ambos_solo_los_toca_un_admin_de_ambos(): void
    {
        $ambos        = $this->operative('all');
        $soloProcesos = $this->operative('procesos');
        $soloCumple   = $this->operative('cumplimiento');

        $this->actingAs($this->cumplimientoAdmin)->patch(route('users.update', $ambos), [
            'role_id' => $this->opRole->id, 'module_access' => 'cumplimiento',
        ])->assertSessionHas('error');
        $this->actingAs($this->cumplimientoAdmin)->delete(route('users.destroy', $soloProcesos))->assertSessionHas('error');

        $this->assertSame('all', $ambos->fresh()->module_access);
        $this->assertNotNull($soloProcesos->fresh());

        $this->actingAs($this->cumplimientoAdmin)->get(route('users.index'))->assertOk()->assertSee('Otra área');

        // Su propia área sí.
        $this->actingAs($this->cumplimientoAdmin)->delete(route('users.destroy', $soloCumple))->assertSessionHas('success');
        $this->assertNull($soloCumple->fresh());

        // El admin de ambos edita al de ambos y conserva su puesto si no lo cambia.
        $this->actingAs($this->bothAdmin)->patch(route('users.update', $ambos), [
            'role_id' => $this->opRole->id, 'module_access' => 'procesos',
        ])->assertSessionHas('success');
        $this->assertSame('procesos', $ambos->fresh()->module_access);
    }

    public function test_un_admin_de_cumplimiento_no_le_cambia_los_puestos_a_nadie(): void
    {
        $lider = JobPosition::create(['group_id' => $this->group->id, 'slug' => 'lider', 'name' => 'Líder']);
        $user  = $this->operative('cumplimiento');
        $user->jobPositions()->attach($lider->id);

        $this->actingAs($this->cumplimientoAdmin)->patch(route('users.update', $user), [
            'role_id' => $this->opRole->id, 'module_access' => 'cumplimiento', 'job_position_id' => [],
        ])->assertSessionHas('success');

        $this->assertSame([$lider->id], $user->fresh()->jobPositions->pluck('id')->all());
    }
}
