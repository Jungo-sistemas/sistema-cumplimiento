<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationChangeEvidence;
use App\Models\RegulationShare;
use App\Services\ApprovalFlowService;
use App\Services\OfficeDocumentConverter;
use Illuminate\Support\Facades\Storage;

/**
 * Muestra el documento con la síntesis de cambios de un proceso, para que los aprobadores lo
 * vean en "Mis aprobaciones" en vez de tener que revisar todo el documento. Se adjunta al
 * establecer el flujo (RegulationController::setFlow → Regulation::replaceChangeSummary()).
 */
class RegulationChangeSummaryController extends Controller
{
    public function show(Regulation $regulation)
    {
        $this->authorizeView($regulation);

        $path = $regulation->change_summary_path;
        abort_unless($path && Storage::disk('private')->exists($path), 404);

        $ext = strtolower(pathinfo($regulation->change_summary_original_name ?? $path, PATHINFO_EXTENSION));

        // Word/Excel se muestran como PDF (mismo criterio y misma caché ".preview.pdf" que "Ver" en
        // las versiones del proceso). Cada archivo subido tiene su propia ruta, así que la caché
        // nunca queda desactualizada al reemplazarlo.
        $converter = app(OfficeDocumentConverter::class);

        if ($ext !== 'pdf' && $converter->isConvertible($ext)) {
            $previewPath = $path . '.preview.pdf';

            if (! Storage::disk('private')->exists($previewPath)) {
                $pdf = $converter->toPdf(Storage::disk('private')->path($path), $ext);

                if ($pdf !== null) {
                    Storage::disk('private')->put($previewPath, $pdf);
                }
            }

            if (Storage::disk('private')->exists($previewPath)) {
                return response()->file(
                    Storage::disk('private')->path($previewPath),
                    ['Content-Type' => 'application/pdf']
                );
            }

            // Sin LibreOffice / falló la conversión: se descarga el original en vez de romper la vista.
            return Storage::disk('private')->download($path, $regulation->change_summary_original_name);
        }

        return response()->file(
            Storage::disk('private')->path($path),
            ['Content-Type' => $regulation->change_summary_mime_type ?? 'application/octet-stream']
        );
    }

    /** Evidencia de apoyo de la síntesis (imagen o PDF), con los mismos permisos que la síntesis. */
    public function showEvidence(Regulation $regulation, RegulationChangeEvidence $evidence)
    {
        abort_unless($evidence->regulation_id === $regulation->id, 404);
        $this->authorizeView($regulation);
        abort_unless(Storage::disk('private')->exists($evidence->path), 404);

        return response()->file(
            Storage::disk('private')->path($evidence->path),
            ['Content-Type' => $evidence->mime_type ?? 'application/octet-stream']
        );
    }

    private function authorizeView(Regulation $regulation): void
    {
        $user = auth()->user();

        $hasPendingApproval = app(ApprovalFlowService::class)
            ->getPendingApprovalForUser($regulation, $user->id) !== null;

        $hasShare = RegulationShare::where('regulation_id', $regulation->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless(
            $user->canAccessCompany($regulation->company) || $hasPendingApproval || $hasShare,
            403
        );
    }
}
