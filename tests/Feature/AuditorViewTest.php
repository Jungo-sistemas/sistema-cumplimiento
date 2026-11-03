<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vista del auditor (Gestión de Auditorías). Por ahora las pantallas usan datos de ejemplo;
 * estas pruebas cuidan que carguen y que los cálculos sigan dando lo mismo.
 */
class AuditorViewTest extends TestCase
{
    use RefreshDatabase;

    private User $auditor;

    protected function setUp(): void
    {
        parent::setUp();

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);

        $this->auditor = User::factory()->create([
            'name' => 'Daniel Ruiz', 'group_id' => $group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Auditor', 'slug' => 'auditor'])->id,
        ]);
    }

        public function test_todas_las_pantallas_del_auditor_cargan(): void
    {
        foreach ([
            route('auditorias.home'),
            route('auditorias.audits.index'), route('auditorias.audits.index', ['tab' => 'finalizadas']),
            route('auditorias.audits.checklist', 1), route('auditorias.audits.checklist', 2),
            route('auditorias.audits.result', 3), route('auditorias.audits.result', 4),
            route('auditorias.findings.index'), route('auditorias.findings.index', ['tab' => 'causas']),
            route('auditorias.help'),
        ] as $url) {
            $this->actingAs($this->auditor)->get($url)->assertOk();
        }
    }

    public function test_una_auditoria_que_no_existe_da_404(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 99))->assertNotFound();
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 1))->assertNotFound();
    }

    public function test_el_resultado_calcula_la_calificacion_final(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))->assertSee('85 / 100');
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 4))->assertSee('92 / 100');
    }

    public function test_hallazgos_marca_los_vencidos_segun_la_fecha(): void
    {
        $this->travelTo('2026-11-03');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertSee('Vencido');

        $this->travelTo('2026-09-01');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertDontSee('Vencido');
    }

    public function test_hallazgos_filtra_por_empresa(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['empresa' => 'Vigía']))
            ->assertSee('Registro de temperatura sin capturar')
            ->assertDontSee('Bitácora de maniobras sin firma del supervisor');
    }
}
