<?php

namespace Tests\Unit;

use App\Services\FlowDiagramMermaidBuilder;
use PHPUnit\Framework\TestCase;

class FlowDiagramMermaidBuilderTest extends TestCase
{
    private FlowDiagramMermaidBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new FlowDiagramMermaidBuilder();
    }

    private function pasos(): array
    {
        return [
            ['titulo' => 'Recepción de la solicitud', 'responsable' => 'Líder'],
            ['titulo' => 'Validación de datos', 'responsable' => 'Jefe'],
            ['titulo' => 'Gestión de cambios de puesto', 'responsable' => 'Líder'],
        ];
    }

    public function test_flujo_simple_sin_decisiones_conecta_todo_en_secuencia(): void
    {
        $diagrama = [
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Líder'],
                ['id' => 'jefe', 'nombre' => 'Jefe'],
            ],
            'decisiones' => [],
            'pasos_especiales' => [],
        ];

        $result = $this->builder->build($diagrama, $this->pasos());

        $this->assertStringContainsString('flowchart LR', $result['mermaid']);
        $this->assertStringContainsString('inicio --> paso1', $result['mermaid']);
        $this->assertStringContainsString('paso1 --> paso2', $result['mermaid']);
        $this->assertStringContainsString('paso2 --> paso3', $result['mermaid']);
        $this->assertStringContainsString('paso3 --> fin', $result['mermaid']);

        $this->assertEquals('actividad', $result['nodeMeta']['paso1']['tipo']);
        $this->assertEquals('lider', $result['nodeMeta']['paso1']['carril_id']);
        $this->assertEquals('jefe', $result['nodeMeta']['paso2']['carril_id']);
        $this->assertEquals(1, $result['nodeMeta']['paso1']['paso_numero']);
        $this->assertEquals('inicio', $result['nodeMeta']['inicio']['tipo']);
        $this->assertEquals('fin', $result['nodeMeta']['fin']['tipo']);
    }

    public function test_paso_marcado_especial_se_dibuja_como_subrutina_y_queda_en_nodemeta(): void
    {
        $diagrama = [
            'carriles' => [['id' => 'lider', 'nombre' => 'Líder']],
            'decisiones' => [],
            'pasos_especiales' => [['paso_numero' => 3, 'nota' => 'Subproceso paralelo al ciclo anual']],
        ];

        $result = $this->builder->build($diagrama, $this->pasos());

        $this->assertStringContainsString('paso3[[', $result['mermaid']);
        $this->assertEquals('subproceso', $result['nodeMeta']['paso3']['tipo']);
        $this->assertEquals('Subproceso paralelo al ciclo anual', $result['nodeMeta']['paso3']['nota']);
    }

    public function test_decision_corta_la_secuencia_por_defecto_y_arma_sus_dos_ramas(): void
    {
        $diagrama = [
            'carriles' => [
                ['id' => 'lider', 'nombre' => 'Líder'],
                ['id' => 'jefe', 'nombre' => 'Jefe'],
            ],
            'decisiones' => [[
                'id' => 'd1',
                'carril_id' => 'lider',
                'texto' => '¿Propuesta dentro del tabulador?',
                'tras_paso' => 1,
                'destino_si' => 'paso:2',
                'etiqueta_si' => 'Sí',
                'destino_no' => 'fin',
                'etiqueta_no' => 'No: corregir',
            ]],
            'pasos_especiales' => [],
        ];

        $result = $this->builder->build($diagrama, $this->pasos());
        $mermaid = $result['mermaid'];

        // La cadena por defecto paso1 -> paso2 ya no debe existir tal cual: paso1 ahora va a la decisión.
        $this->assertStringNotContainsString('paso1 --> paso2', $mermaid);
        $this->assertStringContainsString('paso1 --> d1', $mermaid);
        $this->assertStringContainsString('d1 -->|Sí| paso2', $mermaid);
        $this->assertStringContainsString('d1 -->|No: corregir| fin', $mermaid);
        $this->assertEquals('decision', $result['nodeMeta']['d1']['tipo']);
    }

    public function test_responsable_sin_coincidencia_exacta_cae_al_primer_carril_sin_tronar(): void
    {
        $diagrama = [
            'carriles' => [['id' => 'lider', 'nombre' => 'Líder de Proceso']],
            'decisiones' => [],
            'pasos_especiales' => [],
        ];

        $pasos = [['titulo' => 'Paso único', 'responsable' => 'Alguien que no coincide con ningún carril']];

        $result = $this->builder->build($diagrama, $pasos);

        $this->assertEquals('lider', $result['nodeMeta']['paso1']['carril_id']);
    }
}
