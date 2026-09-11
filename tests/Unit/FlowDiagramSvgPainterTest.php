<?php

namespace Tests\Unit;

use App\Services\FlowDiagramSvgPainter;
use PHPUnit\Framework\TestCase;

/**
 * Corre contra un SVG real capturado de mermaid-cli una sola vez (tests/Fixtures/
 * flow_diagram_sample.svg) — no contra un fixture inventado a mano — para que la prueba de verdad
 * refleje la estructura (ids, foreignObject, namespace por defecto) que produce la versión de
 * mermaid-cli realmente instalada, no una idealización de cómo "debería" verse un SVG de Mermaid.
 */
class FlowDiagramSvgPainterTest extends TestCase
{
    private FlowDiagramSvgPainter $painter;
    private string $rawSvg;

    protected function setUp(): void
    {
        parent::setUp();
        $this->painter = new FlowDiagramSvgPainter();
        $this->rawSvg = file_get_contents(__DIR__ . '/../Fixtures/flow_diagram_sample.svg');
    }

    private function nodeMeta(): array
    {
        return [
            'inicio' => ['tipo' => 'inicio', 'carril_id' => 'gch', 'paso_numero' => null, 'nota' => null],
            'paso1' => ['tipo' => 'actividad', 'carril_id' => 'gch', 'paso_numero' => 1, 'nota' => null],
            'paso2' => ['tipo' => 'actividad', 'carril_id' => 'gch', 'paso_numero' => 2, 'nota' => null],
            'paso3' => ['tipo' => 'actividad', 'carril_id' => 'gun', 'paso_numero' => 3, 'nota' => null],
            'paso4' => ['tipo' => 'actividad', 'carril_id' => 'con', 'paso_numero' => 4, 'nota' => null],
            'paso5' => ['tipo' => 'actividad', 'carril_id' => 'nom', 'paso_numero' => 5, 'nota' => null],
            'paso6' => ['tipo' => 'subproceso', 'carril_id' => 'gch', 'paso_numero' => 6, 'nota' => 'Subproceso paralelo'],
            'fin' => ['tipo' => 'fin', 'carril_id' => 'gch', 'paso_numero' => null, 'nota' => null],
            'd1' => ['tipo' => 'decision', 'carril_id' => 'gch', 'paso_numero' => null, 'nota' => null],
            'd2' => ['tipo' => 'decision', 'carril_id' => 'con', 'paso_numero' => null, 'nota' => null],
        ];
    }

    public function test_pinta_sin_tronar_y_devuelve_un_svg_valido(): void
    {
        $painted = $this->painter->paint($this->rawSvg, $this->nodeMeta());

        $dom = new \DOMDocument();
        $this->assertTrue(@$dom->loadXML($painted) !== false, 'El SVG resultante no es XML válido.');
        $this->assertStringContainsString('<svg', $painted);
    }

    public function test_inyecta_reglas_de_color_por_tipo_de_nodo(): void
    {
        $painted = $this->painter->paint($this->rawSvg, $this->nodeMeta());

        // Actividad: blanco/borde azul. Fin: pálido rojo. Subproceso: dorado sólido.
        $this->assertStringContainsString('flowchart-paso1-', $painted);
        $this->assertMatchesRegularExpression('/flowchart-paso1-[^"]*"\]\s+rect,.*fill:\s*#FFFFFF.*stroke:\s*#5B9BD5/s', $painted);
        $this->assertMatchesRegularExpression('/flowchart-fin-[^"]*"\]\s+rect,.*fill:\s*#FDECEA.*stroke:\s*#C0392B/s', $painted);
        $this->assertMatchesRegularExpression('/flowchart-paso6-[^"]*"\]\s+rect,.*fill:\s*#D4A017/s', $painted);
    }

    public function test_inserta_insignia_numerada_para_cada_paso_de_actividad(): void
    {
        $painted = $this->painter->paint($this->rawSvg, $this->nodeMeta());

        // Un <circle> nuevo por cada nodo de actividad/subproceso (6 pasos) + los que ya traiga
        // Mermaid de fábrica (flechas, etc.) — solo se verifica que haya AL MENOS 6 nuevos.
        $circlesBefore = substr_count($this->rawSvg, '<circle');
        $circlesAfter = substr_count($painted, '<circle');
        $this->assertGreaterThanOrEqual($circlesBefore + 6, $circlesAfter);

        // La nota del subproceso debe aparecer como texto nuevo.
        $this->assertStringContainsString('Subproceso paralelo', $painted);
    }

    public function test_carriles_reciben_colores_distintos_de_encabezado(): void
    {
        $painted = $this->painter->paint($this->rawSvg, $this->nodeMeta());

        // Los 2 primeros colores de la paleta (orden de aparición: gch, gun, con, nom).
        $this->assertStringContainsString('fill:#1F5C55', $painted);
        $this->assertStringContainsString('fill:#2E74B5', $painted);
    }

    public function test_svg_sin_nodos_conocidos_no_truena(): void
    {
        $painted = $this->painter->paint($this->rawSvg, []);

        $this->assertStringContainsString('<svg', $painted);
    }
}
