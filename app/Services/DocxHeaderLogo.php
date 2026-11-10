<?php

namespace App\Services;

/**
 * Saca el logo del encabezado de un .docx (la primera imagen que trae su header).
 *
 * Al editar en el sistema un .docx subido, el encabezado se vuelve a armar con
 * RegulationDocxHeaderBuilder, que en la celda del logo solo tenía el texto "LOGO EMPRESA
 * (insertar logotipo)": el logo real del documento se perdía en la descarga y en "Ver"
 * (P-MDI-053, nov 2026). Con esto se toma del .docx de la versión que se edita y se pone en el
 * encabezado nuevo; como ese .docx ya lo lleva, la siguiente edición lo vuelve a encontrar.
 */
class DocxHeaderLogo
{
    private const EMU_PER_PT = 12700;

    // Lo que cabe en la celda del logo del encabezado (2500 twips menos márgenes ≈ 117 pt).
    private const MAX_WIDTH_PT = 110;
    private const MAX_HEIGHT_PT = 45;

    private const WORD_NS = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    /**
     * @return array{data: string, mime: string, width: float, height: float}|null  medidas en pt
     */
    public function extract(string $docxPath): ?array
    {
        $zip = new \ZipArchive();
        if (! is_file($docxPath) || $zip->open($docxPath) !== true) {
            return null;
        }

        try {
            foreach ($this->headerParts($zip) as $part) {
                $logo = $this->logoFromHeader($zip, $part);
                if ($logo !== null) {
                    return $logo;
                }
            }

            return null;
        } finally {
            $zip->close();
        }
    }

    public function dataUri(array $logo): string
    {
        return 'data:' . $logo['mime'] . ';base64,' . base64_encode($logo['data']);
    }

    /**
     * Partes header*.xml, primero las del encabezado "default" de cada sección (el que se ve en
     * todas las páginas), luego el resto (primera página, pares).
     *
     * @return array<int, string>
     */
    private function headerParts(\ZipArchive $zip): array
    {
        $rels = $this->relationships($zip, 'word/_rels/document.xml.rels');

        $default = [];
        $document = $zip->getFromName('word/document.xml');
        if ($document !== false && preg_match_all('/<w:headerReference\b[^>]*>/', $document, $refs)) {
            foreach ($refs[0] as $ref) {
                if (preg_match('/w:type="default"/', $ref) && preg_match('/r:id="([^"]+)"/', $ref, $id) && isset($rels[$id[1]])) {
                    $default[] = 'word/' . $rels[$id[1]];
                }
            }
        }

        $all = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (preg_match('#^word/header\d*\.xml$#', $name)) {
                $all[] = $name;
            }
        }
        sort($all, SORT_NATURAL);

        return array_values(array_unique([...$default, ...$all]));
    }

    private function logoFromHeader(\ZipArchive $zip, string $part): ?array
    {
        $xml = $zip->getFromName($part);
        if ($xml === false) {
            return null;
        }

        $dom = new \DOMDocument();
        if (! @$dom->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT)) {
            return null;
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('w', self::WORD_NS);
        $xpath->registerNamespace('r', self::REL_NS);
        $xpath->registerNamespace('a', 'http://schemas.openxmlformats.org/drawingml/2006/main');
        $xpath->registerNamespace('wp', 'http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing');
        $xpath->registerNamespace('v', 'urn:schemas-microsoft-com:vml');

        $rels = $this->relationships($zip, preg_replace('#^word/(.+)$#', 'word/_rels/$1.rels', $part));

        // w:drawing (Word moderno): la imagen en a:blip, el tamaño en pantalla en wp:extent.
        foreach ($xpath->query('//w:drawing') as $drawing) {
            $blip = $xpath->query('.//a:blip', $drawing)->item(0);
            $relId = $blip?->getAttributeNS(self::REL_NS, 'embed');
            $extent = $xpath->query('.//wp:extent', $drawing)->item(0);

            $logo = $this->load($zip, $rels[$relId] ?? null);
            if ($logo === null) {
                continue;
            }
            if ($extent) {
                $logo['width'] = (float) $extent->getAttribute('cx') / self::EMU_PER_PT;
                $logo['height'] = (float) $extent->getAttribute('cy') / self::EMU_PER_PT;
            }

            return $this->fit($logo);
        }

        // w:pict (VML, documentos viejos): la imagen en v:imagedata; el tamaño en el style del
        // v:shape, que no siempre trae pt — si no, se queda con la proporción de la imagen.
        foreach ($xpath->query('//v:imagedata') as $imageData) {
            $logo = $this->load($zip, $rels[$imageData->getAttributeNS(self::REL_NS, 'id')] ?? null);
            if ($logo === null) {
                continue;
            }
            $style = $imageData->parentNode instanceof \DOMElement ? $imageData->parentNode->getAttribute('style') : '';
            if (preg_match('/width:\s*([\d.]+)pt/', $style, $w) && preg_match('/height:\s*([\d.]+)pt/', $style, $h)) {
                $logo['width'] = (float) $w[1];
                $logo['height'] = (float) $h[1];
            }

            return $this->fit($logo);
        }

        return null;
    }

    /** @return array{data: string, mime: string, width: float, height: float}|null */
    private function load(\ZipArchive $zip, ?string $target): ?array
    {
        if (! $target) {
            return null;
        }

        $data = $zip->getFromName('word/' . ltrim(str_replace('\\', '/', $target), '/'));
        $size = $data !== false ? @getimagesizefromstring($data) : false;

        // Solo formatos que se ven igual en el navegador y en Word (no EMF/WMF).
        if (! $size || ! in_array($size['mime'], ['image/png', 'image/jpeg', 'image/gif'], true)) {
            return null;
        }

        // Sin tamaño en el XML: el de la imagen a 96 dpi.
        return ['data' => $data, 'mime' => $size['mime'], 'width' => $size[0] * 0.75, 'height' => $size[1] * 0.75];
    }

    private function fit(array $logo): ?array
    {
        if ($logo['width'] <= 0 || $logo['height'] <= 0) {
            return null;
        }

        $scale = min(1, self::MAX_WIDTH_PT / $logo['width'], self::MAX_HEIGHT_PT / $logo['height']);
        $logo['width'] = round($logo['width'] * $scale, 1);
        $logo['height'] = round($logo['height'] * $scale, 1);

        return $logo;
    }

    /** @return array<string, string>  Id => Target */
    private function relationships(\ZipArchive $zip, string $relsPart): array
    {
        $xml = $zip->getFromName($relsPart);
        if ($xml === false || ! preg_match_all('/<Relationship\b[^>]*>/', $xml, $tags)) {
            return [];
        }

        $rels = [];
        foreach ($tags[0] as $tag) {
            if (preg_match('/\bId="([^"]+)"/', $tag, $id) && preg_match('/\bTarget="([^"]+)"/', $tag, $target)
                && ! preg_match('/TargetMode="External"/', $tag)) {
                $rels[$id[1]] = $target[1];
            }
        }

        return $rels;
    }
}
