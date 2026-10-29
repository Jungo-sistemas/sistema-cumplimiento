<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Con el "no-cache, private" que Laravel manda por defecto, el botón "Atrás" del navegador
 * muestra la copia guardada de la página en vez de pedirla otra vez: tras editar un documento y
 * regresar a la lista, se veía la lista vieja sin el cambio. Con "no-store" el navegador siempre
 * vuelve a pedir la página. Solo aplica a páginas con sesión iniciada; los archivos (vistas
 * previas y descargas) se dejan como estaban.
 */
class PreventStalePageCache
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            $request->user()
            && ! $response instanceof BinaryFileResponse
            && ! $response instanceof StreamedResponse
        ) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, private');
        }

        return $response;
    }
}
