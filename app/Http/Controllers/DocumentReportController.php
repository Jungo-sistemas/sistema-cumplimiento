<?php

namespace App\Http\Controllers;

use App\Models\AssetRequirementDocument;
use App\Models\DocumentVersion;
use App\Models\TaskDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DocumentReportController extends Controller
{
    public function weeklyReport(): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $since = now()->subDays(7)->startOfDay();

        $rows = collect();

        // ── 1. Documentos generales ──────────────────────────────────────────
        DocumentVersion::query()
            ->with([
                'uploader:id,name',
                'document.folder.parent',
                'document.company:id,name',
            ])
            ->where('document_versions.created_at', '>=', $since)
            ->whereHas('document', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
                if (! $user->hasGroupScope() && $user->company_id) {
                    $q->where('company_id', $user->company_id);
                }
            })
            ->orderByDesc('document_versions.created_at')
            ->get()
            ->each(function ($v) use ($rows) {
                $doc    = $v->document;
                $folder = $doc?->folder;

                if ($folder?->parent) {
                    $seccion    = $folder->parent->name;
                    $subseccion = $folder->name;
                } else {
                    $seccion    = $folder?->name ?? '—';
                    $subseccion = '—';
                }

                $rows->push([
                    'origen'      => 'Documentos generales',
                    'empresa'     => $doc?->company?->name ?? '—',
                    'seccion'     => $seccion,
                    'subseccion'  => $subseccion,
                    'referencia'  => $doc?->name ?? '—',
                    'tarea'       => '—',
                    'archivo'     => $v->original_name ?? '—',
                    'tamano'      => $v->file_size ? round($v->file_size / 1024, 1) : '—',
                    'version'     => $v->version_number ?? '—',
                    'emision'     => $v->issued_at?->format('d/m/Y') ?? '—',
                    'vencimiento' => $v->valid_until?->format('d/m/Y') ?? '—',
                    'subido_por'  => $v->uploader?->name ?? '—',
                    'fecha_carga' => $v->created_at->format('d/m/Y H:i'),
                ]);
            });

        // ── 2. Evidencias oficiales de requerimientos ────────────────────────
        AssetRequirementDocument::query()
            ->with([
                'uploader:id,name',
                'requirement.asset.assetType:id,name',
                'requirement.asset.company:id,name',
                'requirement.template:id,name',
            ])
            ->where('asset_requirement_documents.created_at', '>=', $since)
            ->whereHas('requirement.asset.company', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
                if (! $user->hasGroupScope() && $user->company_id) {
                    $q->where('id', $user->company_id);
                }
            })
            ->orderByDesc('asset_requirement_documents.created_at')
            ->get()
            ->each(function ($doc) use ($rows) {
                $rows->push([
                    'origen'      => 'Cumplimiento – Evidencia oficial',
                    'empresa'     => $doc->requirement?->asset?->company?->name ?? '—',
                    'seccion'     => $doc->requirement?->asset?->assetType?->name ?? '—',
                    'subseccion'  => $doc->requirement?->asset?->name ?? '—',
                    'referencia'  => $doc->requirement?->template?->name ?? '—',
                    'tarea'       => '—',
                    'archivo'     => $doc->original_name ?? basename($doc->file_path),
                    'tamano'      => $doc->size ? round($doc->size / 1024, 1) : '—',
                    'version'     => $doc->version_number ?? '—',
                    'emision'     => $doc->issued_at?->format('d/m/Y') ?? '—',
                    'vencimiento' => $doc->expires_at?->format('d/m/Y') ?? '—',
                    'subido_por'  => $doc->uploader?->name ?? '—',
                    'fecha_carga' => $doc->created_at->format('d/m/Y H:i'),
                ]);
            });

        // ── 3. Documentos adjuntos en tareas ─────────────────────────────────
        TaskDocument::query()
            ->with([
                'uploader:id,name',
                'task:id,title,asset_requirement_id',
                'task.requirement.asset.assetType:id,name',
                'task.requirement.asset.company:id,name',
                'task.requirement.template:id,name',
            ])
            ->where('task_documents.created_at', '>=', $since)
            ->whereHas('task.requirement.asset.company', function ($q) use ($user) {
                $q->where('group_id', $user->group_id);
                if (! $user->hasGroupScope() && $user->company_id) {
                    $q->where('id', $user->company_id);
                }
            })
            ->orderByDesc('task_documents.created_at')
            ->get()
            ->each(function ($doc) use ($rows) {
                $rows->push([
                    'origen'      => 'Cumplimiento – Tarea',
                    'empresa'     => $doc->task?->requirement?->asset?->company?->name ?? '—',
                    'seccion'     => $doc->task?->requirement?->asset?->assetType?->name ?? '—',
                    'subseccion'  => $doc->task?->requirement?->asset?->name ?? '—',
                    'referencia'  => $doc->task?->requirement?->template?->name ?? '—',
                    'tarea'       => $doc->task?->title ?? '—',
                    'archivo'     => basename($doc->file_path),
                    'tamano'      => '—',
                    'version'     => '—',
                    'emision'     => '—',
                    'vencimiento' => '—',
                    'subido_por'  => $doc->uploader?->name ?? '—',
                    'fecha_carga' => $doc->created_at->format('d/m/Y H:i'),
                ]);
            });

        $sorted   = $rows->sortByDesc('fecha_carga')->values();
        $filename = 'reporte_semanal_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($sorted) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Origen',
                'Empresa',
                'Carpeta / Tipo de activo',
                'Categoría / Activo',
                'Documento / Requerimiento',
                'Tarea',
                'Archivo',
                'Tamaño (KB)',
                'N° Versión',
                'Fecha de emisión',
                'Fecha de vencimiento',
                'Subido por',
                'Fecha de carga',
            ]);

            foreach ($sorted as $row) {
                fputcsv($handle, [
                    $row['origen'],
                    $row['empresa'],
                    $row['seccion'],
                    $row['subseccion'],
                    $row['referencia'],
                    $row['tarea'],
                    $row['archivo'],
                    $row['tamano'],
                    $row['version'],
                    $row['emision'],
                    $row['vencimiento'],
                    $row['subido_por'],
                    $row['fecha_carga'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
