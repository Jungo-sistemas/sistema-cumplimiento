<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\RequirementTemplate;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reporte de cumplimiento en Excel para uno o varios activos seleccionados desde
 * assets/index.blade.php (mismo patrón de selección que ProcessReportController, pero
 * generando un .xlsx real con PhpSpreadsheet en vez de CSV).
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

    public function export(Request $request): StreamedResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $ids = array_filter(array_map('intval', $request->input('asset_ids', [])));
        abort_if(empty($ids), 422, 'Selecciona al menos un activo.');

        $assetsQuery = Asset::query()
            ->whereIn('id', $ids)
            ->with(['type:id,name', 'company:id,name,group_id', 'responsibleUser:id,name']);

        if ($user->hasGroupScope()) {
            $assetsQuery->whereHas('company', fn ($q) => $q->where('group_id', $user->group_id));
        } else {
            $assetsQuery->where('company_id', $user->company_id);
        }

        $assets = $assetsQuery->orderBy('name')->get();

        abort_if($assets->isEmpty(), 403);

        $rowsByAsset = $assets->mapWithKeys(fn (Asset $asset) => [$asset->id => $this->buildRows($asset)]);

        $spreadsheet = new Spreadsheet();
        $this->buildSummarySheet($spreadsheet, $assets, $rowsByAsset);
        $this->buildDetailSheet($spreadsheet, $rowsByAsset);
        $spreadsheet->setActiveSheetIndex(0);

        $filename = 'reporte_cumplimiento_' . now()->format('Y-m-d_His') . '.xlsx';

        return new StreamedResponse(function () use ($spreadsheet) {
            (new Xlsx($spreadsheet))->save('php://output');
        }, 200, [
            'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Una fila por requerimiento del activo, ya con el status calculado igual que la
     * vista de detalle del activo (ver AssetController::show()).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildRows(Asset $asset): array
    {
        $requirements = AssetRequirement::query()
            ->where('asset_id', $asset->id)
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
            ->sortBy(fn ($r) => sprintf(
                '%s|%02d-%s',
                $r->template?->authority ?: 'zzz-sin-entidad',
                $r->template?->subtype_rank ?? 99,
                $r->template?->name ?? ''
            ))
            ->values();

        return $requirements->map(function (AssetRequirement $requirement) use ($asset) {
            $expiresAt = $requirement->expires_at ?? $requirement->due_date;

            $riskLevel = 'normal';
            $daysToExpire = null;

            if ($expiresAt) {
                $daysToExpire = (int) now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false);

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

    private function buildSummarySheet(Spreadsheet $spreadsheet, $assets, $rowsByAsset): void
    {
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Resumen');

        $headers = ['Empresa', 'Activo', 'Tipo', 'Responsable', 'Total requerimientos', 'Cumplidos', '% Cumplimiento', 'Vencidos', 'Falta documento'];
        $sheet->fromArray($headers, null, 'A1', true);
        $this->styleHeaderRow($sheet, count($headers));

        $row = 2;
        foreach ($assets as $asset) {
            $rows = $rowsByAsset[$asset->id];
            $total = count($rows);
            $cumplidos = count(array_filter($rows, fn ($r) => $r['cumplido'] === 'Sí'));
            $vencidos = count(array_filter($rows, fn ($r) => $r['estado'] === self::STATUS_LABELS['expired']));
            $sinDocumento = count(array_filter($rows, fn ($r) => $r['documento_cargado'] === 'No'));
            $porcentaje = $total > 0 ? round(($cumplidos / $total) * 100, 1) : 0;

            $sheet->fromArray([
                $asset->company?->name ?? '—',
                $asset->display_name,
                $asset->type?->name ?? '—',
                $asset->responsibleUser?->name ?? '—',
                $total,
                $cumplidos,
                $porcentaje . '%',
                $vencidos,
                $sinDocumento,
            ], null, "A{$row}", true);

            $row++;
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    private function buildDetailSheet(Spreadsheet $spreadsheet, $rowsByAsset): void
    {
        $sheet = $spreadsheet->createSheet();
        $sheet->setTitle('Detalle');

        $headers = [
            'Empresa', 'Activo', 'Tipo de activo', 'Responsable del activo',
            'Requerimiento', 'Categoría', 'Entidad', 'Prioridad', 'Área responsable',
            'Estado', 'Cumplido', 'Documento cargado', 'Subido por',
            'Fecha de subida', 'Fecha de emisión', 'Vigencia', 'Días para vencer',
        ];
        $sheet->fromArray($headers, null, 'A1', true);
        $this->styleHeaderRow($sheet, count($headers));

        $row = 2;
        foreach ($rowsByAsset as $rows) {
            foreach ($rows as $r) {
                $sheet->fromArray([
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
                ], null, "A{$row}", true);

                $row++;
            }
        }

        $this->autoSizeColumns($sheet, count($headers));
    }

    private function styleHeaderRow($sheet, int $columnCount): void
    {
        $lastColumn = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($columnCount);
        $range = "A1:{$lastColumn}1";

        $sheet->getStyle($range)->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1A428A');
        $sheet->getStyle($range)->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
    }

    private function autoSizeColumns($sheet, int $columnCount): void
    {
        for ($i = 1; $i <= $columnCount; $i++) {
            $sheet->getColumnDimensionByColumn($i)->setAutoSize(true);
        }
    }
}
