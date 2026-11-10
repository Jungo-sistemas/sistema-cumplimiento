<?php

namespace Tests\Unit;

use App\Services\DocxHeaderLogo;
use App\Services\RegulationDocxHeaderBuilder;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\BuildsDocxWithImages;

/**
 * El logo del encabezado de un .docx subido debe pasar al encabezado que arma el sistema al
 * editarlo (P-MDI-053: salía "LOGO EMPRESA (insertar logotipo)"), y de ahí a la siguiente edición.
 */
class DocxHeaderLogoTest extends TestCase
{
    use BuildsDocxWithImages;

    private function compile(?array $logo): string
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        (new RegulationDocxHeaderBuilder())->apply($section, [
            'nombre' => 'DISCIPLINA OPERATIVA', 'codigo' => 'P-MDI-053', 'version' => '02',
            'quien_elabora' => null, 'quien_aprueba' => null, 'fecha_vigencia' => null, 'logo' => $logo,
        ]);
        $section->addText('Cuerpo');

        $tmp = tempnam(sys_get_temp_dir(), 'header_logo_');
        $this->docxTmpFiles[] = $tmp;
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        (new RegulationDocxHeaderBuilder())->finishFile($tmp);

        return $tmp;
    }

    private function headerXml(string $docx): string
    {
        $zip = new \ZipArchive();
        $zip->open($docx);
        $xml = $zip->getFromName('word/header1.xml');
        $zip->close();

        return $xml;
    }

    public function test_el_logo_pasa_al_encabezado_generado_y_se_vuelve_a_encontrar(): void
    {
        $png = $this->twoColorPng();
        $logo = ['data' => $png, 'mime' => 'image/png', 'width' => 98.5, 'height' => 22.5];

        $docx = $this->compile($logo);
        $xml = $this->headerXml($docx);

        // DrawingML, no VML: LibreOffice tapa la imagen VML con el fondo azul de la celda.
        $this->assertStringContainsString('<w:drawing>', $xml);
        $this->assertStringNotContainsString('<w:pict>', $xml);
        $this->assertStringNotContainsString('LOGO EMPRESA', $xml);

        $again = (new DocxHeaderLogo())->extract($docx);
        $this->assertNotNull($again);
        $this->assertSame($png, $again['data']);
        $this->assertEqualsWithDelta(98.5, $again['width'], 0.1);
        $this->assertEqualsWithDelta(22.5, $again['height'], 0.1);
    }

    public function test_sin_logo_queda_el_texto_de_siempre(): void
    {
        $docx = $this->compile(null);

        $this->assertStringContainsString('LOGO EMPRESA', $this->headerXml($docx));
        $this->assertNull((new DocxHeaderLogo())->extract($docx));
    }

    public function test_un_logo_grande_se_ajusta_a_la_celda_sin_deformarse(): void
    {
        $docx = $this->compile(['data' => $this->twoColorPng(), 'mime' => 'image/png', 'width' => 400, 'height' => 100]);

        $logo = (new DocxHeaderLogo())->extract($docx);

        $this->assertLessThanOrEqual(110, $logo['width']);
        $this->assertEqualsWithDelta(4.0, $logo['width'] / $logo['height'], 0.05);
    }
}
