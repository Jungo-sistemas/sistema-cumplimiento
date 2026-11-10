<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;

/**
 * Encabezado fijo (tabla de 4 columnas x 2 filas, con el formato exacto ya aprobado por el
 * cliente) que debe aparecer idéntico en TODOS los documentos de Procesos, en todas las
 * páginas — nombre, código, versión, elaboró, aprobó, fecha de efectividad y número de página
 * automático.
 *
 * A propósito NO se le pide esto a la IA en cada generación: por más detalladas que sean las
 * instrucciones, no hay garantía de que el modelo replique exactamente el mismo diseño dos
 * veces (colores, bordes, estructura de tabla), y cualquier variación rompe el formato ya
 * establecido con el cliente. Construirlo aquí, con los datos reales del documento, lo deja
 * 100% consistente siempre — y como se agrega vía `Section::addHeader()`, Word lo repite
 * nativamente en cada página sin que el HTML del cuerpo tenga que mencionarlo.
 */
class RegulationDocxHeaderBuilder
{
    // Colores fijados según la especificación exacta documentada en
    // resources/ai-reference/texto_validacion.md y documento_condiciones.docx: fila 1 azul
    // marino con texto blanco, fila 2 gris claro con etiquetas en azul marino, bordes #1A3A5C.
    private const NAVY_BG = '002060';
    private const GRAY_BG = 'F2F3F4';
    private const NAVY_TEXT = '002060';
    private const BORDER = '1A3A5C';

    /**
     * `logo` (opcional) es el de DocxHeaderLogo::extract(); sin él la celda queda con el texto
     * "LOGO EMPRESA (insertar logotipo)".
     *
     * @param  array{nombre: string, codigo: ?string, version: int|string, quien_elabora: ?string, quien_aprueba: ?string, fecha_vigencia: ?string, logo?: ?array}  $meta
     */
    public function apply(Section $section, array $meta): void
    {
        $header = $section->addHeader();

        $table = $header->addTable([
            'borderColor' => self::BORDER,
            
            'borderSize'  => 4,
            'unit'        => 'pct',
            'width'       => 100 * 50,
            'cellMargin'  => 80,
        ]);

        // Fila 1: logo / nombre del procedimiento / código / versión — fondo azul marino, texto blanco
        $table->addRow();
        $this->logoCell($table, $meta['logo'] ?? null);
        $this->cell($table, self::NAVY_BG, 'FFFFFF', 'FFFFFF', 'PROCEDIMIENTO', $meta['nombre'] ?? '', italicValue: true);
        $this->cell($table, self::NAVY_BG, 'FFFFFF', 'FFFFFF', 'CÓDIGO', $meta['codigo'] ?? '—');
        $this->cell($table, self::NAVY_BG, 'FFFFFF', 'FFFFFF', 'VERSIÓN', (string) ($meta['version'] ?? '01'));

        // Fila 2: elaboró / aprobó / vigencia / página — fondo gris claro, etiquetas azul marino, valores negros
        $table->addRow();
        $this->cell($table, self::GRAY_BG, self::NAVY_TEXT, '000000', 'ELABORADO POR:', $meta['quien_elabora'] ?? '—');
        $this->cell($table, self::GRAY_BG, self::NAVY_TEXT, '000000', 'APROBADO POR:', $meta['quien_aprueba'] ?? '—');
        $this->cell($table, self::GRAY_BG, self::NAVY_TEXT, '000000', 'Fecha de elaboración:', $this->formatFecha($meta['fecha_vigencia'] ?? null));
        $this->pageNumberCell($table);
    }

    /**
     * Se llama sobre el .docx ya guardado. PhpWord escribe las imágenes como VML (w:pict), y
     * LibreOffice pinta el fondo de la celda encima de una imagen VML dentro de una tabla del
     * encabezado: en el PDF la celda del logo salía azul y vacía. Aquí las imágenes del encabezado
     * se reescriben como DrawingML (w:drawing), que es como las guarda Word y sí se ven.
     */
    public function finishFile(string $docxPath): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($docxPath) !== true) {
            return;
        }

        $docPrId = 9000;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (! preg_match('#^word/header\d*\.xml$#', $name)) {
                continue;
            }

            $xml = $zip->getFromName($name);
            $fixed = preg_replace_callback(
                '#<w:pict><v:shape\b[^>]*style="[^"]*width:\s*([\d.]+)pt;\s*height:\s*([\d.]+)pt;[^"]*"[^>]*>.*?<v:imagedata r:id="([^"]+)"[^>]*/>.*?</v:shape></w:pict>#s',
                fn ($m) => $this->drawingXml((float) $m[1], (float) $m[2], $m[3], $docPrId++),
                $xml
            );

            if ($fixed !== null && $fixed !== $xml) {
                $zip->addFromString($name, $fixed);
            }
        }

        $zip->close();
    }

    private function drawingXml(float $widthPt, float $heightPt, string $relId, int $docPrId): string
    {
        $cx = (int) round($widthPt * 12700);
        $cy = (int) round($heightPt * 12700);

        return '<w:drawing><wp:inline distT="0" distB="0" distL="0" distR="0">'
            . "<wp:extent cx=\"{$cx}\" cy=\"{$cy}\"/><wp:docPr id=\"{$docPrId}\" name=\"Logo\"/>"
            . '<a:graphic xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main">'
            . '<a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:pic xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">'
            . '<pic:nvPicPr><pic:cNvPr id="0" name="Logo"/><pic:cNvPicPr/></pic:nvPicPr>'
            . "<pic:blipFill><a:blip r:embed=\"{$relId}\"/><a:stretch><a:fillRect/></a:stretch></pic:blipFill>"
            . "<pic:spPr><a:xfrm><a:off x=\"0\" y=\"0\"/><a:ext cx=\"{$cx}\" cy=\"{$cy}\"/></a:xfrm>"
            . '<a:prstGeom prst="rect"><a:avLst/></a:prstGeom></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing>';
    }

    private function logoCell($table, ?array $logo): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::NAVY_BG, 'valign' => 'center']);

        if ($logo) {
            $cell->addImage($logo['data'], [
                'width'     => $logo['width'],
                'height'    => $logo['height'],
                'unit'      => 'pt',
                'alignment' => 'center',
            ]);

            return;
        }

        $cell->addText('LOGO EMPRESA', ['bold' => true, 'size' => 9, 'color' => 'FFFFFF'], ['alignment' => 'center']);
        $cell->addText('(insertar logotipo)', ['italic' => true, 'size' => 8, 'color' => 'FFFFFF'], ['alignment' => 'center']);
    }

    private function cell($table, string $bgColor, string $labelColor, string $valueColor, string $label, string $value, bool $italicValue = false): void
    {
        $cell = $table->addCell(2500, ['bgColor' => $bgColor, 'valign' => 'center']);
        $cell->addText($label, ['bold' => true, 'size' => 9, 'color' => $labelColor], ['alignment' => 'center']);
        $cell->addText($value, ['italic' => $italicValue, 'size' => 8, 'color' => $valueColor], ['alignment' => 'center']);
    }

    private function pageNumberCell($table): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::GRAY_BG, 'valign' => 'center']);
        $cell->addText('Página:', ['bold' => true, 'size' => 9, 'color' => self::NAVY_TEXT], ['alignment' => 'center']);

        $run = $cell->addTextRun(['alignment' => 'center']);
        $run->addField('PAGE', [], [], null);
        $run->addText(' de ', ['size' => 8, 'color' => '000000']);
        $run->addField('NUMPAGES', [], [], null);
    }

    private function formatFecha(?string $fecha): string
    {
        if (empty($fecha)) {
            return '—';
        }

        try {
            return \Carbon\Carbon::parse($fecha)->format('d/m/Y');
        } catch (\Throwable) {
            return $fecha;
        }
    }
}
