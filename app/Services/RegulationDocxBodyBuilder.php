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

        [$before, $after] = $this->balanceAroundImage($before, $after);

        if (trim($before) !== '') {
            WordHtml::addHtml($section, $before, false, false);
        }

        $this->addImageInPt($section, $tag, $base64, $extension);

        if (trim($after) !== '') {
            WordHtml::addHtml($section, $after, false, false);
        }
    }

    /**
     * Al cortar el HTML en el <img>, cada mitad debe ser XML válido para addHtml(). El editor deja
     * el diagrama dentro de un párrafo (<p style="text-align:center"><img></p>), y cortar ahí
     * dejaba un <p> sin cerrar en una mitad y un </p> suelto en la otra (error 500 al guardar).
     * Primero se quitan los envoltorios que solo contienen la imagen; si alguno trae más texto,
     * se cierra antes de la imagen y se vuelve a abrir después.
     *
     * @return array{0: string, 1: string}
     */
    private function balanceAroundImage(string $before, string $after): array
    {
        while (preg_match('/<([a-z][a-z0-9]*)\b[^>]*>\s*$/i', $before, $open)
            && preg_match('/^\s*<\/' . preg_quote($open[1], '/') . '\s*>/i', $after, $close)
        ) {
            $before = substr($before, 0, -strlen($open[0]));
            $after = substr($after, strlen($close[0]));
        }

        $stack = [];
        preg_match_all('/<(\/?)([a-z][a-z0-9]*)\b[^>]*?(\/?)>/i', $before, $tags, PREG_SET_ORDER);
        foreach ($tags as $t) {
            $name = strtolower($t[2]);
            if ($t[3] === '/' || in_array($name, ['br', 'hr', 'img', 'col', 'input', 'meta'], true)) {
                continue;
            }
            if ($t[1] === '/') {
                // Cierra la última apertura con ese nombre (HTML del editor, bien anidado).
                for ($i = count($stack) - 1; $i >= 0; $i--) {
                    if ($stack[$i]['name'] === $name) {
                        array_splice($stack, $i);
                        break;
                    }
                }
            } else {
                $stack[] = ['name' => $name, 'open' => $t[0]];
            }
        }

        foreach (array_reverse($stack) as $t) {
            $before .= '</' . $t['name'] . '>';
        }
        $after = implode('', array_column($stack, 'open')) . $after;

        return [$before, $after];
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
