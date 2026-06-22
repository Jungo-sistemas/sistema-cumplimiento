<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProcessReportController extends Controller
{
    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $ids = array_filter(array_map('intval', $request->input('regulation_ids', [])));
        abort_if(empty($ids), 422, 'Selecciona al menos un documento.');

        $query = Regulation::query()
            ->whereIn('id', $ids)
            ->where('group_id', $user->group_id)
            ->where('is_active', true);

        if ($user->hasCompanyScope()) {
            $query->where('company_id', $user->company_id);
        }

        $regulations = $query
            ->with([
                'company:id,name',
                'processType:id,name',
                'creator:id,name',
                'currentVersion',
                'approvals' => fn ($q) => $q->where('status', 'pending')->orderBy('step_number'),
            ])
            ->orderBy('name')
            ->get();

        $filename = 'reporte_procesos_' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($regulations) {
            $handle = fopen('php://output', 'w');

            // BOM para que Excel abra UTF-8 correctamente
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, [
                'Código',
                'Nombre',
                'Empresa',
                'Tipo de proceso',
                'Tipo de documento',
                'Nivel de impacto',
                'Estado del flujo',
                'Estado de vigencia',
                'Versión actual',
                'Fecha de emisión',
                'Fecha de vigencia',
                'Días para vencer',
                'Responsable de versión',
                'Creado por',
                'Fecha de creación',
                'Paso actual del flujo',
            ]);

            foreach ($regulations as $r) {
                $version = $r->currentVersion;

                // Días para vencer
                if ($version?->valid_until) {
                    $days = (int) now()->diffInDays($version->valid_until, false);
                    $daysLeft = $days >= 0
                        ? $days
                        : 'Vencido hace ' . abs($days) . ' día(s)';
                } else {
                    $daysLeft = '—';
                }

                // Paso actual del flujo (mínimo paso pendiente)
                $currentStep = $r->approvals->where('status', 'pending')->min('step_number');

                fputcsv($handle, [
                    $r->code                        ?? '—',
                    $r->name,
                    $r->company?->name              ?? '—',
                    $r->processType?->name          ?? '—',
                    $r->document_type               ?? '—',
                    $r->impactLevelLabel()           ?: '—',
                    $r->approvalStatusLabel()        ?: 'Sin flujo asignado',
                    $version ? $r->statusLabel()    : 'Sin versión',
                    $version ? 'v' . $version->version_number : '—',
                    $version?->issued_at?->format('d/m/Y')    ?? '—',
                    $version?->valid_until?->format('d/m/Y')  ?? '—',
                    $daysLeft,
                    $version?->responsible_name     ?? '—',
                    $r->creator?->name              ?? '—',
                    $r->created_at->format('d/m/Y'),
                    $currentStep ? 'Paso ' . $currentStep : '—',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
