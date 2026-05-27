<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Company;

class LicenseService
{
    /**
     * Plan tiers in ascending order of limit.
     * limit = null means unlimited (Enterprise).
     */
    public const PLANS = [
        ['slug' => 'basic',      'name' => 'Básico',      'limit' => 50,  'price_unit' => 100, 'price_total' => 5_000],
        ['slug' => 'pro',        'name' => 'Profesional', 'limit' => 100, 'price_unit' => 90,  'price_total' => 9_000],
        ['slug' => 'business',   'name' => 'Empresarial', 'limit' => 500, 'price_unit' => 70,  'price_total' => 35_000],
        ['slug' => 'enterprise', 'name' => 'Enterprise',  'limit' => null, 'price_unit' => 50, 'price_total' => null],
    ];

    /**
     * Returns the plan definition matching a given asset_limit value, or null if custom.
     */
    public static function planForLimit(?int $limit): ?array
    {
        foreach (self::PLANS as $plan) {
            if ($plan['limit'] === $limit) {
                return $plan;
            }
        }
        return null;
    }

    /**
     * Returns the plan a company is currently on (based on its effective limit).
     */
    public function activePlan(Company $company): ?array
    {
        return self::planForLimit($this->getLimit($company));
    }

    /**
     * Returns the asset limit that applies to the given company.
     * - Company in a group with a group limit → group limit (shared).
     * - Otherwise → company's own limit.
     * - null means unlimited (Enterprise).
     */
    public function getLimit(Company $company): ?int
    {
        $company->loadMissing('group');

        if ($company->group_id && $company->group?->asset_limit !== null) {
            return $company->group->asset_limit;
        }

        return $company->asset_limit;
    }

    /**
     * Returns the current number of active assets counted against the license.
     * - Group license → counts all active assets across every company in the group.
     * - Company license → counts only that company's active assets.
     */
    public function getCurrentCount(Company $company): int
    {
        $company->loadMissing('group');

        if ($company->group_id && $company->group?->asset_limit !== null) {
            return Asset::query()
                ->whereHas('company', fn ($q) => $q->where('group_id', $company->group_id))
                ->where('status', 'active')
                ->count();
        }

        return Asset::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->count();
    }

    /** Returns true if a new asset can be created. */
    public function hasCapacity(Company $company): bool
    {
        $limit = $this->getLimit($company);
        return $limit === null || $this->getCurrentCount($company) < $limit;
    }

    /** Returns 'group' or 'company' depending on where the limit comes from. */
    public function getLicenseScope(Company $company): string
    {
        $company->loadMissing('group');
        return ($company->group_id && $company->group?->asset_limit !== null) ? 'group' : 'company';
    }

    /** Returns an array with all license info needed by views. */
    public function info(Company $company): array
    {
        $limit   = $this->getLimit($company);
        $current = $this->getCurrentCount($company);
        $scope   = $this->getLicenseScope($company);
        $plan    = self::planForLimit($limit);

        return [
            'limit'      => $limit,
            'current'    => $current,
            'remaining'  => $limit !== null ? max(0, $limit - $current) : null,
            'at_limit'   => $limit !== null && $current >= $limit,
            'scope'      => $scope,
            'percent'    => ($limit > 0) ? min(100, (int) round(($current / $limit) * 100)) : 0,
            'plan'       => $plan,
            'plan_name'  => $plan['name'] ?? ($limit === null ? 'Enterprise' : 'Personalizado'),
        ];
    }
}
