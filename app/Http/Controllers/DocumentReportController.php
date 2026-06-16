<?php

namespace App\Http\Controllers;

use App\Models\DocumentVersion;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReportController extends Controller
{
    public function weeklyReport(): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $since = now()->subDays(7)->startOfDay();

        $versions = DocumentVersion::query()
            ->with([
                'uploader:id,name',
                'document.folder.parent',
                'document.company:id,name',
                'document.uploader:id,name',
            ])
            ->where('document_versions.created_at', '>=', $since)
            ->whereHas('document', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
                if (! $user->hasGroupScope() && $user->company_id) {
                    $q->where('company_id', $user->company_id);
                }
            })
            ->orderBy('document_versions.created_at', 'desc')
            ->get();

        $filename = 'documentos_semana_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($versions) {
            $handle = fopen('php://output', 'w');

            // BOM para que Excel abra UTF-8 correctamente
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Documento',
                'Tipo',
                'Referencia',
                'Responsable',
                'Carpeta',
                'Categoría',
                'Empresa',
                'N° Versión',
                'Archivo original',
                'Tamaño (KB)',
                'Fecha de emisión',
                'Fecha de vencimiento',
                'Subido por',
                'Fecha de carga',
                'Es versión actual',
            ]);

            foreach ($versions as $v) {
                $doc    = $v->document;
                $folder = $doc?->folder;

                // folder puede ser category (tiene parent) o folder directo
                if ($folder?->parent) {
                    $carpeta   = $folder->parent->name;
                    $categoria = $folder->name;
                } else {
                    $carpeta   = $folder?->name ?? '—';
                    $categoria = '—';
                }

                fputcsv($handle, [
                    $doc?->name ?? '—',
                    $doc?->document_type ?? '—',
                    $doc?->reference ?? '—',
                    $doc?->responsible_name ?? '—',
                    $carpeta,
                    $categoria,
                    $doc?->company?->name ?? '—',
                    $v->version_number,
                    $v->original_name ?? '—',
                    $v->file_size ? round($v->file_size / 1024, 1) : '—',
                    $v->issued_at?->format('d/m/Y') ?? '—',
                    $v->valid_until?->format('d/m/Y') ?? '—',
                    $v->uploader?->name ?? '—',
                    $v->created_at->format('d/m/Y H:i'),
                    $v->is_current ? 'Sí' : 'No',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
