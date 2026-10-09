<?php

namespace App\Observers;

use App\Http\Controllers\ProcessesDashboardController;
use App\Models\Regulation;
use Illuminate\Support\Facades\Cache;

class RegulationObserver
{
    public function saved(Regulation $regulation): void
    {
        $this->clearCache($regulation);
    }

    public function deleted(Regulation $regulation): void
    {
        $this->clearCache($regulation);
    }

    public function restored(Regulation $regulation): void
    {
        $this->clearCache($regulation);
    }

    public function forceDeleted(Regulation $regulation): void
    {
        $this->clearCache($regulation);
    }

    private function clearCache(Regulation $regulation): void
    {
        $prefix = ProcessesDashboardController::CACHE_PREFIX;

        if ($regulation->company_id) {
            Cache::forget("{$prefix}c{$regulation->company_id}");
        }
        if ($regulation->group_id) {
            Cache::forget("{$prefix}g{$regulation->group_id}");
        }
    }
}
