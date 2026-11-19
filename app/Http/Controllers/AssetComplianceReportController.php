<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\RequirementTemplate;
use Illuminate\Http\Request;
use App\Support\StreamingXlsxWriter;

/**
 * Reporte de cumplimiento en Excel para uno o varios activos seleccionados desde
 * assets/index.blade.php (mismo patrón de selección que ProcessReportController, pero
 * generando un .xlsx real en vez de CSV).
 *
 * El estado "cumplido/no cumplido" replica exactamente la misma lógica de
 * AssetController::show() (has_official_document + riesgo por vencimiento + avance de
 * tareas), para que el reporte no contradiga lo que ya se ve en la app.
 */
class AssetComplianceReportController extends Controller
{
    private const STATUS_LABELS = [
        'missing_document' => 'Falta documento oficial',
        'pending'          => 'Pendiente',
        'in_progress'      => 'En progreso',
        'in_transit'       => 'En trámite',
        'completed'        => 'Completado',
        'expired'          => 'Vencido',
        'cancelled'        => 'Cancelado',
    ];

    public function export(Request $request)
    {
        $user = $request->user();

        // Por ahora, solo administradores — a diferencia del resto de reportes del módulo
        // (Procesos sí deja entrar a operativos), este se restringe más mientras se valida
        // con el cliente quién más debería tener acceso.
        abort_unless($user->isAdmin(), 403);

        $ids = array_filter(array_map('intval', $request->input('asset_ids', [])));
        abort_if(empty($ids), 422, 'Selecciona al menos un activo.');

        $assetsQuery = Asset::query()
            ->whereIn('id', $ids)
            ->with(['type:id,name', 'company:id,name,group_id', 'responsibleUser:id,name']);

        if ($user->hasGroupScope()) {
            $assetsQuery->whereHas('company', fn ($q) => $q->where('group_id', $user->group_id));
        } elseif ($user->hasMultipleCompanies()) {
            $assetsQuery->whereIn('company_id', $user->accessibleCompanyIds());
        } else {
            $assetsQuery->where('company_id', $user->company_id);
        }

        $assets = $assetsQuery->orderBy('name')->get();

        abort_if($assets->isEmpty(), 403);

        // Se escribe en streaming y activo por activo: ahora se pueden elegir todos los activos de
        // una empresa o del grupo (decenas de miles de requerimientos), y armarlo todo en
        // PhpSpreadsheet agotaba la memoria.
        // Todo el grupo (~850 activos) tarda unos 20 s; margen para que no lo corte el límite de PHP.
        @set_time_limit(300);

        $xlsx = new StreamingXlsxWriter('1A428A');

        $xlsx->startSheet('Resumen', [34, 34, 18, 26, 12, 12, 14, 11, 15]);
        $xlsx->addRow(['Empresa', 'Activo', 'Tipo', 'Responsable', 'Total requerimientos', 'Cumplidos',
            '% Cumplimiento', 'Vencidos', 'Falta documento'], 'header');

        $xlsx->startSheet('Detalle', [30, 30, 18, 24, 44, 18, 18, 11, 20, 22, 10, 12, 22, 16, 14, 12, 16]);
        $xlsx->addRow([
            'Empresa', 'Activo', 'Tipo de activo', 'Responsable del activo',
            'Requerimiento', 'Categoría', 'Entidad', 'Prioridad', 'Área responsable',
            'Estado', 'Cumplido', 'Documento cargado', 'Subido por',
            'Fecha de subida', 'Fecha de emisión', 'Vigencia', 'Días para vencer',
        ], 'header');

        // Requerimientos por bloques de activos (no una consulta por activo).
        $requirementsByAsset = collect();
        foreach ($assets as $index => $asset) {
            if ($index % 100 === 0) {
                $requirementsByAsset = $this->requirementsFor($assets->slice($index, 100)->pluck('id')->all());
            }

            $rows = $this->buildRows($asset, $requirementsByAsset->get($asset->id, collect()));

            $xlsx->useSheet('Detalle');
            foreach ($rows as $r) {
                $xlsx->addRow($this->detailRow($r));
            }

            $total = count($rows);
            $cumplidos = count(array_filter($rows, fn ($r) => $r['cumplido'] === 'Sí'));

            $xlsx->useSheet('Resumen');
            $xlsx->addRow([
                $asset->company?->name ?? '—',
                $asset->display_name,
                $asset->type?->name ?? '—',
                $asset->responsibleUser?->name ?? '—',
                $total,
                $cumplidos,
                ['v' => $total > 0 ? round($cumplidos / $total, 3) : 0, 's' => 'percent'],
                count(array_filter($rows, fn ($r) => $r['estado'] === self::STATUS_LABELS['expired'])),
                count(array_filter($rows, fn ($r) => $r['documento_cargado'] === 'No')),
            ]);
        }

        $path = tempnam(sys_get_temp_dir(), 'reporte_') . '.xlsx';
        $xlsx->save($path);

        $filename = 'reporte_cumplimiento_' . now()->format('Y-m-d_His') . '.xlsx';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Requerimientos de varios activos, agrupados por activo. */
    private function requirementsFor(array $assetIds)
    {
        return AssetRequirement::query()
            ->whereIn('asset_id', $assetIds)
            ->with([
                'template',
                'currentDocument.uploader:id,name',
                'latestDocument.uploader:id,name',
            ])
            ->withCount([
                'tasks as tasks_total' => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'tasks as tasks_done'  => fn ($q) => $q->where('status', TaskStatus::COMPLETED),
            ])
            ->get()
            ->groupBy('asset_id');
    }

    /**
     * Una fila por requerimiento del activo, ya con el status calculado igual que la
     * vista de detalle del activo (ver AssetController::show()).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(Asset $asset, $requirements): array
    {
        $requirements = $requirements
            ->sortBy(fn ($r) => sprintf(
                '%s|%02d-%s',
                $r->template?->authority ?: 'zzz-sin-entidad',
                $r->template?->subtype_rank ?? 99,
                $r->template?->name ?? ''
            ))
            ->values();

        // Días con aritmética de fechas (UTC, sin horario de verano) en vez de Carbon por fila: con
        // decenas de miles de requerimientos, diffInDays() era lo que más tardaba del reporte.
        $today = strtotime(now()->format('Y-m-d') . ' UTC');

        return $requirements->map(function (AssetRequirement $requirement) use ($asset, $today) {
            $expiresAt = $requirement->expires_at ?? $requirement->due_date;

            $riskLevel = 'normal';
            $daysToExpire = null;

            if ($expiresAt) {
                $daysToExpire = intdiv(strtotime($expiresAt->format('Y-m-d') . ' UTC') - $today, 86400);

                if ($daysToExpire < 0) {
                    $riskLevel = 'danger';
                } elseif ($daysToExpire <= 30) {
                    $riskLevel = 'warning';
                }
            }

            $tasksTotal = (int) ($requirement->tasks_total ?? 0);
            $tasksDone = (int) ($requirement->tasks_done ?? 0);
            $hasOfficialDocument = ! is_null($requirement->current_document_id);

            $computedStatus = $requirement->status?->value ?? 'pending';

            if ($computedStatus === 'in_transit') {
                // se preserva
            } elseif (! $hasOfficialDocument) {
                $computedStatus = 'missing_document';
            } elseif ($riskLevel === 'danger') {
                $computedStatus = 'expired';
            } elseif ($tasksTotal > 0 && $tasksDone === $tasksTotal) {
                $computedStatus = 'completed';
            } elseif ($tasksDone > 0 && $tasksDone < $tasksTotal) {
                $computedStatus = 'in_progress';
            } else {
                $computedStatus = $computedStatus ?: 'pending';
            }

            $document = $requirement->currentDocument ?? $requirement->latestDocument;

            return [
                'empresa'            => $asset->company?->name ?? '—',
                'activo'             => $asset->display_name,
                'tipo_activo'        => $asset->type?->name ?? '—',
                'responsable_activo' => $asset->responsibleUser?->name ?? '—',
                'requerimiento'      => $requirement->template?->name ?? '—',
                'categoria'          => RequirementTemplate::CATEGORIES[$requirement->template?->category] ?? ($requirement->template?->category ?? '—'),
                'entidad'            => $requirement->template?->authority ?: '—',
                'prioridad'          => RequirementTemplate::PRIORITIES[$requirement->template?->priority] ?? ($requirement->template?->priority ?? '—'),
                'area_responsable'   => $requirement->template?->responsible_area ?: '—',
                'estado'             => self::STATUS_LABELS[$computedStatus] ?? $computedStatus,
                'cumplido'           => $computedStatus === 'completed' ? 'Sí' : 'No',
                'documento_cargado'  => $hasOfficialDocument ? 'Sí' : 'No',
                'subido_por'         => $document?->uploader?->name ?? '—',
                'fecha_subida'       => $document?->uploaded_at ?? $document?->created_at,
                'fecha_emision'      => $document?->issued_at,
                'vigencia'           => $expiresAt,
                'dias_para_vencer'   => $daysToExpire,
            ];
        })->all();
    }

    private function detailRow(array $r): array
    {
        return [
            $r['empresa'],
            $r['activo'],
            $r['tipo_activo'],
            $r['responsable_activo'],
            $r['requerimiento'],
            $r['categoria'],
            $r['entidad'],
            $r['prioridad'],
            $r['area_responsable'],
            $r['estado'],
            $r['cumplido'],
            $r['documento_cargado'],
            $r['subido_por'],
            $r['fecha_subida']?->format('d/m/Y H:i') ?? '—',
            $r['fecha_emision']?->format('d/m/Y') ?? '—',
            $r['vigencia']?->format('d/m/Y') ?? '—',
            $r['dias_para_vencer'] === null ? '—' : (
                $r['dias_para_vencer'] >= 0
                    ? $r['dias_para_vencer']
                    : 'Vencido hace ' . abs($r['dias_para_vencer']) . ' día(s)'
            ),
        ];
    }
}
