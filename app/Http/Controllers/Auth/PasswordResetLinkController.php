<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    /**
     * Display the password reset link request view.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Handle an incoming password reset link request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Escribe tu correo electrónico.',
            'email.email'    => 'Escribe un correo electrónico válido.',
        ]);

        // Siempre la misma respuesta, exista o no el correo (o si se pidió otro enlace hace menos
        // de un minuto): antes el formulario decía "no encontramos ese usuario" y con eso
        // cualquiera podía probar correos para saber cuáles tienen cuenta. Por la misma razón, si
        // el envío falla se registra en el log en vez de mostrar un error que solo saldría con
        // correos que sí existen.
        try {
            Password::sendResetLink($request->only('email'));
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de restablecimiento de contraseña', [
                'email' => $request->email,
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('status', 'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña. Revisa tu bandeja de entrada y la carpeta de correo no deseado.');
    }
}
