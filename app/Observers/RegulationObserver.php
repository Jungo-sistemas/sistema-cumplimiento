<?php

namespace App\Observers;

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

    private function clearCache(Regulation $regulation): void
    {
        if ($regulation->company_id) {
            Cache::forget("dashboard:processes:c{$regulation->company_id}");
        }
        if ($regulation->group_id) {
            Cache::forget("dashboard:processes:g{$regulation->group_id}");
        }
    }
}
