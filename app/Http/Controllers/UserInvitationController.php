<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserInvitationController extends Controller
{
    public function show(string $token): View
    {
        $user = User::with(['company', 'role'])
            ->where('invite_token', $token)
            ->first();

        if (! $user) {
            return $this->invitationError(
                'Este enlace de invitación no es válido. Pide a tu administrador que te envíe una nueva invitación.'
            );
        }

        if (! $user->invite_expires_at || now()->greaterThan($user->invite_expires_at)) {
            return $this->invitationError(
                'Esta invitación ya expiró. Pide a tu administrador que te envíe una nueva.'
            );
        }

        if ($user->invitation_accepted_at) {
            return $this->invitationError(
                'Esta invitación ya fue utilizada — esta cuenta ya está activa. Si olvidaste tu contraseña, usa la opción de recuperarla en la pantalla de inicio de sesión.',
                showLoginLink: true,
            );
        }

        return view('users.accept-invitation', compact('user'));
    }

    public function store(Request $request, string $token): View|RedirectResponse
    {
        $user = User::where('invite_token', $token)->first();

        if (! $user) {
            return $this->invitationError(
                'Este enlace de invitación no es válido. Pide a tu administrador que te envíe una nueva invitación.'
            );
        }

        if (! $user->invite_expires_at || now()->greaterThan($user->invite_expires_at)) {
            return $this->invitationError(
                'Esta invitación ya expiró. Pide a tu administrador que te envíe una nueva.'
            );
        }

        if ($user->invitation_accepted_at) {
            return $this->invitationError(
                'Esta invitación ya fue utilizada — esta cuenta ya está activa. Si olvidaste tu contraseña, usa la opción de recuperarla en la pantalla de inicio de sesión.',
                showLoginLink: true,
            );
        }

        $request->validate([
            'password' => ['required', 'confirmed', 'min:8'],
        ]);

        // El token NO se borra aquí a propósito: si alguien vuelve a abrir el mismo correo después
        // de activar su cuenta, necesitamos poder encontrar al usuario por ese token para darle el
        // mensaje de "ya fue utilizada" de arriba — antes se ponía en null, así que esa segunda
        // visita nunca llegaba a ese chequeo y siempre caía en un 404 genérico de Laravel.
        $user->update([
            'password' => Hash::make($request->password),
            'status' => 'active',
            'email_verified_at' => now(),
            'invitation_accepted_at' => now(),
        ]);

        Auth::login($user);

        return redirect()->route('dashboard');
    }

    private function invitationError(string $message, bool $showLoginLink = false): View
    {
        return view('users.invitation-error', [
            'message' => $message,
            'showLoginLink' => $showLoginLink,
        ]);
    }
}
