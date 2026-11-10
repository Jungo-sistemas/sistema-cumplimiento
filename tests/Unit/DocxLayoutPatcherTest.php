<?php

namespace Tests\Unit;

use App\Services\DocxLayoutPatcher;
use DOMDocument;
use DOMXPath;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;
use ZipArchive;

/**
 * Editar un Word subido parcha el .docx anterior en vez de reconstruirlo: lo que no cambió conserva
 * su formato exacto y lo editado conserva el formato de su párrafo/fila.
 */
class DocxLayoutPatcherTest extends TestCase
{
    private const W = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';

    private string $base;
    private string $out;

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = tempnam(sys_get_temp_dir(), 'patch_base_') . '.docx';
        $this->out = tempnam(sys_get_temp_dir(), 'patch_out_') . '.docx';
        self::writeSampleDocx($this->base);
    }

    protected function tearDown(): void
    {
        @unlink($this->base);
        @unlink($this->out);
        parent::tearDown();
    }

    /**
     * Word chico con lo que la reconstrucción desde HTML rompía: título con borde inferior,
     * viñetas con estilo de lista, tabla con bordes/sombreado y un párrafo justificado.
     */
    public static function writeSampleDocx(string $path): void
    {
        $title = fn (string $t) => '<w:p><w:pPr><w:pBdr><w:bottom w:val="single" w:sz="8" w:space="1" w:color="002060"/></w:pBdr></w:pPr>'
            . '<w:r><w:rPr><w:b/><w:color w:val="002060"/><w:sz w:val="24"/></w:rPr><w:t>' . $t . '</w:t></w:r></w:p>';
        $bullet = fn (string $t) => '<w:p><w:pPr><w:pStyle w:val="Listaconvietas"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1"/></w:numPr></w:pPr>'
            . '<w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="20"/></w:rPr><w:t>' . $t . '</w:t></w:r></w:p>';
        $cell = fn (string $t, string $fill) => '<w:tc><w:tcPr><w:tcW w:w="4000" w:type="dxa"/><w:shd w:val="clear" w:color="auto" w:fill="' . $fill . '"/></w:tcPr>'
            . '<w:p><w:r><w:rPr><w:sz w:val="19"/></w:rPr><w:t>' . $t . '</w:t></w:r></w:p></w:tc>';
        $row = fn (string $a, string $b, string $fill = 'FFFFFF') => '<w:tr>' . $cell($a, $fill) . $cell($b, $fill) . '</w:tr>';

        $body = $title('OBJETIVO')
            . '<w:p><w:pPr><w:jc w:val="both"/></w:pPr><w:r><w:rPr><w:rFonts w:ascii="Arial" w:hAnsi="Arial"/><w:sz w:val="22"/></w:rPr><w:t>Establecer el procedimiento para administrar las metas.</w:t></w:r></w:p>'
            . $title('TÓPICOS')
            . $bullet('Asignación mensual de metas.')
            . $bullet('Módulo de avisos.')
            . $bullet('Revisión periódica de las plataformas.')
            . $title('DEFINICIONES')
            . '<w:tbl><w:tblPr><w:tblW w:w="8000" w:type="dxa"/><w:tblBorders><w:top w:val="single" w:sz="4" w:color="1A3A5C"/>'
            . '<w:bottom w:val="single" w:sz="4" w:color="1A3A5C"/><w:insideH w:val="single" w:sz="4" w:color="1A3A5C"/></w:tblBorders></w:tblPr>'
            . '<w:tblGrid><w:gridCol w:w="4000"/><w:gridCol w:w="4000"/></w:tblGrid>'
            . $row('TÉRMINO', 'DEFINICIÓN', '002060') . $row('ATQ', 'Autotanque de gas.') . $row('PTA', 'Planta de almacenamiento.')
            . '</w:tbl>'
            . '<w:p><w:r><w:t>Detonante del proceso:</w:t></w:r></w:p>'
            . '<w:sectPr><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1800" w:right="1200" w:bottom="1440" w:left="1200" w:header="700" w:footer="700" w:gutter="0"/></w:sectPr>';

        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>');
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="' . self::W . '" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><w:body>' . $body . '</w:body></w:document>');
        $zip->close();
    }

    /** HTML como el que deja el editor para el Word de ejemplo (viñetas como <p>, como PhpWord). */
    private function editorHtml(): string
    {
        return '<p><span style="color: #002060; font-weight: bold;">OBJETIVO</span></p>'
            . '<p style="text-align: justify;">Establecer el procedimiento para administrar las metas.</p>'
            . '<p><span style="color: #002060; font-weight: bold;">TÓPICOS</span></p>'
            . '<p>Asignación mensual de metas.</p><p>Módulo de avisos.</p><p>Revisión periódica de las plataformas.</p>'
            . '<p><span style="color: #002060; font-weight: bold;">DEFINICIONES</span></p>'
            . '<table><tbody><tr><td><p>TÉRMINO</p></td><td><p>DEFINICIÓN</p></td></tr>'
            . '<tr><td><p>ATQ</p></td><td><p>Autotanque de gas.</p></td></tr>'
            . '<tr><td><p>PTA</p></td><td><p>Planta de almacenamiento.</p></td></tr></tbody></table>'
            . '<p>Detonante del proceso:</p>';
    }

    private function body(string $docx): DOMXPath
    {
        $zip = new ZipArchive();
        $zip->open($docx);
        $dom = new DOMDocument();
        $dom->loadXML($zip->getFromName('word/document.xml'));
        $zip->close();
        $xp = new DOMXPath($dom);
        $xp->registerNamespace('w', self::W);

        return $xp;
    }

    private function paragraphWithText(DOMXPath $xp, string $text): ?\DOMElement
    {
        foreach ($xp->query('//w:body/w:p') as $p) {
            if (str_contains($p->textContent, $text)) {
                return $p;
            }
        }

        return null;
    }

    public function test_sin_cambios_el_cuerpo_queda_identico(): void
    {
        $stats = app(DocxLayoutPatcher::class)->patch($this->base, $this->editorHtml(), null, $this->out);

        $this->assertSame(0, $stats['modified'] + $stats['inserted'] + $stats['deleted'] + $stats['rebuilt']);
        $this->assertSame(
            $this->body($this->base)->query('//w:body')->item(0)->C14N(),
            $this->body($this->out)->query('//w:body')->item(0)->C14N()
        );
    }

    public function test_cada_edicion_conserva_el_formato_de_su_bloque(): void
    {
        $html = str_replace(
            ['<p>Módulo de avisos.</p>', '<p>Revisión periódica de las plataformas.</p>', '<p>Planta de almacenamiento.</p></td></tr>', '<p>Detonante del proceso:</p>'],
            [
                '<p>Módulo de avisos y <strong>notificaciones</strong>.</p>',
                '',
                '<p>Planta de almacenamiento.</p></td></tr><tr><td><p>SLA</p></td><td><p>Acuerdo de nivel de servicio.</p></td></tr>',
                '<p>Detonante del proceso:</p><p>Párrafo nuevo del editor.</p>',
            ],
            $this->editorHtml()
        );

        $stats = app(DocxLayoutPatcher::class)->patch($this->base, $html, null, $this->out);
        $this->assertNotNull($stats);
        $this->assertSame(0, $stats['rebuilt'], 'Nada se tuvo que armar con PhpWord');

        $xp = $this->body($this->out);

        // La viñeta editada sigue siendo viñeta, con su fuente, y la negrita del editor.
        $bullet = $this->paragraphWithText($xp, 'Módulo de avisos y notificaciones.');
        $this->assertNotNull($bullet);
        $this->assertSame('Listaconvietas', $xp->query('./w:pPr/w:pStyle/@w:val', $bullet)->item(0)->nodeValue);
        $this->assertSame(1, $xp->query('./w:pPr/w:numPr', $bullet)->length);
        $this->assertSame('20', $xp->query('.//w:r[w:t="notificaciones"]/w:rPr/w:sz/@w:val', $bullet)->item(0)->nodeValue);
        $this->assertSame(1, $xp->query('.//w:r[w:t="notificaciones"]/w:rPr/w:b', $bullet)->length);

        // La viñeta borrada ya no está; los títulos con borde siguen intactos.
        $this->assertNull($this->paragraphWithText($xp, 'Revisión periódica'));
        $this->assertSame(3, $xp->query('//w:body/w:p[w:pPr/w:pBdr]')->length);

        // La fila nueva copia el formato de la fila de al lado (ancho y sombreado de celda).
        $rows = $xp->query('//w:tbl/w:tr');
        $this->assertSame(4, $rows->length);
        $this->assertStringContainsString('SLA', $rows->item(3)->textContent);
        $this->assertSame('4000', $xp->query('./w:tc[1]/w:tcPr/w:tcW/@w:w', $rows->item(3))->item(0)->nodeValue);
        $this->assertSame(1, $xp->query('//w:tbl/w:tblPr/w:tblBorders')->length);

        // El párrafo nuevo queda después de "Detonante" y antes de las propiedades de sección.
        $children = [];
        foreach ($xp->query('//w:body/*') as $node) {
            $children[] = $node->localName === 'p' ? trim($node->textContent) : $node->localName;
        }
        $this->assertSame(['Detonante del proceso:', 'Párrafo nuevo del editor.', 'sectPr'], array_slice($children, -3));
    }

    public function test_un_html_que_no_se_parece_no_se_parcha(): void
    {
        $html = '<p>Documento reescrito por completo.</p><p>Otro contenido.</p><p>Nada igual.</p><p>Fin.</p>';

        $this->assertNull(app(DocxLayoutPatcher::class)->patch($this->base, $html, null, $this->out));
        $this->assertFileDoesNotExist($this->out);
    }

    public function test_pone_el_encabezado_del_sistema_y_conserva_margenes(): void
    {
        $phpWord = new PhpWord();
        $header = $phpWord->addSection()->addHeader();
        $header->addText('VERSIÓN 02');
        $headerDocx = tempnam(sys_get_temp_dir(), 'hdr_') . '.docx';
        IOFactory::createWriter($phpWord, 'Word2007')->save($headerDocx);

        app(DocxLayoutPatcher::class)->patch($this->base, $this->editorHtml(), $headerDocx, $this->out);
        @unlink($headerDocx);

        $xp = $this->body($this->out);
        $ref = $xp->query('//w:sectPr/w:headerReference[@w:type="default"]')->item(0);
        $this->assertNotNull($ref);
        $this->assertSame('1800', $xp->query('//w:sectPr/w:pgMar/@w:top')->item(0)->nodeValue);

        $zip = new ZipArchive();
        $zip->open($this->out);
        $rels = $zip->getFromName('word/_rels/document.xml.rels');
        preg_match('/Id="' . $ref->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id') . '"[^>]*Target="([^"]+)"/', $rels, $m);
        $this->assertStringContainsString('VERSIÓN 02', $zip->getFromName('word/' . $m[1]));
        $this->assertStringContainsString('/word/' . $m[1], $zip->getFromName('[Content_Types].xml'));
        $zip->close();
    }
}
