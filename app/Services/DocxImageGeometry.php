<?php

namespace App\Services;

/**
 * Aplica a las imágenes del HTML reconstruido de un .docx el tamaño y el recorte con que Word las
 * muestra.
 *
 * El lector de PhpWord (Reader\Word2007\AbstractPart) solo toma el archivo de la imagen: ignora el
 * tamaño en pantalla (wp:extent / style del v:shape) y el recorte (a:srcRect / croptop…), así que
 * cada imagen salía a su resolución nativa y completa. Si en Word estaba estirada o recortada, en
 * el editor, "Ver" y la descarga se veía deformada (F-MDI-043, nov 2026: diagramas de 2550×3120 px
 * que en Word se mostraban con otra proporción).
 *
 * Aquí se lee esa geometría del document.xml y se "hornea" en la imagen: se recorta y se reescala
 * a la proporción de pantalla, y su width/height en el style queda en px (96 dpi) del tamaño en
 * Word. Así la proporción natural de la imagen ya es la correcta para cualquier vista.
 */
class DocxImageGeometry
{
    private const EMU_PER_PX = 9525;

    // Lado mayor máximo de la imagen reescalada: suficiente para imprimir nítido a página completa
    // sin inflar el HTML (las imágenes van en base64 dentro de body_html).
    private const MAX_SIDE_PX = 2400;

    public function apply(string $html, string $docxPath): string
    {
        if (! function_exists('imagecreatefromstring')) {
            return $html;
        }

        $geometries = $this->readGeometries($docxPath);

        if (! preg_match_all('/<img\b[^>]*>/i', $html, $tags, PREG_OFFSET_CAPTURE)) {
            return $html;
        }

        // Se emparejan por orden de aparición. Si el conteo no cuadra (una imagen que PhpWord no
        // leyó, p. ej. dentro de un cuadro de texto), emparejar sería adivinar: se deja igual.
        if (count($geometries) !== count($tags[0])) {
            return $html;
        }

        foreach (array_reverse($tags[0], true) as $i => [$tag, $offset]) {
            $fixed = $this->fixTag($tag, $geometries[$i]);
            if ($fixed !== null) {
                $html = substr_replace($html, $fixed, $offset, strlen($tag));
            }
        }

        return $html;
    }

    /**
     * Geometría de cada imagen que lee PhpWord, en el mismo orden: w:drawing con pic:pic (inline
     * o anclada) y w:pict con v:imagedata.
     *
     * @return array<int, array{width: float, height: float, crop: array{l: float, t: float, r: float, b: float}}|null>
     */
    public function readGeometries(string $docxPath): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return [];
        }
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($xml === false) {
            return [];
        }

        $dom = new \DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        foreach ([
            'w'   => 'http://schemas.openxmlformats.org/wordprocessingml/2006/main',
            'wp'  => 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing',
            'a'   => 'http://schemas.openxmlformats.org/drawingml/2006/main',
            'pic' => 'http://schemas.openxmlformats.org/drawingml/2006/picture',
            'v'   => 'urn:schemas-microsoft-com:vml',
            'mc'  => 'http://schemas.openxmlformats.org/markup-compatibility/2006',
        ] as $prefix => $uri) {
            $xpath->registerNamespace($prefix, $uri);
        }

        $geometries = [];

        // Fuera de mc:Fallback: PhpWord lee el contenido de mc:Choice, no la copia alternativa.
        foreach ($xpath->query('//w:body//w:drawing[not(ancestor::mc:Fallback)] | //w:body//w:pict[not(ancestor::mc:Fallback)]') as $node) {
            if ($node->localName === 'drawing') {
                $holder = $xpath->query('wp:inline | wp:anchor', $node)->item(0);
                if (! $holder || ! $xpath->query('a:graphic/a:graphicData/pic:pic/pic:blipFill/a:blip', $holder)->length) {
                    continue;
                }
                $geometries[] = $this->drawingGeometry($xpath, $holder);
            } else {
                $shape = $xpath->query('v:shape[v:imagedata]', $node)->item(0);
                if (! $shape) {
                    continue;
                }
                $geometries[] = $this->vmlGeometry($shape, $xpath->query('v:imagedata', $shape)->item(0));
            }
        }

        return $geometries;
    }

    private function drawingGeometry(\DOMXPath $xpath, \DOMElement $holder): ?array
    {
        $extent = $xpath->query('wp:extent', $holder)->item(0);
        if (! $extent) {
            return null;
        }

        $crop = ['l' => 0.0, 't' => 0.0, 'r' => 0.0, 'b' => 0.0];
        $srcRect = $xpath->query('a:graphic/a:graphicData/pic:pic/pic:blipFill/a:srcRect', $holder)->item(0);
        if ($srcRect) {
            // a:srcRect viene en milésimas de porcentaje (25000 = 25%).
            foreach ($crop as $side => $_) {
                $crop[$side] = ((float) $srcRect->getAttribute($side)) / 100000;
            }
        }

        return [
            'width'  => (float) $extent->getAttribute('cx') / self::EMU_PER_PX,
            'height' => (float) $extent->getAttribute('cy') / self::EMU_PER_PX,
            'crop'   => $crop,
        ];
    }

    private function vmlGeometry(\DOMElement $shape, \DOMElement $imageData): ?array
    {
        $style = $shape->getAttribute('style');
        $width = $this->cssToPx($style, 'width');
        $height = $this->cssToPx($style, 'height');
        if (! $width || ! $height) {
            return null;
        }

        $crop = [];
        foreach (['l' => 'cropleft', 't' => 'croptop', 'r' => 'cropright', 'b' => 'cropbottom'] as $side => $attr) {
            $crop[$side] = $this->vmlFraction($imageData->getAttribute($attr));
        }

        return ['width' => $width, 'height' => $height, 'crop' => $crop];
    }

    private function cssToPx(string $css, string $property): ?float
    {
        if (! preg_match('/(?:^|;)\s*' . $property . '\s*:\s*(-?\d+(?:\.\d+)?)\s*(pt|px|in|cm|mm)?/i', $css, $m)) {
            return null;
        }

        $factor = ['pt' => 96 / 72, 'px' => 1, 'in' => 96, 'cm' => 96 / 2.54, 'mm' => 96 / 25.4][strtolower($m[2] ?? 'px')];

        return (float) $m[1] * $factor;
    }

    /** VML expresa el recorte como fracción: "12345f" (sobre 65536) o un decimal ("0.25"). */
    private function vmlFraction(string $value): float
    {
        if ($value === '') {
            return 0.0;
        }

        return str_ends_with(strtolower($value), 'f')
            ? (float) substr($value, 0, -1) / 65536
            : (float) $value;
    }

    private function fixTag(string $tag, ?array $geometry): ?string
    {
        if ($geometry === null || $geometry['width'] <= 0 || $geometry['height'] <= 0
            || ! preg_match('/\bsrc="data:image\/(\w+);base64,([^"]+)"/i', $tag, $src)
        ) {
            return null;
        }

        $image = @imagecreatefromstring(base64_decode($src[2]));
        if (! $image) {
            return null;
        }

        $srcW = imagesx($image);
        $srcH = imagesy($image);
        $crop = $geometry['crop'];
        $cropX = (int) round($srcW * max(0, $crop['l']));
        $cropY = (int) round($srcH * max(0, $crop['t']));
        $cropW = (int) round($srcW * (1 - max(0, $crop['l']) - max(0, $crop['r'])));
        $cropH = (int) round($srcH * (1 - max(0, $crop['t']) - max(0, $crop['b'])));

        if ($cropW <= 0 || $cropH <= 0) {
            imagedestroy($image);

            return null;
        }

        $displayRatio = $geometry['width'] / $geometry['height'];
        $sameShape = $cropX === 0 && $cropY === 0 && $cropW === $srcW && $cropH === $srcH
            && abs($displayRatio - $srcW / $srcH) < 0.01;

        $style = sprintf('width: %dpx; height: %dpx;', round($geometry['width']), round($geometry['height']));

        // Ni recortada ni estirada: solo se corrige el tamaño, sin volver a codificar la imagen.
        if ($sameShape) {
            imagedestroy($image);

            return $this->replaceStyle($tag, $style);
        }

        // Se conserva la resolución del recorte en su lado mayor y el otro sigue la proporción de Word.
        $outW = $cropW;
        $outH = (int) round($cropW / $displayRatio);
        $scale = min(1, self::MAX_SIDE_PX / max($outW, $outH));
        $outW = max(1, (int) round($outW * $scale));
        $outH = max(1, (int) round($outH * $scale));

        $out = imagecreatetruecolor($outW, $outH);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagecopyresampled($out, $image, 0, 0, $cropX, $cropY, $outW, $outH, $cropW, $cropH);
        imagedestroy($image);

        ob_start();
        $isJpeg = in_array(strtolower($src[1]), ['jpeg', 'jpg'], true);
        $isJpeg ? imagejpeg($out, null, 90) : imagepng($out, null, 6);
        $data = base64_encode(ob_get_clean());
        imagedestroy($out);

        $newSrc = 'src="data:image/' . ($isJpeg ? 'jpeg' : 'png') . ';base64,' . $data . '"';
        $tag = str_replace($src[0], $newSrc, $tag);

        return $this->replaceStyle($tag, $style);
    }

    private function replaceStyle(string $tag, string $sizeStyle): string
    {
        if (! preg_match('/\bstyle="([^"]*)"/i', $tag, $m)) {
            return preg_replace('/^<img\b/i', '<img style="' . $sizeStyle . '"', $tag);
        }

        $rest = trim(preg_replace('/(?:^|;)\s*(?:width|height)\s*:[^;]*/i', '', $m[1]), "; \t");
        $style = $sizeStyle . ($rest !== '' ? ' ' . $rest . ';' : '');

        return str_replace($m[0], 'style="' . $style . '"', $tag);
    }
}
