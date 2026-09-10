<?php

namespace Tests\Unit;

use App\Services\RegulationBodyHtmlBuilder;
use App\Services\RegulationChangeDiffService;
use PHPUnit\Framework\TestCase;

/**
 * Bug real encontrado probando el flujo completo "wizard de IA -> editor de texto libre" con un
 * navegador de verdad (Playwright): RegulationBodyHtmlBuilder escribe cada título de sección como
 * texto plano dentro de un <span style="...font-weight: bold...">, sin <strong>. Pero en cuanto ese
 * documento se abre UNA SOLA VEZ en el editor TipTap (regulation-versions/edit.blade.php) y se
 * guarda — sin que el usuario toque esa sección para nada — la extensión Bold de TipTap reconoce
 * ese "font-weight: bold" y envuelve el texto en <strong> al volver a serializar el HTML. Eso
 * rompía por completo splitSections(): los 10 títulos dejaban de reconocerse desde la PRIMERA
 * edición manual, y RegulationChangeDiffService caía siempre al respaldo de "Documento completo"
 * en vez de mostrarle a quien aprueba qué sección cambió — confirmado end-to-end contra datos
 * reales antes de corregir la expresión regular.
 */
class RegulationChangeDiffServiceTest extends TestCase
{
    private RegulationChangeDiffService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RegulationChangeDiffService();
    }

    private function titleParagraph(string $title, string $inner = ''): string
    {
        return '<p style="text-align: justify; margin-top: 10pt; margin-bottom: 4pt;">'
            . '<span style="color: #1A5276; font-weight: bold; text-transform: uppercase;">'
            . $title . '</span></p>' . $inner;
    }

    public function test_reconoce_secciones_con_el_formato_original_del_wizard(): void
    {
        $html = '';
        foreach (RegulationBodyHtmlBuilder::SECTION_TITLES as $title) {
            $html .= $this->titleParagraph($title, "<p>Contenido de {$title}.</p>");
        }

        $diff = $this->service->diff($html, str_replace('Contenido de Objetivo.', 'Contenido NUEVO de Objetivo.', $html));

        $this->assertCount(1, $diff);
        $this->assertEquals('Objetivo', $diff[0]['title']);
    }

    /**
     * Reproduce exactamente la mutación real de TipTap: el título queda envuelto en <strong>
     * DENTRO del span (el span y su style se conservan intactos vía la extensión
     * PreserveInlineStyle del editor) — confirmado con una edición real de un documento generado
     * por el wizard.
     */
    public function test_reconoce_secciones_despues_de_que_tiptap_envuelve_el_titulo_en_strong(): void
    {
        $html = '';
        foreach (RegulationBodyHtmlBuilder::SECTION_TITLES as $title) {
            $html .= $this->titleParagraph("<strong>{$title}</strong>", "<p>Contenido de {$title}.</p>");
        }

        $diff = $this->service->diff($html, str_replace('Contenido de Anexos.', 'Contenido NUEVO de Anexos.', $html));

        $this->assertCount(1, $diff, 'No reconoció las secciones tras la mutación real de TipTap (<strong> anidado) — cae al respaldo de "Documento completo".');
        $this->assertEquals('Anexos', $diff[0]['title']);
    }

    public function test_mezcla_de_titulos_mutados_y_sin_mutar_se_reconoce_igual(): void
    {
        // Un edición real solo mutaría los títulos que el HTML completo trae — pero como
        // splitSections() exige reconocer al menos la mitad de los 10 títulos conocidos para no
        // caer al respaldo de "Documento completo", se incluyen suficientes secciones (algunas
        // mutadas por TipTap, otras no) para cruzar ese umbral.
        $titles = RegulationBodyHtmlBuilder::SECTION_TITLES;
        $html = '';
        foreach (array_slice($titles, 0, 6) as $i => $title) {
            $rendered = $i === 1 ? '<strong>' . $title . '</strong>' : $title;
            $html .= $this->titleParagraph($rendered, "<p>Contenido {$i}.</p>");
        }

        $newHtml = str_replace('Contenido 1.', 'Contenido 1 modificado.', $html);

        $diff = $this->service->diff($html, $newHtml);

        $this->assertCount(1, $diff);
        $this->assertEquals($titles[1], $diff[0]['title']);
    }
}
