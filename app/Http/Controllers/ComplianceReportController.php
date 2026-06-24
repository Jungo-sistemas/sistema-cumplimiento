<?php

namespace App\Http\Controllers;

use App\Models\AssetRequirementDocument;
use App\Models\TaskDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ComplianceReportController extends Controller
{
    public function weeklyReport(): StreamedResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $since = now()->subDays(7)->startOfDay();

        // ── Evidencias oficiales de requerimientos ───────────────────────────
        $officialDocs = AssetRequirementDocument::query()
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
            ->map(fn ($doc) => [
                'tipo'          => 'Evidencia oficial',
                'empresa'       => $doc->requirement?->asset?->company?->name ?? '—',
                'tipo_activo'   => $doc->requirement?->asset?->assetType?->name ?? '—',
                'activo'        => $doc->requirement?->asset?->name ?? '—',
                'requerimiento' => $doc->requirement?->template?->name ?? '—',
                'tarea'         => '—',
                'archivo'       => $doc->original_name ?? basename($doc->file_path),
                'tamano'        => $doc->size ? round($doc->size / 1024, 1) : '—',
                'version'       => $doc->version_number ?? '—',
                'emision'       => $doc->issued_at?->format('d/m/Y') ?? '—',
                'vencimiento'   => $doc->expires_at?->format('d/m/Y') ?? '—',
                'subido_por'    => $doc->uploader?->name ?? '—',
                'fecha_carga'   => $doc->created_at->format('d/m/Y H:i'),
            ]);

        // ── Documentos adjuntos en tareas ────────────────────────────────────
        $taskDocs = TaskDocument::query()
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
            ->map(fn ($doc) => [
                'tipo'          => 'Documento de tarea',
                'empresa'       => $doc->task?->requirement?->asset?->company?->name ?? '—',
                'tipo_activo'   => $doc->task?->requirement?->asset?->assetType?->name ?? '—',
                'activo'        => $doc->task?->requirement?->asset?->name ?? '—',
                'requerimiento' => $doc->task?->requirement?->template?->name ?? '—',
                'tarea'         => $doc->task?->title ?? '—',
                'archivo'       => basename($doc->file_path),
                'tamano'        => '—',
                'version'       => '—',
                'emision'       => '—',
                'vencimiento'   => '—',
                'subido_por'    => $doc->uploader?->name ?? '—',
                'fecha_carga'   => $doc->created_at->format('d/m/Y H:i'),
            ]);

        $rows = $officialDocs
            ->concat($taskDocs)
            ->sortByDesc('fecha_carga')
            ->values();

        $filename = 'cumplimiento_semana_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Tipo',
                'Empresa',
                'Tipo de activo',
                'Activo',
                'Requerimiento',
                'Tarea',
                'Archivo',
                'Tamaño (KB)',
                'N° Versión',
                'Fecha de emisión',
                'Fecha de vencimiento',
                'Subido por',
                'Fecha de carga',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, [
                    $row['tipo'],
                    $row['empresa'],
                    $row['tipo_activo'],
                    $row['activo'],
                    $row['requerimiento'],
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
