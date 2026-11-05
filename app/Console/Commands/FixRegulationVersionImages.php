<?php

namespace App\Console\Commands;

use App\Models\RegulationVersion;
use App\Services\DocxImageGeometry;
use App\Services\RegulationDocxBodyBuilder;
use App\Services\RegulationDocxHeaderBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * Repara las imágenes de una versión editada en el sistema a partir del .docx original que se
 * subió: antes de DocxImageGeometry, abrir un .docx subido en el editor dejaba cada imagen cruda
 * (sin el estirado/recorte con que Word la mostraba) y el diagrama quedaba deformado (F-MDI-043).
 *
 * Toma las imágenes del original ya corregidas y las pone, en el mismo orden, en lugar de las del
 * body_html de la versión — el resto del contenido (etiquetas de personas y documentos, cambios de
 * texto) se queda como está. Luego vuelve a armar su .docx y borra el PDF en caché.
 */
class FixRegulationVersionImages extends Command
{
    protected $signature = 'fix:regulation-version-images
                            {version : ID de la versión a reparar (regulation_versions.id)}
                            {original : Ruta del .docx original que se subió}
                            {--dry-run : Muestra qué imágenes cambiarían sin guardar nada}';

    protected $description = 'Corrige las imágenes deformadas de una versión editada, tomándolas del .docx original';

    public function handle(DocxImageGeometry $geometry): int
    {
        $version = RegulationVersion::with('regulation')->findOrFail($this->argument('version'));
        $original = $this->argument('original');

        if (! is_file($original)) {
            $this->error("No existe el archivo: {$original}");

            return self::FAILURE;
        }
        if (! $version->body_html) {
            $this->error('La versión no tiene body_html (no se editó en el sistema): no hay nada que reparar.');

            return self::FAILURE;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'fix_images_') . '.html';
        IOFactory::createWriter(IOFactory::load($original), 'HTML')->save($tmp);
        $raw = file_get_contents($tmp);
        @unlink($tmp);
        $originalHtml = $geometry->apply(preg_match('/<body[^>]*>(.*?)<\/body>/si', $raw, $m) ? $m[1] : $raw, $original);

        preg_match_all('/<img\b[^>]*>/i', $originalHtml, $fixedTags);
        preg_match_all('/<img\b[^>]*>/i', $version->body_html, $currentTags, PREG_OFFSET_CAPTURE);
        $fixedTags = $fixedTags[0];
        $currentTags = $currentTags[0];

        if (count($fixedTags) !== count($currentTags)) {
            $this->error(sprintf(
                'El original tiene %d imagen(es) y la versión %d: no se pueden emparejar con seguridad.',
                count($fixedTags), count($currentTags)
            ));

            return self::FAILURE;
        }

        $this->table(['#', 'Ahora', 'Corregida'], array_map(fn ($i) => [
            $i + 1, $this->describe($currentTags[$i][0]), $this->describe($fixedTags[$i]),
        ], array_keys($fixedTags)));

        if ($this->option('dry-run')) {
            $this->info('[dry-run] No se guardó nada.');

            return self::SUCCESS;
        }

        $html = $version->body_html;
        foreach (array_reverse($currentTags, true) as $i => [$tag, $offset]) {
            $html = substr_replace($html, $fixedTags[$i], $offset, strlen($tag));
        }

        $disk = Storage::disk('private');
        $backup = $version->file_path . '.respaldo-' . now()->format('YmdHis') . '.docx';
        $disk->copy($version->file_path, $backup);

        $version->update(['body_html' => $html]);
        $disk->put($version->file_path, $this->compileDocx($version, $html));
        $disk->delete($version->file_path . '.preview.pdf');

        $this->info("Listo. Respaldo del .docx anterior: {$backup}");

        return self::SUCCESS;
    }

    private function describe(string $tag): string
    {
        $style = preg_match('/style="([^"]*)"/i', $tag, $s) ? $s[1] : '—';
        $size = preg_match('/base64,([^"]+)/', $tag, $b) ? @getimagesizefromstring(base64_decode($b[1])) : null;

        return $style . ($size ? " ({$size[0]}×{$size[1]} px)" : '');
    }

    /** Mismo armado que RegulationVersionController::saveEdit(). */
    private function compileDocx(RegulationVersion $version, string $html): string
    {
        $regulation = $version->regulation;
        $details = $regulation->details ?? [];

        \PhpOffice\PhpWord\Settings::setDefaultFontName('Arial');
        \PhpOffice\PhpWord\Settings::setDefaultFontSize(11);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'paperSize'    => 'Letter',
            'marginTop'    => 2000,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
            'headerHeight' => 1300,
        ]);
        if (! $regulation->is_annex) {
            app(RegulationDocxHeaderBuilder::class)->apply($section, [
                'nombre'         => $regulation->name,
                'codigo'         => $regulation->code,
                'version'        => sprintf('%02d', $version->version_number),
                'quien_elabora'  => $details['quien_elabora'] ?? null,
                'quien_aprueba'  => $details['quien_aprueba'] ?? null,
                'fecha_vigencia' => $details['fecha_vigencia'] ?? null,
            ]);
        }
        app(RegulationDocxBodyBuilder::class)->apply($section, $html);

        $tmp = tempnam(sys_get_temp_dir(), 'fix_images_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
        $content = file_get_contents($tmp);
        @unlink($tmp);

        return $content;
    }
}
