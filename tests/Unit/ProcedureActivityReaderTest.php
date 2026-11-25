<?php

namespace Tests\Unit;

use App\Services\ProcedureActivityReader;
use PHPUnit\Framework\TestCase;

/**
 * Lector de pasos de los procedimientos P subidos en Word o PDF. Los textos son inventados: los
 * procedimientos reales son confidenciales y no se suben al repositorio.
 */
class ProcedureActivityReaderTest extends TestCase
{
    public function test_lee_cada_paso_con_sus_campos_sin_encabezados_ni_pies(): void
    {
        $pages = [
            "VIGÍA · Procedimiento de prueba\nCódigo: P-TST-001\n"
            . "1. Objetivo\nDescribir la recepción.\n"
            . "Descripción del proceso / Actividades\n"
            . "Paso 1. Recepción del carrotanque\n"
            . "Responsable: Jefe de patio\n"
            . "Quién: Operador de patio.\n"
            . "Qué: Recibe el carrotanque\ny revisa los sellos.\n"
            . "Cómo:\n1. Verifica el número del carro.\n2.Revisa los sellos\ncontra la guía.\n"
            . "Página 1 de 2",
            "VIGÍA · Procedimiento de prueba\nCódigo: P-TST-001\n"
            . "Cuándo: Al llegar el tren.\n"
            . "Dónde: Patio 3.\n"
            . "Evidencia requerida: Formato F-\nALC-005 firmado.\n"
            . "Paso 2. Descarga\n"
            . "Responsable: Operador\n"
            . "Qué: Descarga el producto.\n"
            . "Resultado / Entregable\nCarrotanque descargado.\n"
            . "Página 2 de 2",
        ];

        $steps = ProcedureActivityReader::steps($pages);

        $this->assertCount(2, $steps);
        $this->assertSame([
            'numero' => 1, 'titulo' => 'Recepción del carrotanque', 'responsable' => 'Jefe de patio',
            'quien' => 'Operador de patio.', 'que' => 'Recibe el carrotanque y revisa los sellos.',
            'como' => ['1. Verifica el número del carro.', '2. Revisa los sellos contra la guía.'],
            'cuando' => 'Al llegar el tren.', 'donde' => 'Patio 3.', 'evidencia' => 'Formato F-ALC-005 firmado.', 'otros' => [],
        ], $steps[0]);
        $this->assertSame([2, 'Descarga', 'Operador', 'Descarga el producto.'], [$steps[1]['numero'], $steps[1]['titulo'], $steps[1]['responsable'], $steps[1]['que']]);
        $this->assertSame('', $steps[1]['evidencia']); // lo de "Resultado / Entregable" ya no es del paso
    }

    public function test_plantilla_anterior_y_etiquetas_propias_del_paso(): void
    {
        $steps = ProcedureActivityReader::steps(["Descripción del proceso / Actividades\n"
            . "1. Revisión de documentos\n"
            . "Responsable: Analista fiscal\n"
            . "Qué: Revisa que la factura esté completa.\n"
            . "Cómo — Preparación: Junta la factura y el pedido.\n"
            . "Cómo — Revisión: Compara los montos.\n"
            . "Riesgo de Seguridad y Sanciones: Multa del SAT\nsi no se corrige.\n"
            . "2. Archivo\n"
            . "Responsable: Auxiliar\n"
            . "Evidencia: Expediente digital.\n"]);

        $this->assertCount(2, $steps);
        $this->assertSame([1, 'Revisión de documentos'], [$steps[0]['numero'], $steps[0]['titulo']]);
        $this->assertSame(['Preparación: Junta la factura y el pedido.', 'Revisión: Compara los montos.'], $steps[0]['como']);
        $this->assertSame([['etiqueta' => 'Riesgo de Seguridad y Sanciones', 'texto' => 'Multa del SAT si no se corrige.']], $steps[0]['otros']);
        $this->assertSame([2, 'Archivo', 'Expediente digital.'], [$steps[1]['numero'], $steps[1]['titulo'], $steps[1]['evidencia']]);
    }

    public function test_omite_la_funcion_transversal_sin_pegarla_al_paso_anterior(): void
    {
        $steps = ProcedureActivityReader::steps(["Descripción del proceso / Actividades\n"
            . "Paso 1. Atención al cliente\n"
            . "Responsable: Ejecutivo de cuenta\n"
            . "Qué: Atiende la solicitud.\n"
            . "Evidencia requerida: Correo con la\nrespuesta al cliente.\n"
            . "Función Transversal — Calidad (supervisión durante toda la\njornada)\n"
            . "Responsable: Gerente de calidad\n"
            . "Qué: Revisa todos los pasos.\n"
            . "Paso 2. Seguimiento\n"
            . "Responsable: Ejecutivo de cuenta\n"
            . "Qué: Da seguimiento al pago.\n"]);

        $this->assertSame([1, 2], array_column($steps, 'numero'));
        $this->assertSame('Correo con la respuesta al cliente.', $steps[0]['evidencia']);
        $this->assertSame('Da seguimiento al pago.', $steps[1]['que']);
    }

    public function test_sin_pasos_no_regresa_nada(): void
    {
        $this->assertSame([], ProcedureActivityReader::steps([]));
        $this->assertSame([], ProcedureActivityReader::steps(["Objetivo\n1. Alcance\nAplica a todo el personal.\n"]));
    }
}
