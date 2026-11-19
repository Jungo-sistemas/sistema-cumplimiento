<?php

namespace App\Services;

use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;

/**
 * El encabezado del .docx (nombre, código, elaboró, aprobó, fecha de elaboración) se escribe una
 * sola vez, al crear la versión. "Ver" de un documento hecho en el sistema lo arma con los datos
 * actuales, pero "Descargar" (y "Ver" de un Word editado) usan el archivo — así que al cambiar la
 * información básica el archivo seguía con los datos viejos. Aquí se le reescribe el encabezado a
 * la versión vigente y a la publicada, y se borra su PDF ya convertido para que se regenere.
 *
 * Solo a los .docx con el encabezado del sistema (generados con IA / editados en línea): un Word
 * o PDF subido tal cual trae su propio encabezado, que no es del sistema y no se toca.
 */
class RegulationDocxHeaderRefresher
{
    public function __construct(
        private DocxLayoutPatcher $patcher,
        private RegulationDocxHeaderBuilder $builder,
        private DocxHeaderLogo $logos,
    ) {}

    /** @return int cuántos archivos se actualizaron */
    public function refresh(Regulation $regulation): int
    {
        if ($regulation->is_annex) {
            return 0;
        }

        $versions = collect([$regulation->currentVersion()->first(), $regulation->publishedVersion()->first()])
            ->filter()
            ->unique('id');

        $updated = 0;
        foreach ($versions as $version) {
            if ($this->refreshVersion($regulation, $version)) {
                $updated++;
            }
        }

        return $updated;
    }

    /** Si la versión lleva el encabezado del sistema (y por lo tanto se puede reescribir). */
    public function hasSystemHeader(RegulationVersion $version): bool
    {
        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path ?? '', PATHINFO_EXTENSION));

        return $ext === 'docx' && ($version->body_html || $version->keeps_docx_layout);
    }

    /**
     * Logo del encabezado del .docx de la versión. Si no trae (versiones editadas antes de que
     * RegulationDocxHeaderBuilder pusiera el logo), el de la versión anterior más reciente que sí.
     */
    public function logoFor(RegulationVersion $version): ?array
    {
        $disk = Storage::disk('private');

        // withTrashed: un proceso en la papelera no carga por la relación normal (versión huérfana).
        $regulation = $version->regulation
            ?? Regulation::withoutGlobalScopes()->withTrashed()->find($version->regulation_id);
        if (! $regulation) {
            return null;
        }

        $candidates = $regulation->versions()
            ->where('version_number', '<=', $version->version_number)
            ->orderByDesc('version_number')
            ->pluck('file_path');

        foreach ($candidates as $path) {
            if ($path && str_ends_with(strtolower($path), '.docx') && $disk->exists($path)
                && ($logo = $this->logos->extract($disk->path($path)))) {
                return $logo;
            }
        }

        return null;
    }

    private function refreshVersion(Regulation $regulation, RegulationVersion $version): bool
    {
        $disk = Storage::disk('private');
        if (! $this->hasSystemHeader($version) || ! $disk->exists($version->file_path)) {
            return false;
        }

        $version->setRelation('regulation', $regulation);

        $details = $regulation->details ?? [];
        $headerDocx = $this->buildHeaderDocx([
            'nombre'         => $regulation->name,
            'codigo'         => $regulation->code,
            // Cada versión conserva su propio número.
            'version'        => sprintf('%02d', $version->version_number),
            'quien_elabora'  => $details['quien_elabora'] ?? null,
            'quien_aprueba'  => $details['quien_aprueba'] ?? null,
            'fecha_vigencia' => $details['fecha_vigencia'] ?? null,
            'logo'           => $this->logoFor($version),
        ]);

        // Se trabaja sobre una copia: si algo falla, el archivo original queda intacto.
        $tmp = tempnam(sys_get_temp_dir(), 'header_refresh_');
        $ok = copy($disk->path($version->file_path), $tmp)
            && $this->patcher->replaceHeaderOnly($tmp, $headerDocx);

        if ($ok) {
            $disk->put($version->file_path, file_get_contents($tmp));
            // PDF ya convertido para Ver/Descargar: se regenera con el encabezado nuevo.
            $disk->delete($version->file_path . '.preview.pdf');
        }

        @unlink($tmp);
        @unlink($headerDocx);

        return $ok;
    }

    private function buildHeaderDocx(array $meta): string
    {
        // Mismos ajustes de página que RegulationController::renderHtmlToDocx().
        Settings::setDefaultFontName('Arial');
        Settings::setDefaultFontSize(11);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'paperSize'    => 'Letter',
            'marginTop'    => 2000,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
            'headerHeight' => 1300,
        ]);
        $this->builder->apply($section, $meta);

        $path = tempnam(sys_get_temp_dir(), 'header_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($path);
        $this->builder->finishFile($path);

        return $path;
    }
}
