<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator;
use App\Models\Asset;
use App\Models\Regulation;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
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
        // Fechas en español ("hace 2 meses", nombres de mes) sin cambiar APP_LOCALE, que dejaría
        // los mensajes de validación sin traducción (no hay lang/es).
        \Illuminate\Support\Carbon::setLocale('es');
        Asset::observe(AssetObserver::class);
        Regulation::observe(RegulationObserver::class);
        // Pantallas del auditor (/auditorias): solo auditores y admins, igual que el menú.
        Gate::define('auditorias', fn (User $user) => $user->isAuditor() || $user->isAdmin());
    }
}
