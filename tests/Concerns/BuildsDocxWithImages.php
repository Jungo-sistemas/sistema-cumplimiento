<?php

namespace Tests\Concerns;

/**
 * Arma .docx mínimos con imágenes estiradas o recortadas, como las que trae un Word subido a mano
 * (ver App\Services\DocxImageGeometry). La imagen es de 400×200: mitad izquierda roja, mitad
 * derecha azul, para poder revisar qué parte quedó después de recortar.
 */
trait BuildsDocxWithImages
{
    private array $docxTmpFiles = [];

    protected function tearDown(): void
    {
        array_map(fn ($f) => @unlink($f), $this->docxTmpFiles);
        parent::tearDown();
    }

    protected function twoColorPng(): string
    {
        $img = imagecreatetruecolor(400, 200);
        imagefilledrectangle($img, 0, 0, 199, 199, imagecolorallocate($img, 255, 0, 0));
        imagefilledrectangle($img, 200, 0, 399, 199, imagecolorallocate($img, 0, 0, 255));
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return ob_get_clean();
    }

    /** Imagen DrawingML (Word actual) mostrada a $cxPx×$cyPx, con recorte opcional (<a:srcRect …/>). */
    protected function drawing(string $rId, int $cxPx, int $cyPx, string $srcRect = ''): string
    {
        $cx = $cxPx * 9525;
        $cy = $cyPx * 9525;

        return '<w:p><w:r><w:drawing><wp:inline><wp:extent cx="' . $cx . '" cy="' . $cy . '"/><wp:docPr id="1" name="img"/>'
            . '<a:graphic><a:graphicData uri="http://schemas.openxmlformats.org/drawingml/2006/picture"><pic:pic>'
            . '<pic:nvPicPr><pic:cNvPr id="1" name="img"/><pic:cNvPicPr/></pic:nvPicPr>'
            . '<pic:blipFill><a:blip r:embed="' . $rId . '"/>' . $srcRect . '<a:stretch><a:fillRect/></a:stretch></pic:blipFill>'
            . '<pic:spPr><a:xfrm><a:off x="0" y="0"/><a:ext cx="' . $cx . '" cy="' . $cy . '"/></a:xfrm><a:prstGeom prst="rect"/></pic:spPr>'
            . '</pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r></w:p>';
    }

    /** Imagen VML (formato antiguo de Word). */
    protected function vml(string $rId, string $style, string $crop = ''): string
    {
        return '<w:p><w:r><w:pict><v:shape style="' . $style . '"><v:imagedata r:id="' . $rId . '" ' . $crop . '/></v:shape></w:pict></w:r></w:p>';
    }

    /** .docx con el cuerpo dado; la imagen de dos colores queda en la relación "rIdImg". */
    protected function docx(string $bodyXml): string
    {
        $path = tempnam(sys_get_temp_dir(), 'geom_') . '.docx';
        $this->docxTmpFiles[] = $path;

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Default Extension="png" ContentType="image/png"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>');
        $zip->addFromString('word/_rels/document.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rIdImg" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image1.png"/></Relationships>');
        $zip->addFromString('word/media/image1.png', $this->twoColorPng());
        $zip->addFromString('word/document.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"'
            . ' xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main"'
            . ' xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:v="urn:schemas-microsoft-com:vml">'
            . '<w:body><w:p><w:r><w:t>Antes</w:t></w:r></w:p>' . $bodyXml . '<w:sectPr/></w:body></w:document>');
        $zip->close();

        return $path;
    }
}
