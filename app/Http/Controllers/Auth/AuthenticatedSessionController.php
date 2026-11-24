<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Support\TrustedDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        // Get user before logging out to trigger 2FA
        $user = Auth::user();

        // Log out immediately — full login completes only after 2FA verification
        Auth::guard('web')->logout();

        // Equipo recordado ("Recordar este equipo" en el código): entra sin código y la sesión
        // dura lo que le queda a esa confianza.
        $trustedMinutes = TrustedDevice::remainingMinutes($request, $user);
        if ($trustedMinutes > 0) {
            return TwoFactorController::completeLogin($request, $user, $trustedMinutes);
        }

        // Generate and email the 6-digit code
        TwoFactorController::generateAndSendCode($user);

        // Store pending user and regenerate session
        $request->session()->regenerate();
        $request->session()->put('two_factor_pending_user_id', $user->id);

        return redirect()->route('two-factor');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
