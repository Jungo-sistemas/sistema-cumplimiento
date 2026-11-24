<?php

namespace App\Http\Controllers;

use App\Support\PortalModules;

/** Secciones del menú que todavía no se construyen (Fase 1, Marcas, Compras, Cursos…). */
class ComingSoonController extends Controller
{
    public function __invoke(string $section)
    {
        $page = PortalModules::COMING_SOON[$section] ?? abort(404);

        return view('coming-soon', [
            'section'     => $page,
            'moduleLabel' => PortalModules::LABELS[$page['module']],
        ]);
    }
}
