<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationShare;
use App\Services\ApprovalFlowService;
use App\Services\OfficeDocumentConverter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Documento con el resumen de cambios de un proceso, que un administrador sube desde
 * "Mis aprobaciones" para que los aprobadores lo vean en vez de tener que revisar todo el
 * documento. Es uno por proceso (no por versión): sirve de guía para las aprobaciones que
 * vengan después hasta que alguien suba otro, que reemplaza al anterior.
 */
class RegulationChangeSummaryController extends Controller
{
    public function store(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $request->validateWithBag('changeSummary' . $regulation->id, [
            'file' => ['required', 'file', 'mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png', 'max:20480'],
        ], [
            'file.required' => 'Selecciona el documento con el resumen de cambios.',
            'file.mimes'    => 'El documento debe ser PDF, Word, Excel o imagen (JPG/PNG).',
            'file.max'      => 'El documento no puede pesar más de 20 MB.',
        ]);

        $file = $request->file('file');
        $path = $file->store("regulations/{$regulation->company_id}/{$regulation->id}/change-summary", 'private');

        $this->deleteStoredFile($regulation->change_summary_path);

        $regulation->update([
            'change_summary_path'          => $path,
            'change_summary_original_name' => $file->getClientOriginalName(),
            'change_summary_mime_type'     => $file->getMimeType(),
            'change_summary_uploaded_by'   => $user->id,
            'change_summary_uploaded_at'   => now(),
        ]);

        return back()->with('success', 'Resumen de cambios guardado para «' . $regulation->name . '».');
    }

    public function show(Regulation $regulation)
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

    private function deleteStoredFile(?string $path): void
    {
        if ($path) {
            Storage::disk('private')->delete([$path, $path . '.preview.pdf']);
        }
    }
}
