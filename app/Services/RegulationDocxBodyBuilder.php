<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Shared\Html as WordHtml;

/**
 * Vuelca el HTML del cuerpo del documento a la sección de Word, con un caso especial para la
 * imagen del diagrama de flujo (la única imagen que este sistema inserta en un .docx).
 *
 * PhpWord\Shared\Html::addHtml() SIEMPRE interpreta los atributos width/height de un <img> como
 * píxeles (Style\Image::UNIT_PX) y los escribe tal cual en el VML del .docx (style="width:624px").
 * LibreOffice — que es el motor detrás del botón "Ver" y de la vista pública (ver
 * OfficeDocumentConverter) — interpreta ese "px" asumiendo 120dpi en vez de los 96dpi estándar de
 * HTML/CSS, así que el diagrama terminaba insertándose a ~80% del tamaño físico previsto
 * (verificado insertando la misma imagen por las dos vías y comparando la matriz de
 * posicionamiento real que PDF guarda en su content stream: con "px" salía a 374pt en vez de los
 * 468pt esperados; con la unidad "pt" de PhpWord, que no depende de ninguna suposición de DPI,
 * sale exacto). La proporción (ancho/alto) nunca se distorsionaba — el problema era solo de escala.
 *
 * Por eso aquí se saca el <img> del HTML antes de mandarlo a addHtml() y se inserta aparte con
 * Section::addImage(unit: 'pt'), calculando el punto a partir del mismo width/height en px que ya
 * trae el tag (con la conversión estándar 96px = 1in = 72pt) — el resto del documento (texto,
 * tablas) sigue su camino normal por addHtml(), que para eso sí funciona bien.
 */
class RegulationDocxBodyBuilder
{
    private const IMG_PATTERN = '/<img\b[^>]*\bsrc="data:image\/(\w+);base64,([^"]+)"[^>]*\/?>/i';

    public function apply(Section $section, string $html): void
    {
        if (! preg_match(self::IMG_PATTERN, $html, $match, PREG_OFFSET_CAPTURE)) {
            WordHtml::addHtml($section, $html, false, false);

            return;
        }

        $tag = $match[0][0];
        $offset = $match[0][1];
        $extension = $match[1][0];
        $base64 = $match[2][0];

        $before = substr($html, 0, $offset);
        $after = substr($html, $offset + strlen($tag));

        if ($before !== '') {
            WordHtml::addHtml($section, $before, false, false);
        }

        $this->addImageInPt($section, $tag, $base64, $extension);

        if ($after !== '') {
            WordHtml::addHtml($section, $after, false, false);
        }
    }

    private function addImageInPt(Section $section, string $tag, string $base64, string $extension): void
    {
        $tmpPath = tempnam(sys_get_temp_dir(), 'regulation_docx_img_') . '.' . $extension;
        file_put_contents($tmpPath, base64_decode($base64));

        $style = ['unit' => 'pt'];
        $dimensions = $this->extractPtDimensions($tag);
        if ($dimensions !== null) {
            $style['width'] = $dimensions['width'];
            $style['height'] = $dimensions['height'];
        }

        $section->addImage($tmpPath, $style);

        // Element\Image solo guarda esta ruta — PhpWord lee el archivo hasta que el Writer
        // arma el .docx (IOFactory::createWriter(...)->save(), en el controlador, después de
        // que este método ya regresó), así que borrarlo aquí mismo (p. ej. en un finally)
        // rompe el documento: la referencia/relación queda en el XML pero sin el archivo
        // dentro del .docx. Se limpia al terminar la petición completa en su lugar.
        register_shutdown_function(static fn () => @unlink($tmpPath));
    }

    /** @return array{width: float, height: float}|null */
    private function extractPtDimensions(string $tag): ?array
    {
        if (! preg_match('/\bwidth="(\d+(?:\.\d+)?)"/', $tag, $w)
            || ! preg_match('/\bheight="(\d+(?:\.\d+)?)"/', $tag, $h)
        ) {
            return null;
        }

        return [
            'width' => ((float) $w[1]) * 72 / 96,
            'height' => ((float) $h[1]) * 72 / 96,
        ];
    }
}
