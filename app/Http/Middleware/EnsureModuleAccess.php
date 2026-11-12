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
            'auditorias.*',
            'mi-espacio.*',
            'gestion.*',
        ],
        'cumplimiento' => [
            'assets.*',
            'requirements.*',
            'tasks.*',
            'documents.*',
            'investment-requests.*',
            'dashboard',
        ],
    ];

    /**
     * Lo único de Procesos y Auditorías a lo que entra quien no tiene Gestión ahí (solo lectura, o
     * admin/operativo "Solo Cumplimiento"): Mi espacio y lo que este usa — consultar, imprimir y
     * descargar sus procedimientos, y aprobar o rechazar lo que le toca. Crear, editar, flujos,
     * papelera, Gestión y la vista del auditor quedan fuera aunque sea admin. Los controladores
     * validan además que el proceso le toque (empresa, aprobación asignada, mención…).
     */
    private const AUDITEE_ROUTES = [
        'mi-espacio.*',
        'my-approvals.*',
        'processes.show',
        'processes.print',
        'processes.flow',
        'processes.approve',
        'processes.reject',
        'processes.changeSummary.show',
        'processes.changeEvidence.show',
        'processes.view-track',
        'regulation-versions.preview',
        'regulation-versions.download',
    ];

    /**
     * Aprobar Anexos 8: los aprobadores son fijos por razón social y alguno puede no tener
     * Cumplimiento (p. ej. "Solo Procesos"). Entran solo a esto; el controlador limita a quien no
     * tiene Cumplimiento a las solicitudes donde está en el flujo.
     */
    private const INVESTMENT_APPROVER_ROUTES = [
        'investment-requests.my-approvals',
        'investment-requests.show',
        'investment-requests.preview',
        'investment-requests.download',
        'investment-requests.approve',
        'investment-requests.reject',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        $module = $this->detectModule($request);

        if ($module === 'procesos') {
            // Paywall: Procesos requiere que la licencia de la empresa/grupo lo incluya. A propósito
            // esto NO se salta con isAdmin() — un "admin" del cliente tampoco tiene acceso si su
            // organización no pagó el módulo. Solo el superadmin (Vigia) queda exento.
            if (! app(LicenseService::class)->hasProcesosAccessForUser($user)) {
                return redirect($this->fallbackUrl($user))
                    ->with('error', 'El módulo de Procesos no está incluido en la licencia de tu empresa.');
            }

            // Todos entran a Procesos y Auditorías, pero sin Gestión solo a Mi espacio. Aplica
            // también a admins "Solo Cumplimiento": no intervienen en lo de los admins de Procesos.
            if (! $user->hasProcesosGestion() && ! $request->routeIs(...self::AUDITEE_ROUTES)) {
                abort_unless($request->isMethod('GET'), 403, 'No tienes acceso a la gestión de Procesos.');

                return redirect()->route('mi-espacio.home');
            }

            return $next($request);
        }

        // Cumplimiento: cada quien en su sección, admins incluidos.
        if ($module !== null && ! $user->canAccessModule($module) && ! $request->routeIs(...self::INVESTMENT_APPROVER_ROUTES)) {
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
            return route('gestion.home');
        }

        return route('dashboard');
    }
}
