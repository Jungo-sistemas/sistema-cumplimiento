<?php

namespace Tests\Unit;

use App\Services\DocxImageGeometry;
use PhpOffice\PhpWord\IOFactory;
use PHPUnit\Framework\TestCase;
use Tests\Concerns\BuildsDocxWithImages;

/**
 * Un .docx subido cuya imagen Word muestra estirada o recortada debe llegar al HTML del editor con
 * esa misma forma, no con la imagen cruda (F-MDI-043: diagramas aplastados tras editar).
 */
class DocxImageGeometryTest extends TestCase
{
    use BuildsDocxWithImages;

    /** Mismo camino que RegulationVersionController::docxToHtml(). */
    private function editorHtml(string $docx): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'geom_html_');
        $this->docxTmpFiles[] = $tmp;
        IOFactory::createWriter(IOFactory::load($docx), 'HTML')->save($tmp);
        $raw = file_get_contents($tmp);
        $html = preg_match('/<body[^>]*>(.*?)<\/body>/si', $raw, $m) ? $m[1] : $raw;

        return (new DocxImageGeometry())->apply($html, $docx);
    }

    /** @return array<int, array{style: string, image: \GdImage}> */
    private function images(string $html): array
    {
        preg_match_all('/<img\b[^>]*style="([^"]*)"[^>]*src="data:image\/\w+;base64,([^"]+)"|<img\b[^>]*src="data:image\/\w+;base64,([^"]+)"[^>]*style="([^"]*)"/i', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => [
            'style' => $x[1] !== '' ? $x[1] : $x[4],
            'image' => imagecreatefromstring(base64_decode($x[2] !== '' ? $x[2] : $x[3])),
        ], $m);
    }

    private function colorAt(\GdImage $img, int $x, int $y): string
    {
        $c = imagecolorsforindex($img, imagecolorat($img, $x, $y));

        return $c['red'] > 200 && $c['blue'] < 60 ? 'rojo' : ($c['blue'] > 200 && $c['red'] < 60 ? 'azul' : 'otro');
    }

    public function test_imagen_estirada_en_word_conserva_esa_proporcion(): void
    {
        // Imagen 400×200 (2:1) que en Word se muestra cuadrada, 200×200.
        [$img] = $this->images($this->editorHtml($this->docx($this->drawing('rIdImg', 200, 200))));

        $this->assertStringContainsString('width: 200px; height: 200px;', $img['style']);
        $this->assertEqualsWithDelta(1.0, imagesx($img['image']) / imagesy($img['image']), 0.01);
        $this->assertSame('rojo', $this->colorAt($img['image'], 5, 5));
        $this->assertSame('azul', $this->colorAt($img['image'], imagesx($img['image']) - 5, 5));
    }

    public function test_imagen_recortada_en_word_solo_muestra_la_parte_visible(): void
    {
        // Recorte del 50% derecho: en Word solo se ve la mitad roja, a 150×150.
        [$img] = $this->images($this->editorHtml($this->docx(
            $this->drawing('rIdImg', 150, 150, '<a:srcRect r="50000"/>')
        )));

        $this->assertStringContainsString('width: 150px; height: 150px;', $img['style']);
        $this->assertSame('rojo', $this->colorAt($img['image'], 5, 5));
        $this->assertSame('rojo', $this->colorAt($img['image'], imagesx($img['image']) - 3, imagesy($img['image']) - 3));
    }

    public function test_imagen_vml_estirada_y_varias_imagenes_en_orden(): void
    {
        $html = $this->editorHtml($this->docx(
            $this->vml('rIdImg', 'width:225pt;height:75pt', 'cropleft="32768f"')   // mitad derecha, 300×100 px
            . $this->drawing('rIdImg', 400, 200)                                     // sin cambios
        ));

        [$vml, $plain] = $this->images($html);

        $this->assertStringContainsString('width: 300px; height: 100px;', $vml['style']);
        $this->assertEqualsWithDelta(3.0, imagesx($vml['image']) / imagesy($vml['image']), 0.02);
        $this->assertSame('azul', $this->colorAt($vml['image'], 5, 5));

        $this->assertStringContainsString('width: 400px; height: 200px;', $plain['style']);
        $this->assertSame([400, 200], [imagesx($plain['image']), imagesy($plain['image'])]);
    }

    public function test_sin_imagenes_o_conteo_distinto_no_toca_el_html(): void
    {
        $html = '<p>Hola</p><img src="data:image/png;base64,' . base64_encode($this->twoColorPng()) . '" style="width: 400px; height: 200px;" />';

        // El .docx no trae ninguna imagen que emparejar.
        $this->assertSame($html, (new DocxImageGeometry())->apply($html, $this->docx('')));
    }
}
