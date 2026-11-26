<?php

namespace App\Services;

use App\Models\RegulationVersion;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;

/**
 * Pasos de un procedimiento subido en PDF o Word (sin body_html), leídos de su archivo. El Word se
 * convierte a PDF con LibreOffice y se guarda junto al original (<archivo>.preview.pdf): es el
 * mismo PDF que usan "Ver" y "Descargar", así que cada versión se convierte una sola vez.
 *
 * Nunca lanza excepción: si el archivo no está, no se puede convertir o no se puede leer, no hay
 * pasos y el checklist se queda con la pregunta fija (el auditor agrega sus criterios).
 */
class ProcedureDocumentReader
{
    public function __construct(private OfficeDocumentConverter $converter) {}

    /** @return array<int, array> lo mismo que ProcedureActivityReader::steps */
    public function steps(RegulationVersion $version): array
    {
        $pdf = $this->pdfOf($version);
        if ($pdf === null) {
            return [];
        }

        try {
            $pages = array_map(fn ($page) => $page->getText(), (new Parser())->parseContent($pdf)->getPages());
        } catch (\Throwable $e) {
            Log::warning('ProcedureDocumentReader: no se pudo leer el PDF del proceso', ['version' => $version->id, 'error' => $e->getMessage()]);

            return [];
        }

        return ProcedureActivityReader::steps($pages);
    }

    /** El contenido del PDF de la versión: el archivo si es PDF; si es Word, su conversión. */
    private function pdfOf(RegulationVersion $version): ?string
    {
        $disk = Storage::disk('private');
        if (! $version->file_path || ! $disk->exists($version->file_path)) {
            return null;
        }

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return $disk->get($version->file_path);
        }
        if (! $this->converter->isConvertible($ext)) {
            return null;
        }

        $previewPath = $version->file_path . '.preview.pdf';
        if (! $disk->exists($previewPath)) {
            $pdf = $this->converter->toPdf($disk->path($version->file_path), $ext);
            if ($pdf === null) {
                return null;
            }
            $disk->put($previewPath, $pdf);
        }

        return $disk->get($previewPath);
    }
}
