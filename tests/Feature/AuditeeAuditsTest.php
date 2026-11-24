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
use App\Notifications\AuditScheduledNotification;
use App\Notifications\AuditUpcomingNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Mis auditorías (rol auditado): solo informativa. El auditor fija la fecha y al auditado le llega
 * el aviso 3 días antes (auditorias.aviso_dias_antes); antes de eso no la ve.
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

    public function test_solo_ve_las_que_estan_a_tres_dias_o_menos_de_su_fecha(): void
    {
        $this->audit('Proceso En Tres Dias', '2026-11-01', ['scheduled_at' => '2026-11-15 10:00:00']);
        $this->audit('Proceso En Cuatro Dias', '2026-11-01', ['scheduled_at' => '2026-11-16 10:00:00']);
        $this->audit('Proceso Sin Fecha', '2026-11-01');
        $this->audit('De otra persona', '2026-11-01', ['scheduled_at' => '2026-11-13 10:00:00'], $this->other);

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))
            ->assertOk()
            ->assertSee('Proceso En Tres Dias')
            ->assertSee('15 de noviembre de 2026, 10:00')
            ->assertSee('Ver proceso')
            ->assertDontSee('Proceso En Cuatro Dias')
            ->assertDontSee('Proceso Sin Fecha')
            ->assertDontSee('De otra persona');
    }

    public function test_historial_muestra_realizadas_con_su_calificacion(): void
    {
        $this->audit('Auditoría realizada', '2026-08-01', [
            'status' => Audit::STATUS_COMPLETED, 'scheduled_at' => '2026-08-20 12:00:00',
            'completed_at' => '2026-08-20 13:00:00', 'result' => '92 / 100',
        ]);
        $this->audit('Proceso Todavia Pendiente', '2026-11-01', ['scheduled_at' => '2026-11-13 10:00:00']);

        $this->actingAs($this->auditee)->get(route('mi-espacio.audits', ['tab' => 'historial']))
            ->assertSee('Auditoría realizada')->assertSee('92 / 100')->assertDontSee('Proceso Todavia Pendiente');
    }

    public function test_el_aviso_llega_una_vez_tres_dias_antes_de_la_fecha(): void
    {
        $cerca  = $this->audit('Cerca', '2026-11-01', ['scheduled_at' => '2026-11-15 10:00:00']);
        $lejos  = $this->audit('Lejos', '2026-11-01', ['scheduled_at' => '2026-11-20 10:00:00']);
        $sinDia = $this->audit('Sin fecha', '2026-11-01');

        $this->artisan('audits:notify-upcoming')->assertSuccessful();
        $this->artisan('audits:notify-upcoming')->assertSuccessful(); // segunda corrida: no repite

        Notification::assertSentToTimes($this->auditee, AuditUpcomingNotification::class, 1);
        $this->assertNotNull($cerca->fresh()->notice_sent_at);
        $this->assertNull($lejos->fresh()->notice_sent_at);
        $this->assertNull($sinDia->fresh()->notice_sent_at);

        // Tres días antes de la del 20, se avisa esa.
        $this->travelTo(now()->setDate(2026, 11, 17)->setTime(7, 30));
        $this->artisan('audits:notify-upcoming')->assertSuccessful();
        Notification::assertSentToTimes($this->auditee, AuditUpcomingNotification::class, 2);
    }

    public function test_no_avisa_auditorias_cuya_fecha_ya_paso(): void
    {
        $this->audit('Pasada', '2026-11-01', ['scheduled_at' => '2026-11-11 10:00:00']);

        $this->artisan('audits:notify-upcoming')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_el_aviso_lleva_la_fecha_y_hora(): void
    {
        $audit = $this->audit('Noviembre', '2026-11-01', ['scheduled_at' => '2026-11-15 10:00:00']);

        $html = (new AuditUpcomingNotification($audit))->toMail($this->auditee)->render();

        $this->assertStringContainsString('15 de noviembre de 2026, 10:00', $html);
    }

    public function test_el_auditor_fija_la_fecha_y_el_aviso_sale_hasta_tres_dias_antes(): void
    {
        $audit = $this->audit('Proceso Agendado', '2026-11-01');

        // Fecha lejana: se guarda, pero el auditado todavía no se entera ni la ve.
        $this->artisan('audits:set-date', ['audit' => $audit->id, 'fecha' => '2026-11-25 10:00'])->assertSuccessful();
        Notification::assertNothingSent();
        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))->assertDontSee('Proceso Agendado');

        // El auditor la adelanta a dentro de 2 días: el aviso sale en ese momento.
        $this->artisan('audits:set-date', ['audit' => $audit->id, 'fecha' => '2026-11-14 16:30'])->assertSuccessful();
        Notification::assertSentToTimes($this->auditee, AuditUpcomingNotification::class, 1);
        Notification::assertNotSentTo($this->auditee, AuditScheduledNotification::class);
        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))->assertSee('Proceso Agendado');

        // Ya avisado, si la cambia de nuevo le llega "Cambió la fecha" (y la sigue viendo).
        $this->artisan('audits:set-date', ['audit' => $audit->id, 'fecha' => '2026-11-30 09:00'])->assertSuccessful();
        Notification::assertSentTo($this->auditee, AuditScheduledNotification::class, fn ($n) => $n->rescheduled);
        Notification::assertSentToTimes($this->auditee, AuditUpcomingNotification::class, 1);
        $this->assertSame('2026-11-30 09:00', $audit->fresh()->scheduled_at->format('Y-m-d H:i'));
        $this->actingAs($this->auditee)->get(route('mi-espacio.audits'))->assertSee('Proceso Agendado');
    }

    public function test_los_dias_de_aviso_se_configuran(): void
    {
        config(['auditorias.aviso_dias_antes' => 5]);
        $this->audit('Proceso En Cinco Dias', '2026-11-01', ['scheduled_at' => '2026-11-17 10:00:00']);

        $this->artisan('audits:notify-upcoming')->assertSuccessful();

        Notification::assertSentToTimes($this->auditee, AuditUpcomingNotification::class, 1);
    }

    public function test_inicio_y_campana_cuentan_las_proximas_auditorias(): void
    {
        $this->audit('Cerca', '2026-11-01', ['scheduled_at' => '2026-11-14 10:00:00']);
        $this->audit('Lejos', '2026-11-01', ['scheduled_at' => '2026-12-05 10:00:00']);

        $this->actingAs($this->auditee)->get(route('mi-espacio.home'))
            ->assertOk()
            ->assertSeeInOrder(['Mis auditorías activas', '1'])
            ->assertSee('Próximas auditorías');

        $this->actingAs($this->other)->get(route('mi-espacio.home'))
            ->assertDontSee('Próximas auditorías');
    }
}
