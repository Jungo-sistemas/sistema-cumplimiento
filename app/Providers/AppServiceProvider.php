<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\Asset;
use App\Models\Regulation;
use App\Observers\AssetObserver;
use App\Observers\RegulationObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useTailwind();
        Asset::observe(AssetObserver::class);
        Regulation::observe(RegulationObserver::class);
    }
}
