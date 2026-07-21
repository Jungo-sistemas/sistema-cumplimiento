<?php

namespace App\Http\Middleware;

use App\Services\LicenseService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLicenseActive
{
    public function __construct(private LicenseService $licenseService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        // El superadmin (Vigia) es quien gestiona las licencias — nunca se ve afectado por esto.
        // A propósito NO se exceptúa a "admin" aquí: si el admin pertenece al cliente cuya
        // licencia venció, también pierde acceso (es justo a quien hay que cortarle el acceso).
        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if ($this->isLicenseActive($user)) {
            return $next($request);
        }

        auth()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'El acceso fue suspendido por falta de pago de la licencia. Contacta a tu administrador.');
    }

    private function isLicenseActive(mixed $user): bool
    {
        if ($user->company) {
            return (bool) $this->licenseService->resolveLicensable($user->company)->is_active;
        }

        if ($user->group) {
            return (bool) $user->group->is_active;
        }

        return true;
    }
}
