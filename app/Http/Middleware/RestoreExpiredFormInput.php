<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Si un formulario se envió con la sesión ya expirada ("Page Expired", ver el render del 419 en
 * bootstrap/app.php), lo capturado se guarda en la sesión junto con la página de donde venía.
 * Cuando el usuario vuelve a iniciar sesión y regresa a esa página, se le devuelve como old()
 * para que no tenga que capturarlo otra vez.
 */
class RestoreExpiredFormInput
{
    public function handle(Request $request, Closure $next): Response
    {
        $saved = $request->hasSession() ? $request->session()->get('expired_form_input') : null;

        if ($saved && $request->user() && $request->isMethod('GET') && $request->fullUrl() === $saved['url']) {
            $request->session()->forget('expired_form_input');
            // now() y no flash(): solo para esta página; si recarga, ya no se vuelve a llenar.
            $request->session()->now('_old_input', $saved['input']);
            $request->session()->now('session_notice', 'Tu sesión había expirado. Recuperamos lo que habías capturado: revísalo y vuelve a enviarlo.');
        }

        return $next($request);
    }
}
