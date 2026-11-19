<?php

namespace App\Support;

/**
 * Escritor de .xlsx mínimo que escribe las filas directo a disco. PhpSpreadsheet arma cada celda
 * como objeto (≈400 MB y 20 s para 50 mil filas), y los reportes de cumplimiento del grupo
 * completo llegan a ese tamaño.
 *
 * Estilos disponibles por celda: 'title', 'header', 'bold', 'percent'. Una celda puede ser un
 * valor (string/int/float/null) o ['v' => valor, 's' => estilo].
 */
final class StreamingXlsxWriter
{
    private const STYLES = ['default' => 0, 'header' => 1, 'bold' => 2, 'percent' => 3, 'title' => 4];

    /** @var array<int, array{name:string, path:string, handle:resource|null, rows:int}> */
    private array $sheets = [];

    /** Hoja en la que se está escribiendo (se pueden tener varias abiertas y alternar con useSheet). */
    private ?int $current = null;

    public function __construct(private string $headerColor = '5B1690') {}

    /**
     * Abre una hoja nueva y la deja como actual. Las hojas quedan en el libro en el orden en que
     * se abren; las anteriores siguen abiertas (useSheet() para volver a escribir en ellas).
     *
     * @param  array<int, float>  $widths  ancho de cada columna (en caracteres)
     */
    public function startSheet(string $name, array $widths = []): void
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx_sheet_');
        $handle = fopen($path, 'w');

        fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">');

        if ($widths) {
            fwrite($handle, '<cols>');
            foreach (array_values($widths) as $i => $w) {
                $col = $i + 1;
                fwrite($handle, "<col min=\"{$col}\" max=\"{$col}\" width=\"{$w}\" customWidth=\"1\"/>");
            }
            fwrite($handle, '</cols>');
        }

        fwrite($handle, '<sheetData>');

        $this->sheets[] = [
            'name'   => mb_substr(preg_replace('#[\\\\/?*\[\]:]#', ' ', $name), 0, 31),
            'path'   => $path,
            'handle' => $handle,
            'rows'   => 0,
        ];
        $this->current = array_key_last($this->sheets);
    }

    /** Vuelve a escribir en una hoja ya abierta (por nombre). */
    public function useSheet(string $name): void
    {
        foreach ($this->sheets as $i => $sheet) {
            if ($sheet['name'] === $name && $sheet['handle']) {
                $this->current = $i;
                return;
            }
        }

        throw new \InvalidArgumentException("La hoja {$name} no está abierta.");
    }

    public function addRow(array $cells, ?string $style = null): void
    {
        $r = ++$this->sheets[$this->current]['rows'];
        $xml = "<row r=\"{$r}\">";

        foreach (array_values($cells) as $i => $cell) {
            $value = is_array($cell) ? ($cell['v'] ?? null) : $cell;
            $s = self::STYLES[is_array($cell) ? ($cell['s'] ?? $style ?? 'default') : ($style ?? 'default')] ?? 0;
            $ref = self::column($i) . $r;
            $styleAttr = $s ? " s=\"{$s}\"" : '';

            if ($value === null || $value === '') {
                $xml .= $s ? "<c r=\"{$ref}\"{$styleAttr}/>" : '';
            } elseif (is_int($value) || is_float($value)) {
                $xml .= "<c r=\"{$ref}\"{$styleAttr}><v>{$value}</v></c>";
            } else {
                $text = htmlspecialchars(self::clean((string) $value), ENT_XML1 | ENT_QUOTES, 'UTF-8');
                $xml .= "<c r=\"{$ref}\"{$styleAttr} t=\"inlineStr\"><is><t xml:space=\"preserve\">{$text}</t></is></c>";
            }
        }

        fwrite($this->sheets[$this->current]['handle'], $xml . '</row>');
    }

    public function blankRow(): void
    {
        $this->sheets[$this->current]['rows']++;
    }

    /** Guarda el .xlsx en $path. */
    public function save(string $path): void
    {
        $this->closeSheets();

        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException("No se pudo crear {$path}");
        }

        $sheetsXml = $rels = $overrides = '';
        foreach ($this->sheets as $i => $sheet) {
            $n = $i + 1;
            $name = htmlspecialchars($sheet['name'], ENT_XML1 | ENT_QUOTES, 'UTF-8');
            $sheetsXml .= "<sheet name=\"{$name}\" sheetId=\"{$n}\" r:id=\"rId{$n}\"/>";
            $rels .= "<Relationship Id=\"rId{$n}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet\" Target=\"worksheets/sheet{$n}.xml\"/>";
            $overrides .= "<Override PartName=\"/xl/worksheets/sheet{$n}.xml\" ContentType=\"application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml\"/>";
            $zip->addFile($sheet['path'], "xl/worksheets/sheet{$n}.xml");
        }
        $stylesRel = count($this->sheets) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . $overrides . '</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . "<sheets>{$sheetsXml}</sheets></workbook>");

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . "<Relationship Id=\"rId{$stylesRel}\" Type=\"http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles\" Target=\"styles.xml\"/>"
            . '</Relationships>');

        $zip->addFromString('xl/styles.xml', $this->stylesXml());
        $zip->close();

        foreach ($this->sheets as $sheet) {
            @unlink($sheet['path']);
        }
        $this->sheets = [];
    }

    public function __destruct()
    {
        foreach ($this->sheets as $sheet) {
            if ($sheet['handle']) {
                fclose($sheet['handle']);
            }
            @unlink($sheet['path']);
        }
    }

    private function closeSheets(): void
    {
        foreach ($this->sheets as $i => $sheet) {
            if ($sheet['handle']) {
                fwrite($sheet['handle'], '</sheetData></worksheet>');
                fclose($sheet['handle']);
                $this->sheets[$i]['handle'] = null;
            }
        }
        $this->current = null;
    }

    private function stylesXml(): string
    {
        $color = 'FF' . strtoupper($this->headerColor);

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="1"><numFmt numFmtId="164" formatCode="0.0%"/></numFmts>'
            . '<fonts count="4">'
            . '<font><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="14"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . "<fill><patternFill patternType=\"solid\"><fgColor rgb=\"{$color}\"/><bgColor indexed=\"64\"/></patternFill></fill></fills>"
            . '<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="5">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/>'
            . '<xf numFmtId="0" fontId="2" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'
            . '</cellXfs>'
            . '<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            . '</styleSheet>';
    }

    private static function column(int $index): string
    {
        $name = '';
        for ($n = $index + 1; $n > 0; $n = intdiv($n - 1, 26)) {
            $name = chr(65 + ($n - 1) % 26) . $name;
        }

        return $name;
    }

    /** Quita caracteres de control que invalidan el XML. */
    private static function clean(string $text): string
    {
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $text) ?? '';
    }
}
