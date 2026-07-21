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
    private const ACCENT_BG = 'D9E2F3';
    private const GRAY_BG = 'A6A6A6';
    private const BORDER = '000000';

    /**
     * @param  array{nombre: string, codigo: ?string, version: int|string, quien_elabora: ?string, quien_aprueba: ?string, fecha_vigencia: ?string}  $meta
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

        // Fila 1: logo / nombre del procedimiento / código / versión
        $table->addRow();
        $this->logoCell($table);
        $this->cell($table, self::ACCENT_BG, 'PROCEDIMIENTO', $meta['nombre'] ?? '', italicValue: true);
        $this->cell($table, self::ACCENT_BG, 'CÓDIGO', $meta['codigo'] ?? '—');
        $this->cell($table, self::ACCENT_BG, 'VERSIÓN', (string) ($meta['version'] ?? '01'));

        // Fila 2: elaboró / aprobó / vigencia / página
        $table->addRow();
        $this->cell($table, self::GRAY_BG, 'ELABORADO POR:', $meta['quien_elabora'] ?? '—');
        $this->cell($table, self::GRAY_BG, 'APROBADO POR:', $meta['quien_aprueba'] ?? '—');
        $this->cell($table, self::GRAY_BG, 'Fecha de elaboración:', $this->formatFecha($meta['fecha_vigencia'] ?? null));
        $this->pageNumberCell($table);
    }

    private function logoCell($table): void
    {
        $cell = $table->addCell(2500, ['valign' => 'center']);
        $cell->addText('LOGO EMPRESA', ['bold' => true, 'size' => 9], ['alignment' => 'center']);
        $cell->addText('(insertar logotipo)', ['italic' => true, 'size' => 8], ['alignment' => 'center']);
    }

    private function cell($table, string $bgColor, string $label, string $value, bool $italicValue = false): void
    {
        $cell = $table->addCell(2500, ['bgColor' => $bgColor, 'valign' => 'center']);
        $cell->addText($label, ['bold' => true, 'size' => 9], ['alignment' => 'center']);
        $cell->addText($value, ['italic' => $italicValue, 'size' => 8], ['alignment' => 'center']);
    }

    private function pageNumberCell($table): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::GRAY_BG, 'valign' => 'center']);
        $cell->addText('Página:', ['bold' => true, 'size' => 9], ['alignment' => 'center']);

        $run = $cell->addTextRun(['alignment' => 'center']);
        $run->addField('PAGE', [], [], null);
        $run->addText(' de ', ['size' => 8]);
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
