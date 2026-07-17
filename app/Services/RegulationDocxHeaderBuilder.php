<?php

namespace App\Services;

use PhpOffice\PhpWord\Element\Section;

/**
 * Encabezado fijo (tabla de 4 columnas x 2 filas) que debe aparecer idéntico en TODOS los
 * documentos de Procesos, en todas las páginas — nombre, código, versión, elaboró, aprobó,
 * fecha de efectividad y número de página automático.
 *
 * A propósito NO se le pide esto a la IA en cada generación: por más detalladas que sean las
 * instrucciones, no hay garantía de que el modelo lo replique exactamente igual dos veces
 * (colores, bordes, estructura de tabla). Construirlo aquí, con los datos reales del
 * documento, lo deja 100% consistente siempre — y como se agrega vía `Section::addHeader()`,
 * Word lo repite nativamente en cada página sin que el HTML del cuerpo tenga que mencionarlo.
 */
class RegulationDocxHeaderBuilder
{
    private const NAVY = '002060';
    private const LIGHT_GRAY = 'F2F3F4';
    private const BORDER = '1A3A5C';

    /**
     * @param  array{nombre: string, codigo: ?string, version: int|string, quien_elabora: ?string, quien_aprueba: ?string, fecha_vigencia: ?string}  $meta
     */
    public function apply(Section $section, array $meta): void
    {
        $header = $section->addHeader();

        $table = $header->addTable([
            'borderColor' => self::BORDER,
            'borderSize'  => 6,
            'unit'        => 'pct',
            'width'       => 100 * 50,
            'cellMargin'  => 80,
        ]);

        $table->addRow();
        $this->headerCell($table, 'Logo');
        $this->headerCell($table, 'Nombre del procedimiento');
        $this->headerCell($table, 'Código');
        $this->headerCell($table, 'Versión');

        $table->addRow();
        $this->labelValueCell($table, 'Elaborado por', $meta['quien_elabora'] ?? '—');
        $this->labelValueCell($table, 'Aprobado por', $meta['quien_aprueba'] ?? '—');
        $this->labelValueCell($table, 'Fecha efectividad', $this->formatFecha($meta['fecha_vigencia'] ?? null));
        $this->pageNumberCell($table);

        // Segunda fila con los valores reales de nombre/código/versión, debajo de los títulos.
        $table->addRow();
        $this->valueCell($table, ''); // logo: sin valor, solo la etiqueta de arriba
        $this->valueCell($table, $meta['nombre'] ?? '');
        $this->valueCell($table, $meta['codigo'] ?? '—');
        $this->valueCell($table, (string) ($meta['version'] ?? '01'));
    }

    private function headerCell($table, string $text): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::NAVY, 'valign' => 'center']);
        $cell->addText($text, ['color' => 'FFFFFF', 'bold' => true, 'size' => 9], ['alignment' => 'center']);
    }

    private function valueCell($table, string $text): void
    {
        $cell = $table->addCell(2500, ['valign' => 'center']);
        $cell->addText($text, ['size' => 9], ['alignment' => 'center']);
    }

    private function labelValueCell($table, string $label, string $value): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::LIGHT_GRAY, 'valign' => 'center']);
        $run = $cell->addTextRun(['alignment' => 'center']);
        $run->addText($label . ': ', ['color' => self::NAVY, 'bold' => true, 'size' => 8]);
        $run->addText($value, ['size' => 8]);
    }

    private function pageNumberCell($table): void
    {
        $cell = $table->addCell(2500, ['bgColor' => self::LIGHT_GRAY, 'valign' => 'center']);
        $run = $cell->addTextRun(['alignment' => 'center']);
        $run->addText('Página ', ['color' => self::NAVY, 'bold' => true, 'size' => 8]);
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
