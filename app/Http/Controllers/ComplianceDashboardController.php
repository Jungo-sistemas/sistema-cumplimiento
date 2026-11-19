<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Application\Compliance\ComplianceLevelService;
use App\Models\AssetType;
use App\Support\StreamingXlsxWriter;
use App\Models\Company;

class ComplianceDashboardController extends Controller
{
    public function index(Request $request, ComplianceLevelService $levelService)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        // Los roles que no son admin solo tienen la Vista de auditado.
        if ($user->isAuditeeOnly()) {
            return redirect()->route('mi-espacio.home');
        }

        $companies = $this->visibleCompanies($user);

        // Nivel de cumplimiento de todo lo que el usuario ve, con los filtros del tablero.
        $levelFilters = ComplianceLevelService::filtersFrom($request->query(), $companies->pluck('id')->all());
        $level = $levelService->summary($companies->pluck('id')->all(), $levelFilters);
        $assetTypes = AssetType::orderBy('name')->get(['id', 'name']);
        $upcoming = $levelService->upcoming($companies->pluck('id')->all(), $levelFilters);
        $authorities = $levelService->authorities();

        return view('dashboard', compact('companies', 'level', 'levelFilters', 'assetTypes', 'upcoming', 'authorities'));
    }

    /**
     * Excel del nivel de cumplimiento con los mismos filtros del tablero, como evidencia: resumen
     * (general, por impacto, por entidad, por empresa y por tipo de activo) y el detalle de cada requerimiento.
     */
    public function exportLevel(Request $request, ComplianceLevelService $levelService)
    {
        $user = $request->user();
        abort_if($user->isSuperAdmin() || $user->isAuditeeOnly(), 403);

        $companies = $this->visibleCompanies($user);
        $companyIds = $companies->pluck('id')->all();
        $filters = ComplianceLevelService::filtersFrom($request->query(), $companyIds);
        $level = $levelService->summary($companyIds, $filters);

        $filterText = [
            'Empresa'        => $filters['company_id'] ? $companies->firstWhere('id', $filters['company_id'])?->name : 'Todas',
            'Tipo de activo' => $filters['asset_type_id'] ? (AssetType::find($filters['asset_type_id'])?->name ?? '—') : 'Todos',
            'Impacto'        => $filters['impact'] ? ComplianceLevelService::IMPACTS[$filters['impact']] : 'Todos',
            'Entidad'        => match ($filters['authority']) {
                null                                   => 'Todas',
                ComplianceLevelService::NO_AUTHORITY   => ComplianceLevelService::NO_AUTHORITY_LABEL,
                default                                => $filters['authority'],
            },
        ];

        $xlsx = new StreamingXlsxWriter();
        $states = ComplianceLevelService::STATES;
        $pct = fn (array $row) => $row['pct'] === null ? '—' : ['v' => $row['pct'] / 100, 's' => 'percent'];
        $tableHeader = array_merge(['', 'Requerimientos'], array_values($states), ['% cumplimiento']);
        $tableRow = fn (string $name, array $row) => array_merge(
            [$name, $row['total']],
            array_map(fn ($k) => $row[$k], array_keys($states)),
            [$pct($row)]
        );

        // ── Hoja 1: Resumen ──────────────────────────────────────────────────
        $xlsx->startSheet('Resumen', [42, 16, 13, 13, 13, 13, 16]);
        $xlsx->addRow(['Nivel de cumplimiento de requerimientos'], 'title');
        $xlsx->addRow(['Generado', now()->format('d/m/Y H:i') . ' por ' . $user->name]);
        foreach ($filterText as $label => $value) {
            $xlsx->addRow([$label, $value]);
        }
        $xlsx->addRow(['Cumplido = completado con documento vigente. Vencido = documento vencido o fecha límite pasada sin completar. No cuentan los cancelados.']);
        $xlsx->blankRow();

        $xlsx->addRow(['General'] + array_slice($tableHeader, 1, null, true), 'header');
        $xlsx->addRow($tableRow('Total', $level['overall']), 'bold');
        $xlsx->blankRow();

        $sections = [
            'Por impacto'        => $level['by_impact'],
            'Por entidad'        => $level['by_authority'],
            'Por empresa'        => $level['by_company'],
            'Por tipo de activo' => $level['by_type'],
        ];
        foreach ($sections as $title => $rows) {
            $xlsx->addRow([$title] + array_slice($tableHeader, 1, null, true), 'header');
            foreach ($rows as $row) {
                $xlsx->addRow($tableRow($row['name'], $row));
            }
            $xlsx->blankRow();
        }

        // ── Hoja 2: Detalle ──────────────────────────────────────────────────
        $xlsx->startSheet('Detalle', [34, 18, 34, 16, 48, 14, 12, 12, 13, 15, 40]);
        $xlsx->addRow(['Empresa', 'Tipo de activo', 'Activo', 'No. económico / código', 'Requerimiento', 'Entidad', 'Impacto',
            'Estado', 'Fecha límite', 'Vigencia del documento', 'Documento vigente'], 'header');
        foreach ($levelService->detail($companyIds, $filters) as $row) {
            $xlsx->addRow(array_values($row));
        }

        $path = tempnam(sys_get_temp_dir(), 'cumplimiento_') . '.xlsx';
        $xlsx->save($path);

        return response()->download($path, 'Nivel de cumplimiento ' . now()->format('Y-m-d') . '.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    /** Empresas que el usuario ve en Cumplimiento. */
    private function visibleCompanies($user)
    {
        return Company::query()
            ->when($user->isGlobalScope(), function ($query) {
                // global-scope non-superadmin: see all companies
            }, function ($query) use ($user) {
                if ($user->hasGroupScope()) {
                    $query->where('group_id', $user->group_id);
                } elseif ($user->hasMultipleCompanies()) {
                    $query->whereIn('id', $user->accessibleCompanyIds());
                } else {
                    $query->where('id', $user->company_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}