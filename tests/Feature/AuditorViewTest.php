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

    public function test_el_auditor_entra_a_su_inicio_de_auditorias(): void
    {
        // El Inicio de Gestión (procesos liberados, por aprobar, por vencer) es para admins y operativos.
        $this->actingAs($this->auditor)->get(route('gestion.home'))->assertRedirect(route('auditorias.home'));

        // En el menú, "Inicio" lleva al Inicio de Auditorías.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.index'))
            ->assertSee('href="' . route('auditorias.home') . '"', false);
    }

    public function test_solo_auditores_y_admins_entran_a_auditorias(): void
    {
        // Mismo grupo que el auditor, otro rol.
        $conRol = fn (string $slug): User => User::factory()->create([
            'group_id' => $this->auditor->group_id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => ucfirst($slug), 'slug' => $slug])->id,
        ]);

        // Un operativo que escribe la dirección a mano no entra.
        $this->actingAs($conRol('operative'))->get(route('auditorias.audits.checklist', 1))->assertForbidden();

        // Un admin sí entra.
        $this->actingAs($conRol('admin'))->get(route('auditorias.audits.checklist', 1))->assertOk();
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

    public function test_solo_la_auditoria_en_captura_se_puede_llenar(): void
    {
        // Auditoría 1 está "En captura": trae los soportes, el botón de hallazgos y la ventana.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1))
            ->assertSee('Soporte de la revisión (opcional)')
            ->assertSee('Registrar hallazgo')
            ->assertSee('Conclusiones de la auditoría');

        // Auditoría 2 está "Programada": se consulta, pero no se captura.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 2))
            ->assertDontSee('Soporte de la revisión (opcional)')
            ->assertDontSee('Registrar hallazgo')
            ->assertSee('Conclusiones de la auditoría');
    }

    public function test_los_hallazgos_se_registran_por_criterio_solo_en_auditorias_abiertas(): void
    {
        // Auditoría 1 (En captura): un botón en cada uno de sus 3 criterios.
        $checklist = $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1));
        $this->assertSame(3, substr_count($checklist->getContent(), 'Registra los hallazgos que identifiques en este criterio.'));

        // En una auditoría finalizada y en la lista de Hallazgos ya no se registran.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))->assertDontSee('Registrar hallazgo');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertDontSee('Registrar hallazgo');
    }

    public function test_cada_criterio_muestra_su_descripcion(): void
    {
        // La descripción va entre el título del criterio y la evidencia a verificar.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1))
            ->assertSeeInOrder([
                '2. La actividad cuenta con registro trazable.',
                'Cada vez que se realiza la actividad queda un registro',
                'Evidencia a verificar:',
            ]);
    }

    public function test_la_primera_pregunta_es_si_el_auditado_conoce_sus_procedimientos(): void
    {
        // En el checklist va primero y con su etiqueta, en todas las auditorías.
        foreach ([1, 2] as $id) {
            $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $id))
                ->assertSeeInOrder([
                    'Pregunta fija · va en todas las auditorías',
                    '1. El auditado conoce sus procedimientos y sabe dónde consultarlos.',
                    '2. La actividad cuenta con registro trazable.',
                ]);
        }

        // En el Resultado también es el criterio 1, así que lo que hay que corregir empieza en el 2.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))
            ->assertSee('2. Los contenedores están identificados con su etiqueta.');
    }

    public function test_cada_criterio_muestra_el_fragmento_del_procedimiento(): void
    {
        $checklist = $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1));

        // El fragmento va entre la descripción y la evidencia. La pregunta fija no trae fragmento.
        $checklist->assertSeeInOrder([
            '1. El auditado conoce sus procedimientos', 'Evidencia a verificar:',
            '2. La actividad cuenta con registro trazable.',
            'Qué dice el procedimiento · 6.3 Registro de maniobras', 'Al terminar cada maniobra', 'Evidencia a verificar:',
        ]);
        $this->assertSame(2, substr_count($checklist->getContent(), 'Qué dice el procedimiento ·'));

        // El enlace abre el proceso en otra pestaña, para no perder lo capturado, y el auditor sí puede entrar.
        $proceso = route('processes.index', ['q' => 'Control de maniobras y seguridad']);
        $checklist->assertSee('href="' . $proceso . '" target="_blank"', false);
        $this->actingAs($this->auditor)->get($proceso)->assertOk();
    }

    public function test_se_puede_solicitar_reprogramar_una_auditoria_no_finalizada(): void
    {
        // En captura y Programada: el botón abre la ventana, que muestra la fecha actual y pide solo el motivo.
        foreach ([1 => '11 sep 2026', 2 => '14 sep 2026'] as $id => $fecha) {
            $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', $id))
                ->assertSee('@click="$dispatch(\'solicitar-reprogramacion\')"', false)
                ->assertSeeInOrder(['Solicitar reprogramación', 'Fecha actual: ' . $fecha, 'Motivo', 'El auditor líder revisa la solicitud y agenda la nueva fecha.'])
                ->assertDontSee('id="reprogramacion-fecha"', false);
        }

        // Una auditoría finalizada ya no se reprograma.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))->assertDontSee('Solicitar reprogramación');
    }

    public function test_la_lista_de_asistencia_muestra_a_los_convocados(): void
    {
        // Auditoría 1 (En captura): el botón va arriba del contador y se marca quién asistió.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1))
            ->assertSeeInOrder([
                'Lista de asistencia',
                'Marca a las personas que asistieron a la llamada.',
                'Daniel Ruiz', 'Auditor líder · Equipo auditor',
                'Laura Méndez', 'Responsable del proceso · Equipo auditado',
                'criterios capturados',
            ]);

        // Auditoría 2 (Programada): se ve quién está convocado, pero todavía no se marca.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 2))
            ->assertSee('Podrás marcar la asistencia cuando la auditoría esté En captura.')
            ->assertDontSee('Marca a las personas que asistieron a la llamada.');
    }

    public function test_para_finalizar_deben_asistir_el_auditor_y_el_auditado(): void
    {
        // Auditoría 1: el auditor y el auditado llevan la etiqueta; la supervisora no.
        $checklist = $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1));
        $this->assertSame(2, substr_count($checklist->getContent(), '>Obligatorio</span>'));
        $checklist->assertSee('Para finalizar, marca en la lista de asistencia al auditor y al auditado.');
    }

    public function test_el_resultado_muestra_la_asistencia(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))
            ->assertSeeInOrder(['Lista de asistencia', '2 de 3 asistieron', 'Ana Torres', 'Asistió', 'Mariana Garza', 'No asistió']);
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 4))
            ->assertSee('3 de 3 asistieron')
            ->assertDontSee('No asistió');
    }

    public function test_el_resultado_muestra_las_conclusiones_del_auditor(): void
    {
        // Auditoría 3: las conclusiones van después de los hallazgos.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))
            ->assertSeeInOrder(['Hallazgos registrados por el auditor', 'Conclusiones del auditor', 'Hay que etiquetar los dos contenedores']);

        // Auditoría 4: el auditor no escribió conclusiones, así que la tarjeta no aparece.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 4))
            ->assertDontSee('Conclusiones del auditor');
    }

    public function test_el_resultado_muestra_sus_hallazgos(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))
            ->assertSee('Contenedores de residuos sin etiqueta');
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 4))
            ->assertSee('No se registraron hallazgos en esta auditoría.');
    }

    public function test_parcial_sale_en_que_necesitas_corregir(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 3))
            ->assertSeeInOrder(['Qué necesitas corregir', 'Parcial', 'Hallazgos registrados por el auditor']);
    }

    public function test_no_aplica_no_cuenta_en_la_calificacion(): void
    {
        // Auditoría 4: cuentan 95 y 89 (promedio 92). Si "No aplica" contara como 0, daría 61.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.result', 4))
            ->assertSee('92 / 100')
            ->assertSee('No aplica');
    }

    public function test_hallazgos_marca_los_vencidos_segun_la_fecha(): void
    {
        $this->travelTo('2026-11-03');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertSee('Vencido');

        $this->travelTo('2026-09-01');
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index'))->assertDontSee('Vencido');
    }

    public function test_los_hallazgos_se_clasifican_como_critico_o_no_critico(): void
    {
        // Al registrar un hallazgo solo hay dos opciones.
        $this->actingAs($this->auditor)->get(route('auditorias.audits.checklist', 1))
            ->assertSeeInOrder(['Clasificación', '>Crítico</option>', '>No crítico</option>'], false);

        // La lista de Hallazgos y el Resultado ya no usan Mayor ni Menor.
        foreach ([route('auditorias.findings.index'), route('auditorias.audits.result', 3)] as $url) {
            $this->actingAs($this->auditor)->get($url)
                ->assertSee('No crítico')
                ->assertDontSee('Mayor')
                ->assertDontSee('Menor');
        }
    }

    public function test_hallazgos_filtra_por_empresa(): void
    {
        $this->actingAs($this->auditor)->get(route('auditorias.findings.index', ['empresa' => 'Vigía']))
            ->assertSee('Registro de temperatura sin capturar')
            ->assertDontSee('Bitácora de maniobras sin firma del supervisor');
    }
}
