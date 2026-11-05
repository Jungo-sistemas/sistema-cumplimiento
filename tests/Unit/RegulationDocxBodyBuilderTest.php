<?php

namespace Tests\Unit;

use App\Services\RegulationDocxBodyBuilder;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PHPUnit\Framework\TestCase;

/**
 * Un .docx subido a mano y reconstruido en el editor trae sus imágenes con el tamaño en
 * style="width:…px;height:…px" (y puede traer varias). Al compilar el .docx nuevo deben quedar
 * todas dentro de la hoja y con su proporción original — P-MDI-053 salía con el diagrama aplastado.
 */
class RegulationDocxBodyBuilderTest extends TestCase
{
    private function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return base64_encode(ob_get_clean());
    }

    /** @return array<int, array{width: float, height: float}> */
    private function imageSizesInDocx(string $html): array
    {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection(['paperSize' => 'Letter', 'marginLeft' => 1440, 'marginRight' => 1440]);
        (new RegulationDocxBodyBuilder())->apply($section, $html);

        $tmp = tempnam(sys_get_temp_dir(), 'docx_test_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        $zip = new \ZipArchive();
        $zip->open($tmp);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        preg_match_all('/width:\s*([\d.]+)pt;\s*height:\s*([\d.]+)pt/', $xml, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => ['width' => (float) $x[1], 'height' => (float) $x[2]], $m);
    }

    public function test_imagenes_con_tamano_en_style_caben_en_la_hoja_sin_deformarse(): void
    {
        // Diagrama ancho (1200x800px) y otro alto (600x1600px), como los trae PhpWord de un Word subido.
        $html = '<p>Antes</p>'
            . '<p><img border="0" style="width: 1200px; height: 800px;" src="data:image/png;base64,' . $this->png(1200, 800) . '"/></p>'
            . '<p>En medio</p>'
            . '<p><img style="width:600px;height:1600px" src="data:image/png;base64,' . $this->png(600, 1600) . '"/></p>'
            . '<p>Después</p>';

        $sizes = $this->imageSizesInDocx($html);

        $this->assertCount(2, $sizes);
        foreach ([[1200, 800], [600, 1600]] as $i => [$w, $h]) {
            $this->assertLessThanOrEqual(468, $sizes[$i]['width']);
            $this->assertLessThanOrEqual(600, $sizes[$i]['height']);
            $this->assertEqualsWithDelta($w / $h, $sizes[$i]['width'] / $sizes[$i]['height'], 0.01);
        }
    }

    public function test_diagrama_generado_con_atributos_conserva_su_tamano(): void
    {
        // 624x416px = 468x312pt: ya cabe, no se toca.
        $html = '<p style="text-align:center"><img width="624" height="416" src="data:image/png;base64,' . $this->png(624, 416) . '"></p>';

        $this->assertSame([['width' => 468.0, 'height' => 312.0]], $this->imageSizesInDocx($html));
    }
}
