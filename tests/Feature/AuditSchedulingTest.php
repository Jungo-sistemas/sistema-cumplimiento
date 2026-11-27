<?php

namespace Tests\Feature;

use App\Models\Audit;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AuditScheduledNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Programar auditoría: buscador de procesos, un solo envío y "Reprogramar" con su motivo y su propio error. */
class AuditSchedulingTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;
    private User $ana;
    private Regulation $regulation;

    protected function setUp(): void
    {
        parent::setUp();
        Notification::fake();
        config(['app.timezone' => 'UTC', 'auditorias.zona_horaria' => 'America/Mexico_City']);
        $this->travelTo(Carbon::create(2026, 11, 25, 15, 14, 0, 'UTC')); // 9:14 en México

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $group->id, 'show_in_processes' => true]);
        $make = fn (string $name, string $slug) => User::factory()->create([
            'name' => $name, 'group_id' => $group->id, 'company_id' => $company->id,
            'scope_level' => 'company', 'status' => 'active',
            'role_id' => Role::firstOrCreate(['slug' => $slug], ['name' => ucfirst($slug)])->id,
        ]);
        $this->auditor = $make('Pedro Auditor', 'auditor');
        $this->ana = $make('Ana Torres', 'operative');

        $type = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $this->regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-ALC-002', 'name' => 'CONTROL Y MANEJO DE CARROTANQUES', 'is_active' => true,
            'approval_status' => 'approved',
        ]);
    }

    private function create(array $extra = [])
    {
        return $this->actingAs($this->auditor)->post(route('auditorias.audits.store'), $extra + [
            'regulation_id' => $this->regulation->id, 'auditees' => [$this->ana->id],
            'fecha' => '2026-11-25', 'hora' => '09:30', // hoy en unos minutos
        ]);
    }

    public function test_el_proceso_se_busca_por_codigo_o_nombre(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))
            ->assertOk()
            ->assertSee('Buscar por código o nombre', false)
            ->assertDontSee('<select id="nueva-proceso"', false)
            ->assertSee('min="2026-11-25"', false); // hoy en México, no el día UTC
    }

    public function test_solo_se_auditan_los_procesos_con_codigo_p(): void
    {
        $viejo = Regulation::create([
            'group_id' => $this->regulation->group_id, 'company_id' => $this->regulation->company_id,
            'process_type_id' => $this->regulation->process_type_id,
            'code' => 'REG-ALC-002', 'name' => 'CONTROL VIEJO DESACTUALIZADO', 'is_active' => true, 'approval_status' => 'approved',
        ]);
        $sinCodigo = Regulation::create([
            'group_id' => $this->regulation->group_id, 'company_id' => $this->regulation->company_id,
            'process_type_id' => $this->regulation->process_type_id,
            'code' => 'CLI-PROCE-01', 'name' => 'OTRO CON P EN MEDIO', 'is_active' => true, 'approval_status' => 'approved',
        ]);

        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))
            ->assertOk()
            ->assertSee('CONTROL Y MANEJO DE CARROTANQUES')
            ->assertDontSee('CONTROL VIEJO DESACTUALIZADO')
            ->assertDontSee('OTRO CON P EN MEDIO');

        // Tampoco forzando el id.
        foreach ([$viejo, $sinCodigo] as $regulation) {
            $this->create(['regulation_id' => $regulation->id])->assertSessionHasErrors('regulation_id');
        }
        $this->assertSame(0, Audit::count());
    }

    public function test_varios_clics_no_crean_varias_auditorias(): void
    {
        $this->create()->assertRedirect(route('auditorias.audits.index'))->assertSessionHas('success');
        $this->create()->assertRedirect(route('auditorias.audits.index'))
            ->assertSessionHas('success', 'Esa auditoría ya se había creado; no se duplicó.');

        $this->assertSame(1, Audit::count());

        // Otra persona auditada (u otro momento) sí es otra auditoría.
        $luis = User::factory()->create([
            'group_id' => $this->ana->group_id, 'company_id' => $this->ana->company_id, 'scope_level' => 'company',
            'status' => 'active', 'role_id' => $this->ana->role_id,
        ]);
        $this->create(['auditees' => [$luis->id]]);
        $this->assertSame(2, Audit::count());

        $this->travel(3)->minutes();
        $this->create(['hora' => '10:00']);
        $this->assertSame(3, Audit::count());
    }

    public function test_cambiar_fecha_rechazada_reabre_su_ventana_con_el_mensaje(): void
    {
        $this->create();
        $audit = Audit::sole();
        $index = route('auditorias.audits.index');

        // Hora que ya pasó: vuelve con el mensaje en la ventana de "Reprogramar", no en "Programar".
        $this->actingAs($this->auditor)->from($index)
            ->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-25', 'hora' => '09:00'])
            ->assertRedirect($index)
            ->assertSessionHasErrorsIn('schedule', 'fecha')
            ->assertSessionHas('reprogramar.hora', '09:00');
        $this->actingAs($this->auditor)->get($index)->assertOk()->assertSee('La fecha tiene que ser futura.');

        // Más tarde hoy: se guarda.
        $this->actingAs($this->auditor)->from($index)
            ->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-25', 'hora' => '11:15', 'motivo' => 'Se cruzó con otra reunión.'])
            ->assertSessionHas('success');
        $this->assertSame('2026-11-25 11:15', $audit->fresh()->scheduled_at->format('Y-m-d H:i'));
    }

    public function test_reprogramar_pide_el_motivo_y_lo_manda_en_el_aviso(): void
    {
        // Poner la fecha por primera vez no pide motivo.
        $this->create(['fecha' => null, 'hora' => null]);
        $audit = Audit::sole();
        $this->actingAs($this->auditor)->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-26', 'hora' => '10:00'])
            ->assertSessionHas('success');
        $this->assertNull($audit->fresh()->reschedule_reason);
        $this->assertNotNull($audit->fresh()->notice_sent_at); // es mañana: ya se les avisó

        // Ya con fecha, el botón dice Reprogramar.
        $index = route('auditorias.audits.index');
        $this->actingAs($this->auditor)->get($index)
            ->assertOk()->assertSee('>Reprogramar</button>', false)->assertDontSee('Cambiar fecha');

        // Reprogramar sin motivo: vuelve a su ventana con el mensaje y no cambia nada.
        $this->actingAs($this->auditor)->from($index)
            ->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-27', 'hora' => '10:00', 'motivo' => '   '])
            ->assertRedirect($index)
            ->assertSessionHasErrorsIn('schedule', 'motivo')
            ->assertSessionHas('reprogramar.fecha', '2026-11-27');
        $this->assertSame('2026-11-26', $audit->fresh()->scheduled_at->toDateString());

        // Con motivo: se guarda, va en el aviso del cambio y se ve en el checklist.
        $motivo = 'El auditado está en una visita a planta.';
        $this->actingAs($this->auditor)->from($index)
            ->post(route('auditorias.audits.schedule', $audit), ['fecha' => '2026-11-27', 'hora' => '10:00', 'motivo' => $motivo])
            ->assertSessionHas('success');
        $this->assertSame($motivo, $audit->fresh()->reschedule_reason);
        Notification::assertSentTo($this->ana, AuditScheduledNotification::class,
            fn ($n) => $n->rescheduled && str_contains($n->toMail($this->ana)->render(), $motivo));
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $audit))
            ->assertOk()->assertSee('Motivo de la última reprogramación:')->assertSee($motivo)
            ->assertSee('>Reprogramar</button>', false)->assertDontSee('Cambiar fecha');
    }
}
