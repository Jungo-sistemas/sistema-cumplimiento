<?php

namespace App\Application\Compliance;

use App\Enums\RequirementStatus;
use App\Models\AssetRequirement;
use App\Models\Company;
use App\Models\User;

final class ComplianceDashboardService
{
    public function metricsForCompany(Company|int $company): array
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        // 🔹 KPIs
        $base = AssetRequirement::query()->forCompany($companyId);

        $total = (clone $base)->count();
        $expired = (clone $base)->expired()->count();
        $danger = (clone $base)->critical()->count();
        $dueSoon = (clone $base)->dueSoon()->count();
        $warning = max(0, $dueSoon - $danger);

        // 🔹 BREAKDOWN POR ASSET TYPE — agregado en SQL, sin cargar filas
        $rows = AssetRequirement::query()
            ->forCompany($companyId)
            ->join('assets', 'asset_requirements.asset_id', '=', 'assets.id')
            ->join('asset_types', 'assets.asset_type_id', '=', 'asset_types.id')
            ->selectRaw('
                asset_types.name as asset_type,
                COUNT(*) as total,
                SUM(CASE WHEN asset_requirements.risk_level = ? THEN 1 ELSE 0 END) as expired,
                SUM(CASE WHEN asset_requirements.risk_level = ? THEN 1 ELSE 0 END) as danger,
                SUM(CASE WHEN asset_requirements.risk_level = ? THEN 1 ELSE 0 END) as warning
            ', ['expired', 'danger', 'warning'])
            ->groupBy('asset_types.id', 'asset_types.name')
            ->orderBy('asset_types.name')
            ->get();

        $byAssetType = $rows->map(fn ($r) => [
            'asset_type' => $r->asset_type,
            'total'      => (int) $r->total,
            'expired'    => (int) $r->expired,
            'danger'     => (int) $r->danger,
            'warning'    => (int) $r->warning,
        ])->all();

        // 🔹 RETURN AL FINAL
        return [
            'kpis' => [
                'total' => $total,
                'expired' => $expired,
                'danger' => $danger,
                'warning' => $warning,
            ],
            'breakdowns' => [
                'by_asset_type' => $byAssetType,
            ],
        ];
    }



    public function metricsForUser(User $user): array
    {
        return $this->metricsForCompany($user->company);
    }

    private function mapItem($r): array
    {
        return [
            'id' => $r->id,
            'status' => $r->status->value,
            'due_date' => optional($r->due_date)->toDateString(),
            'risk_level' => $r->risk_level,

            'asset' => [
                'id' => $r->asset?->id,
                'name' => $r->asset?->name,
                'asset_type' => [
                    'id' => $r->asset?->assetType?->id,
                    'name' => $r->asset?->assetType?->name,
                ],
            ],

            'template' => [
                'id' => $r->template?->id,
                'name' => $r->template?->name,
            ],
        ];
    }
}
