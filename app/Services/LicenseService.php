<?php

namespace App\Services;

use App\Models\Asset;
use App\Models\Company;
use App\Models\Group;
use App\Models\License;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

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

    /** Monto fijo mensual del módulo de Procesos, sin importar el plan de activos. */
    public const PROCESOS_ADDON_PRICE = 6_000.0;

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

    /**
     * A quién pertenece la licencia efectiva de una empresa: si pertenece a un grupo, el grupo
     * (mismo criterio de precedencia que ya se usa para asset_limit — el grupo manda cuando existe);
     * si no, la propia empresa. Un grupo licencia a todas sus empresas de un solo golpe.
     */
    public function resolveLicensable(Company $company): Model
    {
        $company->loadMissing('group');

        return ($company->group_id && $company->group) ? $company->group : $company;
    }

    /** Última licencia registrada (de cualquier estado) para esa empresa/grupo, o null si nunca se activó una. */
    public function currentLicense(Model $licensable): ?License
    {
        return $licensable->licenses()->latest('activated_at')->first();
    }

    /**
     * Si nunca se le ha activado una licencia, no se restringe nada (así ningún cliente actual
     * se queda sin acceso de golpe al lanzar esta funcionalidad). En cuanto existe una licencia,
     * manda ella: activa y vigente, o no.
     */
    public function isAccessAllowed(Model $licensable): bool
    {
        $license = $this->currentLicense($licensable);

        if (! $license) {
            return true;
        }

        return $license->status === 'active' && $license->expires_at->isFuture();
    }

    /** Mismo criterio "sin licencia = sin restricción" aplicado al módulo de Procesos. */
    public function hasProcesosAccess(Company $company): bool
    {
        return $this->licenseGrantsProcesos($this->currentLicense($this->resolveLicensable($company)));
    }

    /**
     * Igual que hasProcesosAccess() pero a partir de un usuario — cubre tanto a quienes tienen
     * company_id (la mayoría) como a quienes solo tienen group_id (usuarios con scope de grupo,
     * sin empresa asignada).
     */
    public function hasProcesosAccessForUser(User $user): bool
    {
        if ($user->company) {
            return $this->hasProcesosAccess($user->company);
        }

        if ($user->group) {
            return $this->licenseGrantsProcesos($this->currentLicense($user->group));
        }

        return true;
    }

    private function licenseGrantsProcesos(?License $license): bool
    {
        if (! $license) {
            return true;
        }

        return $license->status === 'active'
            && $license->expires_at->isFuture()
            && $license->includes_procesos;
    }

    /** Precio mensual total: el del plan de activos (según su límite) + el fijo de Procesos si aplica. */
    public function priceFor(?int $assetLimit, bool $includesProcesos): float
    {
        $plan = self::planForLimit($assetLimit);
        $base = (float) ($plan['price_total'] ?? 0.0);

        return $base + ($includesProcesos ? self::PROCESOS_ADDON_PRICE : 0.0);
    }

    /** Activa (o renueva) la licencia de una empresa o grupo por un ciclo de 1 mes desde hoy. */
    public function activate(Model $licensable, bool $includesProcesos, User $activatedBy): License
    {
        $assetLimit = $licensable instanceof Group ? $licensable->asset_limit : $this->getLimit($licensable);

        $license = $licensable->licenses()->create([
            'includes_procesos' => $includesProcesos,
            'price'             => $this->priceFor($assetLimit, $includesProcesos),
            'status'            => 'active',
            'activated_at'      => now(),
            'expires_at'        => now()->addMonth(),
            'activated_by'      => $activatedBy->id,
        ]);

        $licensable->update(['is_active' => true]);

        return $license;
    }
}
