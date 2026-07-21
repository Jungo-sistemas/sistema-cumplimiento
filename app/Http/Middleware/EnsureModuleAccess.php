<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleAccess
{
    /**
     * Route name patterns that belong to each module.
     * Extend this map when new modules are added.
     */
    private const MODULE_ROUTES = [
        'procesos' => [
            'processes.*',
            'procesos.*',
            'my-approvals.*',
            'regulation-versions.*',
        ],
        'cumplimiento' => [
            'assets.*',
            'requirements.*',
            'tasks.*',
            'documents.*',
            'dashboard',
        ],
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        $module = $this->detectModule($request);

        // Paywall: Procesos requiere que la licencia de la empresa/grupo lo incluya. A propósito
        // esto NO se salta con isAdmin() — un "admin" del cliente tampoco tiene acceso si su
        // organización no pagó el módulo. Solo el superadmin (Vigia) queda exento.
        if ($module === 'procesos' && !$user->isSuperAdmin() && !app(LicenseService::class)->hasProcesosAccessForUser($user)) {
            return redirect($this->fallbackUrl($user))
                ->with('error', 'El módulo de Procesos no está incluido en la licencia de tu empresa.');
        }

        // Admins and superadmins are never restricted by module_access.
        if ($user->isAdmin()) {
            return $next($request);
        }

        if ($module !== null && !$user->canAccessModule($module)) {
            return redirect($this->fallbackUrl($user))
                ->with('error', 'No tienes acceso a este módulo.');
        }

        return $next($request);
    }

    private function detectModule(Request $request): ?string
    {
        foreach (self::MODULE_ROUTES as $module => $patterns) {
            if ($request->routeIs(...$patterns)) {
                return $module;
            }
        }

        // Shared routes (users, profile, superadmin, etc.) — no restriction.
        return null;
    }

    private function fallbackUrl(mixed $user): string
    {
        $modules = $user->accessibleModules();

        if (in_array('cumplimiento', $modules, true)) {
            return route('assets.index');
        }

        if (in_array('procesos', $modules, true)) {
            return route('processes.dashboard');
        }

        return route('dashboard');
    }
}
