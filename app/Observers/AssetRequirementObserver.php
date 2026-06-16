<?php

namespace App\Observers;

use App\Models\AssetRequirement;
use Illuminate\Support\Facades\Cache;

class AssetRequirementObserver
{
    public function saved(AssetRequirement $req): void
    {
        $this->clearCache($req);
    }

    public function deleted(AssetRequirement $req): void
    {
        $this->clearCache($req);
    }

    private function clearCache(AssetRequirement $req): void
    {
        $companyId = $req->company_id ?? $req->asset?->company_id;
        if ($companyId) {
            Cache::forget("dashboard:compliance:{$companyId}");
            // Limpia todas las variantes usuario/all_tasks de esa empresa
            foreach (range(0, 1) as $allTasks) {
                foreach (\App\Models\User::where('company_id', $companyId)->pluck('id') as $uid) {
                    Cache::forget("dashboard:compliance:{$companyId}:u{$uid}:{$allTasks}");
                }
            }
        }
    }
}
