<?php

namespace App\Http\Controllers;

use App\Support\EmailPreviews;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * /dev/correos — vista previa de los correos con datos de ejemplo (App\Support\EmailPreviews).
 * Solo para desarrollo: las rutas se registran únicamente con APP_ENV=local (routes/web.php) y
 * aquí se vuelve a comprobar por si acaso. No envía nada ni muestra datos de clientes.
 */
class EmailPreviewController extends Controller
{
    public function index(Request $request, EmailPreviews $previews)
    {
        abort_unless(app()->environment('local'), 404);

        $catalog = collect($previews->catalog())
            ->map(fn ($entry, $key) => ['key' => $key, 'group' => $entry['group'], 'label' => $entry['label']]);

        $selected = $catalog->has($request->query('correo')) ? $request->query('correo') : $catalog->keys()->first();

        return view('dev.emails', [
            'groups'   => $catalog->groupBy('group'),
            'selected' => $catalog[$selected],
            'subject'  => $previews->render($selected)['subject'],
        ]);
    }

    /** El HTML del correo tal cual, para el iframe de la vista previa. */
    public function show(EmailPreviews $previews, string $key): Response
    {
        abort_unless(app()->environment('local'), 404);

        return response($previews->render($key)['html'])
            ->header('Content-Type', 'text/html; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'no-store');
    }
}
