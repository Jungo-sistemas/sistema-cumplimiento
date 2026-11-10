<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Services\OfficeDocumentConverter;
use Illuminate\Support\Facades\Storage;

/**
 * Vista pública del QR de pared: sin sesión, sin layout de la app, sin manera de "regresar" a la
 * plataforma — la respuesta ES el PDF (Content-Type: application/pdf), así que no hay nada más que
 * mostrar en la pestaña. Siempre resuelve la versión vigente (currentVersion) en el momento de la
 * visita, nunca una versión fija, para que el mismo QR impreso sirva después de cada actualización.
 */
class RegulationPublicViewController extends Controller
{
    public function show(Regulation $regulation, string $token)
    {
        abort_unless(
            $regulation->public_share_token && hash_equals($regulation->public_share_token, $token),
            404
        );

        // Si el reglamento se editó y está a medio flujo (o se rechazó) desde que se generó este
        // link, el contenido actual ya no está validado — el QR de pared no debe servirlo hasta
        // que vuelva a quedar aprobado.
        abort_unless($regulation->approval_status === 'approved', 404);

        // Un proceso confidencial nunca se sirve sin sesión.
        abort_if($regulation->isRestricted(), 404);

        $version = $regulation->currentVersion;

        abort_unless($version && $version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));

        if ($ext === 'docx') {
            // Mismo PDF cacheado (y mismo sidecar ".preview.pdf") que ya usan preview()/download() en
            // RegulationVersionController — file_path es inmutable por versión, así que nunca queda
            // desactualizado.
            $previewPath = $version->file_path . '.preview.pdf';

            if (! Storage::disk('private')->exists($previewPath)) {
                $pdf = app(OfficeDocumentConverter::class)->toPdf(
                    Storage::disk('private')->path($version->file_path),
                    'docx'
                );

                if ($pdf !== null) {
                    Storage::disk('private')->put($previewPath, $pdf);
                }
            }

            abort_unless(Storage::disk('private')->exists($previewPath), 404);

            return Storage::disk('private')->response($previewPath, ($regulation->code ?: $regulation->name) . '.pdf');
        }

        if ($ext === 'pdf') {
            return Storage::disk('private')->response(
                $version->file_path,
                $version->original_name ?? basename($version->file_path)
            );
        }

        // Otros formatos de Office subidos manualmente: no hay forma de mostrarlos inline sin
        // descargarlos y abrir otra app, lo que rompe la idea de "solo ver" del QR de pared.
        abort(415, 'Este documento no se puede mostrar directamente — solo PDF o Word.');
    }
}
