<?php

namespace Tests\Unit;

use App\Services\FlowDiagramLayoutEngine;
use App\Services\FlowDiagramSvgRenderer;
use PHPUnit\Framework\TestCase;

class FlowDiagramSvgRendererTest extends TestCase
{
    private FlowDiagramLayoutEngine $engine;
    private FlowDiagramSvgRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FlowDiagramLayoutEngine();
        $this->renderer = new FlowDiagramSvgRenderer();
    }

    private function sampleLayout(): array
    {
        $diagrama = [
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Líder'],
                ['id' => 'gerente', 'nombre' => 'Gerente'],
            ],
            'decisiones' => [[
                'id' => 'd1', 'carril_id' => 'lider', 'texto' => '¿Aprueba?',
                'tras_paso' => 1, 'destino_si' => 'paso:2', 'etiqueta_si' => 'Sí',
                'destino_no' => 'fin', 'etiqueta_no' => 'No: rechazar',
            ]],
            'pasos_especiales' => [['paso_numero' => 2, 'nota' => 'Subproceso paralelo']],
        ];
        $pasos = [
            ['titulo' => 'Revisar solicitud', 'responsable' => 'Líder'],
            ['titulo' => 'Ejecutar en paralelo', 'responsable' => 'Gerente'],
        ];

        return $this->engine->build($diagrama, $pasos);
    }

    public function test_produce_un_svg_valido_con_las_dimensiones_del_canvas(): void
    {
        $layout = $this->sampleLayout();
        $svg = $this->renderer->render($layout);

        $dom = new \DOMDocument();
        $this->assertNotFalse($dom->loadXML($svg), 'El SVG generado no es XML válido.');
        $this->assertStringContainsString('width="' . $this->fmt($layout['canvas']['width']) . '"', $svg);
        $this->assertStringContainsString('height="' . $this->fmt($layout['canvas']['height']) . '"', $svg);
    }

    public function test_dibuja_un_rect_de_encabezado_por_carril_con_colores_distintos(): void
    {
        $svg = $this->renderer->render($this->sampleLayout());

        $this->assertStringContainsString('#1F5C55', $svg);
        $this->assertStringContainsString('#2E74B5', $svg);
    }

    public function test_dibuja_insignia_numerada_para_cada_paso_de_actividad_o_subproceso(): void
    {
        $svg = $this->renderer->render($this->sampleLayout());

        $dom = new \DOMDocument();
        $dom->loadXML($svg);

        // 2 pasos (paso1 actividad, paso2 subproceso) -> 2 insignias, ni una más ni una menos.
        $this->assertSame(2, $dom->getElementsByTagName('circle')->length);
        $this->assertStringContainsString('Subproceso paralelo', $svg);
    }

    public function test_nodo_de_decision_se_dibuja_como_poligono_en_forma_de_rombo(): void
    {
        $svg = $this->renderer->render($this->sampleLayout());

        $dom = new \DOMDocument();
        $dom->loadXML($svg);

        $this->assertSame(1, $dom->getElementsByTagName('polygon')->length);
    }

    public function test_nodo_de_subproceso_dibuja_dos_lineas_verticales_internas(): void
    {
        $svg = $this->renderer->render($this->sampleLayout());

        $dom = new \DOMDocument();
        $dom->loadXML($svg);

        $this->assertSame(2, $dom->getElementsByTagName('line')->length);
    }

    public function test_flecha_con_etiqueta_dibuja_una_placa_de_fondo_con_el_texto(): void
    {
        $svg = $this->renderer->render($this->sampleLayout());

        $this->assertStringContainsString('rechazar', $svg);
    }

    public function test_svg_sin_pasos_no_truena(): void
    {
        $layout = $this->engine->build([
            'carriles' => [['id' => 'l1', 'nombre' => 'Solo']],
            'decisiones' => [],
            'pasos_especiales' => [],
        ], []);

        $svg = $this->renderer->render($layout);

        $this->assertStringContainsString('<svg', $svg);
    }

    private function fmt(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
