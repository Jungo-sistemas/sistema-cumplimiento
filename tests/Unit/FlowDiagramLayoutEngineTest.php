<?php

namespace Tests\Unit;

use App\Services\FlowDiagramLayoutEngine;
use PHPUnit\Framework\TestCase;

class FlowDiagramLayoutEngineTest extends TestCase
{
    private FlowDiagramLayoutEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FlowDiagramLayoutEngine();
    }

    private function diagrama(array $overrides = []): array
    {
        return array_merge([
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Líder'],
                ['id' => 'gerente', 'nombre' => 'Gerente'],
            ],
            'decisiones' => [],
            'pasos_especiales' => [],
        ], $overrides);
    }

    private function findEdge(array $edges, string $from, string $to): array
    {
        foreach ($edges as $edge) {
            if ($edge['from'] === $from && $edge['to'] === $to) {
                return $edge;
            }
        }

        $this->fail("No se encontró la arista {$from}->{$to}.");
    }

    public function test_flujo_secuencial_simple_asigna_carril_y_encadena_inicio_fin(): void
    {
        $pasos = [
            ['titulo' => 'Primer paso', 'responsable' => 'Líder'],
            ['titulo' => 'Segundo paso', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama(), $pasos);

        $this->assertSame('lider', $layout['nodes']['inicio']['carril_id']);
        $this->assertSame('gerente', $layout['nodes']['fin']['carril_id']);
        $this->assertSame('lider', $layout['nodes']['paso1']['carril_id']);
        $this->assertSame('gerente', $layout['nodes']['paso2']['carril_id']);

        $this->findEdge($layout['edges'], 'inicio', 'paso1');
        $this->findEdge($layout['edges'], 'paso1', 'paso2');
        $this->findEdge($layout['edges'], 'paso2', 'fin');
    }

    public function test_carril_se_resuelve_por_coincidencia_difusa_si_no_hay_exacta(): void
    {
        $pasos = [
            ['titulo' => 'Paso único', 'responsable' => 'Gerente de Unidad de Negocio'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [['id' => 'gerente', 'nombre' => 'Gerente']],
        ]), $pasos);

        $this->assertSame('gerente', $layout['nodes']['paso1']['carril_id']);
    }

    public function test_paso_marcado_como_especial_se_dibuja_como_subproceso(): void
    {
        $pasos = [
            ['titulo' => 'Paso normal', 'responsable' => 'Líder'],
            ['titulo' => 'Paso paralelo', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'pasos_especiales' => [['paso_numero' => 2, 'nota' => 'Subproceso paralelo']],
        ]), $pasos);

        $this->assertSame('subproceso', $layout['nodes']['paso2']['tipo']);
        $this->assertSame('Subproceso paralelo', $layout['nodes']['paso2']['nota']);
        $this->assertSame('subprocess', $layout['nodes']['paso2']['shape']);
    }

    public function test_decision_corta_la_cadena_por_defecto_y_arma_sus_dos_ramas(): void
    {
        $pasos = [
            ['titulo' => 'Revisar propuesta', 'responsable' => 'Líder'],
            ['titulo' => 'Aprobar', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Dentro del tabulador?',
                'tras_paso' => 1, 'destino_si' => 'paso:2', 'etiqueta_si' => 'Sí',
                'destino_no' => 'fin', 'etiqueta_no' => 'No: rechazar',
            ]],
        ]), $pasos);

        $this->findEdge($layout['edges'], 'paso1', 'd1');
        $this->findEdge($layout['edges'], 'd1', 'paso2');
        $this->findEdge($layout['edges'], 'd1', 'fin');

        $edgePairs = array_map(fn ($e) => [$e['from'], $e['to']], $layout['edges']);
        $this->assertNotContains(['paso1', 'paso2'], $edgePairs, 'La decisión debe reemplazar la cadena por defecto, no coexistir con ella.');
    }

    /**
     * Bug real encontrado con el pipeline de Mermaid anterior (2026-09): el motor de acomodo
     * ajeno dimensionaba cada carril según SU PROPIO contenido — un carril con un solo paso
     * quedaba mucho más corto que uno con varios, con anchos y separaciones entre carriles que
     * ni siquiera coincidían entre sí (medido contra Mermaid real). Aquí la uniformidad debe salir
     * por construcción: mismo ancho, misma altura, mismo arranque vertical y misma separación
     * entre TODOS los carriles, sin importar cuánto contenido tenga cada uno.
     */
    public function test_todos_los_carriles_terminan_con_el_mismo_ancho_alto_y_separacion(): void
    {
        $pasos = [
            ['titulo' => 'Paso corto', 'responsable' => 'Líder'],
            ['titulo' => 'Paso largo número dos', 'responsable' => 'Gerente'],
            ['titulo' => 'Paso largo número tres, con bastante más texto para que ocupe varias líneas de sobra', 'responsable' => 'Gerente'],
            ['titulo' => 'Paso largo número cuatro', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama(), $pasos);

        $widths = array_column($layout['lanes'], 'width');
        $heights = array_column($layout['lanes'], 'height');
        $tops = array_column($layout['lanes'], 'y');

        $this->assertCount(1, array_unique($widths), 'Todos los carriles deben tener el mismo ancho.');
        $this->assertCount(1, array_unique($heights), 'Todos los carriles deben tener la misma altura.');
        $this->assertCount(1, array_unique($tops), 'Todos los carriles deben arrancar en la misma posición vertical.');

        $xs = array_column($layout['lanes'], 'x');
        $gaps = [];
        for ($i = 1; $i < count($xs); $i++) {
            $gaps[] = $xs[$i] - $xs[$i - 1];
        }
        $this->assertCount(1, array_unique($gaps), 'La separación entre carriles consecutivos debe ser siempre la misma.');
    }

    public function test_decision_es_mas_ancha_que_un_paso_de_actividad(): void
    {
        $pasos = [['titulo' => 'Paso', 'responsable' => 'Líder']];

        $layout = $this->engine->build($this->diagrama([
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Aplica?',
                'tras_paso' => 1, 'destino_si' => 'fin', 'etiqueta_si' => 'Sí',
                'destino_no' => 'fin', 'etiqueta_no' => 'No',
            ]],
        ]), $pasos);

        $this->assertGreaterThan($layout['nodes']['paso1']['width'], $layout['nodes']['d1']['width']);
    }

    public function test_un_texto_mas_largo_produce_un_nodo_mas_alto(): void
    {
        $pasos = [
            ['titulo' => 'Corto', 'responsable' => 'Líder'],
            ['titulo' => 'Un título bastante más largo que debería necesitar más de una línea de texto para desplegarse completo', 'responsable' => 'Líder'],
        ];

        $layout = $this->engine->build($this->diagrama(['carriles' => [['id' => 'lider', 'nombre' => 'Líder']]]), $pasos);

        $this->assertGreaterThan($layout['nodes']['paso1']['height'], $layout['nodes']['paso2']['height']);
    }

    public function test_arista_a_carril_inmediato_siguiente_usa_ruteo_simple(): void
    {
        $pasos = [
            ['titulo' => 'Paso en líder', 'responsable' => 'Lider'],
            ['titulo' => 'Paso en gerente', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Lider'],
                ['id' => 'gerente', 'nombre' => 'Gerente'],
            ],
        ]), $pasos);

        $this->assertSame('simple', $this->findEdge($layout['edges'], 'paso1', 'paso2')['routing']);
    }

    public function test_arista_que_salta_mas_de_un_carril_usa_la_franja_reservada(): void
    {
        $pasos = [
            ['titulo' => 'Paso en líder', 'responsable' => 'Lider'],
            ['titulo' => 'Paso en intermedio', 'responsable' => 'Intermedio'],
            ['titulo' => 'Paso en gerente', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Lider'],
                ['id' => 'intermedio', 'nombre' => 'Intermedio'],
                ['id' => 'gerente', 'nombre' => 'Gerente'],
            ],
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Se salta la revisión intermedia?',
                'tras_paso' => 1, 'destino_si' => 'paso:3', 'etiqueta_si' => 'Sí',
                'destino_no' => 'paso:2', 'etiqueta_no' => 'No',
            ]],
        ]), $pasos);

        $this->assertSame('gutter', $this->findEdge($layout['edges'], 'd1', 'paso3')['routing']);
        $this->assertSame('simple', $this->findEdge($layout['edges'], 'd1', 'paso2')['routing']);
    }

    public function test_arista_que_regresa_a_un_carril_anterior_usa_la_franja_reservada(): void
    {
        $pasos = [
            ['titulo' => 'Paso en líder', 'responsable' => 'Lider'],
            ['titulo' => 'Paso en gerente', 'responsable' => 'Gerente'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Lider'],
                ['id' => 'gerente', 'nombre' => 'Gerente'],
            ],
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'gerente', 'texto' => '¿Aprueba?',
                'tras_paso' => 2, 'destino_si' => 'fin', 'etiqueta_si' => 'Sí',
                'destino_no' => 'paso:1', 'etiqueta_no' => 'No: regresa al líder',
            ]],
        ]), $pasos);

        $this->assertSame('gutter', $this->findEdge($layout['edges'], 'd1', 'paso1')['routing']);
    }

    /**
     * Bug real encontrado generando un documento real desde el wizard (2026-09): cuando una
     * decisión colocada al fondo del carril regresa varios pasos atrás DENTRO DEL MISMO carril
     * (ej. "No: regresa a la revisión"), su tramo comparte el mismo pasillo vertical central que
     * la cadena normal del carril — el desfase de convergencia por sí solo (unos px) no bastaba
     * para distinguirla, se veía como "rieles de tren" pegados en vez de una ruta aparte.
     */
    public function test_arista_que_brinca_varios_pasos_del_mismo_carril_se_dibuja_a_un_costado(): void
    {
        $pasos = [
            ['titulo' => 'Uno', 'responsable' => 'Lider'],
            ['titulo' => 'Dos', 'responsable' => 'Lider'],
            ['titulo' => 'Tres', 'responsable' => 'Lider'],
            ['titulo' => 'Cuatro', 'responsable' => 'Lider'],
        ];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [['id' => 'lider', 'nombre' => 'Lider']],
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Ok?',
                'tras_paso' => 4, 'destino_si' => 'fin', 'etiqueta_si' => 'Sí',
                'destino_no' => 'paso:1', 'etiqueta_no' => 'No: regresa al inicio',
            ]],
        ]), $pasos);

        // paso2->paso3 es un tramo normal de la cadena (nodos consecutivos en el carril): debe
        // seguir el pasillo central, sin desfase lateral.
        $chain = $this->findEdge($layout['edges'], 'paso2', 'paso3');
        $this->assertEqualsWithDelta($chain['points'][0]['x'], $chain['points'][1]['x'], 0.01);

        // d1->paso1 brinca paso4/fin de por medio dentro del mismo carril: debe salir por un
        // costado, no por el mismo pasillo central que usa la cadena normal.
        $loop = $this->findEdge($layout['edges'], 'd1', 'paso1');
        $centerX = $chain['points'][0]['x'];
        $this->assertGreaterThan(30.0, abs($loop['points'][0]['x'] - $centerX), 'La arista que brinca pasos del mismo carril debe separarse claramente del pasillo central.');
    }

    public function test_varias_flechas_que_convergen_en_el_mismo_nodo_se_desfasan_entre_si(): void
    {
        $pasos = [['titulo' => 'Paso', 'responsable' => 'Lider']];

        $layout = $this->engine->build($this->diagrama([
            'carriles' => [['id' => 'lider', 'nombre' => 'Lider']],
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿A?',
                'tras_paso' => 1, 'destino_si' => 'fin', 'etiqueta_si' => 'Sí',
                'destino_no' => 'fin', 'etiqueta_no' => 'No',
            ]],
        ]), $pasos);

        $toFin = array_values(array_filter($layout['edges'], fn ($e) => $e['from'] === 'd1' && $e['to'] === 'fin'));

        $this->assertCount(2, $toFin);
        $this->assertNotEquals($toFin[0]['points'], $toFin[1]['points'], 'Dos flechas que llegan al mismo nodo no deben dibujarse exactamente encimadas.');
    }

    public function test_dos_generaciones_del_mismo_diagrama_producen_exactamente_el_mismo_ruteo(): void
    {
        $pasos = [
            ['titulo' => 'Uno', 'responsable' => 'Líder'],
            ['titulo' => 'Dos', 'responsable' => 'Gerente'],
        ];
        $diagrama = $this->diagrama([
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Ok?',
                'tras_paso' => 1, 'destino_si' => 'paso:2', 'etiqueta_si' => 'Sí',
                'destino_no' => 'fin', 'etiqueta_no' => 'No',
            ]],
        ]);

        $a = $this->engine->build($diagrama, $pasos);
        $b = $this->engine->build($diagrama, $pasos);

        $this->assertSame($a['edges'], $b['edges']);
    }
}
