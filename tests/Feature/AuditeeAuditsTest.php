<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuditMonthStartedNotification;
use App\Notifications\AuditScheduledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Mis auditorías (rol auditado): solo informativa. El auditado se entera del MES cuando empieza
 * (nunca antes, nunca el día) y ve la fecha y hora cuando el auditor la registra.
 */
class AuditeeAuditsTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $auditor;
    private User $auditee;
    private User $other;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        $this->travelTo(now()->setDate(2026, 11, 12)->setTime(9, 0));

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id]);
        $roleId = Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id;

        foreach (['auditor' => 'Pedro Auditor', 'auditee' => 'Ana Torres', 'other' => 'Luis Otro'] as $prop => $name) {
            $this->{$prop} = User::factory()->create([
                'name' => $name, 'group_id' => $this->group->id, 'company_id' => $this->company->id,
                'scope_level' => 'company', 'status' => 'active', 'role_id' => $roleId,
            ]);
        }
    }

    private function audit(string $name, string $plannedFor, array $attributes = [], ?User $auditee = null): Audit
    {
        $type = ProcessType::firstOrCreate(['group_id' => $this->group->id, 'name' => 'Operaciones'], ['is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'ALC-' . fake()->unique()->numberBetween(100, 999), 'name' => $name,
            'is_active' => true, 'approval_status' => 'approved', 'impact_level' => 'alto',
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);

        $audit = Audit::create($attributes + [
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'regulation_id' => $regulation->id,
            'auditor_id' => $this->auditor->id, 'planned_for' => $plannedFor,
        ]);
        $audit->auditees()->attach(($auditee ?? $this->auditee)->id);

        return $audit;
    }

    public function test_solo_ve_las_de_su_mes_y_anteriores_nunca_las_de_meses_siguientes(): void
    {
        $this->audit('Carga de combustible', '2026-11-01');
        $this->audit('Pendiente de octubre', '2026-10-01');
        $this->audit('Auditoría de diciembre', '2026-12-01');
        $this->audit('De otra persona', '2026-11-01', [], $this->other);

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))
            ->assertOk()
            ->assertSee('Carga de combustible')
            ->assertSee('Pendiente de octubre')
            ->assertSee('Noviembre 2026')
            ->assertSee('Ver proceso')
            ->assertDontSee('Auditoría de diciembre')
            ->assertDontSee('De otra persona');
    }

    public function test_sin_fecha_muestra_solo_el_mes_y_con_fecha_muestra_dia_y_hora(): void
    {
        $this->audit('Proceso Sin Agendar', '2026-11-01');
        $this->audit('Proceso Agendado', '2026-11-01', ['scheduled_at' => '2026-11-18 10:00:00']);

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits', ['estado' => 'sin_fecha']))
            ->assertSee('Proceso Sin Agendar')->assertSee('Programada')->assertDontSee('Proceso Agendado');

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits', ['estado' => 'con_fecha']))
            ->assertSee('Proceso Agendado')->assertSee('18 de noviembre de 2026, 10:00')->assertDontSee('Proceso Sin Agendar');
    }

    public function test_historial_muestra_realizadas_con_su_calificacion(): void
    {
        $this->audit('Auditoría realizada', '2026-08-01', [
            'status' => Audit::STATUS_COMPLETED, 'scheduled_at' => '2026-08-20 12:00:00',
            'completed_at' => '2026-08-20 13:00:00', 'result' => '92 / 100',
        ]);
        $this->audit('Proceso Todavia Pendiente', '2026-11-01');

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits', ['tab' => 'historial']))
            ->assertSee('Auditoría realizada')->assertSee('92 / 100')->assertDontSee('Proceso Todavia Pendiente');
    }

    public function test_el_aviso_del_mes_llega_una_vez_y_solo_cuando_empieza_el_mes(): void
    {
        $nov = $this->audit('Noviembre', '2026-11-01');
        $dic = $this->audit('Diciembre', '2026-12-01');

        $this->artisan('audits:notify-month')->assertSuccessful();
        $this->artisan('audits:notify-month')->assertSuccessful(); // segunda corrida: no repite

        Notification::assertSentToTimes($this->auditee, AuditMonthStartedNotification::class, 1);
        $this->assertNotNull($nov->fresh()->month_notified_at);
        $this->assertNull($dic->fresh()->month_notified_at);

        // Al llegar diciembre, se avisa la de diciembre.
        $this->travelTo(now()->setDate(2026, 12, 1)->setTime(7, 30));
        $this->artisan('audits:notify-month')->assertSuccessful();
        Notification::assertSentToTimes($this->auditee, AuditMonthStartedNotification::class, 2);
    }

    public function test_el_aviso_del_mes_no_lleva_el_dia(): void
    {
        $audit = $this->audit('Noviembre', '2026-11-01', ['scheduled_at' => '2026-11-18 10:00:00']);

        $html = (new AuditMonthStartedNotification($audit))->toMail($this->auditee)->render();

        $this->assertStringContainsString('noviembre 2026', mb_strtolower($html));
        $this->assertStringNotContainsString('18 de noviembre', $html);
    }

    public function test_registrar_la_fecha_avisa_al_auditado_si_su_mes_ya_empezo(): void
    {
        $nov = $this->audit('Noviembre', '2026-11-01');
        $dic = $this->audit('Diciembre', '2026-12-01');

        $this->artisan('audits:set-date', ['audit' => $nov->id, 'fecha' => '2026-11-18 10:00'])->assertSuccessful();
        Notification::assertSentTo($this->auditee, AuditScheduledNotification::class, fn ($n) => ! $n->rescheduled);

        $this->artisan('audits:set-date', ['audit' => $nov->id, 'fecha' => '2026-11-20 16:30'])->assertSuccessful();
        Notification::assertSentTo($this->auditee, AuditScheduledNotification::class, fn ($n) => $n->rescheduled);
        $this->assertSame('2026-11-20 16:30', $nov->fresh()->scheduled_at->format('Y-m-d H:i'));

        // Diciembre todavía no empieza: se guarda la fecha pero no se avisa ni se ve.
        $this->artisan('audits:set-date', ['audit' => $dic->id, 'fecha' => '2026-12-05 09:00'])->assertSuccessful();
        Notification::assertSentToTimes($this->auditee, AuditScheduledNotification::class, 2);
        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))->assertDontSee('Diciembre');
    }

    public function test_inicio_y_campana_cuentan_las_auditorias_del_mes(): void
    {
        $this->audit('Noviembre', '2026-11-01');
        $this->audit('Diciembre', '2026-12-01');

        $this->actingAs($this->auditee)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSeeInOrder(['Mis auditorías activas', '1'])
            ->assertSee('Auditorías de este mes');

        $this->actingAs($this->other)->get(route('mi-espacio.home'))
            ->assertDontSee('Auditorías de este mes');
    }
}
