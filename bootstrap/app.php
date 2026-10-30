<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'api.token'      => \App\Http\Middleware\ValidateApiToken::class,
            'module.access'  => \App\Http\Middleware\EnsureModuleAccess::class,
            'license.active' => \App\Http\Middleware\EnsureLicenseActive::class,
        ]);

        $middleware->web(append: [
            \App\Http\Middleware\PreventStalePageCache::class,
            \App\Http\Middleware\RestoreExpiredFormInput::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // "Page Expired" (419): el formulario se envió con un token CSRF que ya no es válido —
        // casi siempre porque la sesión expiró mientras la página estaba abierta. En vez de la
        // pantalla genérica de Laravel (que además pierde lo capturado), se regresa al usuario a
        // donde estaba con un aviso y con sus datos.
        $exceptions->render(function (HttpException $e, Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson()) {
                return null;
            }

            $input = $request->except(['_token', 'password', 'password_confirmation']);

            // La sesión sigue activa (ej. se volvió a iniciar sesión en otra pestaña): basta con
            // volver a la página, que ya trae un token nuevo, y reenviar.
            if ($request->user()) {
                return redirect()->back()
                    ->withInput($input)
                    ->with('session_notice', 'La página llevaba mucho tiempo abierta y no se pudo enviar. Revisa tus datos y vuelve a enviar.');
            }

            // El POST de login no tiene nombre de ruta (solo el GET se llama "login").
            if ($request->is('login')) {
                return redirect()->route('login')
                    ->withInput($request->only('email'))
                    ->with('status', 'La página de inicio de sesión estuvo abierta mucho tiempo. Vuelve a intentarlo.');
            }

            // La sesión expiró: al volver a entrar, se regresa a la página donde estaba y con lo que
            // había capturado (RestoreExpiredFormInput lo devuelve como old() en esa página).
            $previous = url()->previous();
            if (str_starts_with($previous, $request->getSchemeAndHttpHost())) {
                session()->put('url.intended', $previous);
                session()->put('expired_form_input', ['url' => $previous, 'input' => $input]);
            }

            return redirect()->route('login')
                ->with('status', 'Tu sesión expiró por inactividad. Inicia sesión de nuevo para continuar.');
        });
    })->create();
