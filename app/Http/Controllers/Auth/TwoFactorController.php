<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\TwoFactorCodeMail;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor');
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $request->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('login');
        }

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $userId = $request->session()->get('two_factor_pending_user_id');
        $user   = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $rateLimitKey = 'two_factor:' . $userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 5)) {
            $request->session()->forget('two_factor_pending_user_id');
            return redirect()->route('login')
                ->withErrors(['email' => 'Demasiados intentos fallidos. Inicia sesión de nuevo.']);
        }

        if (! $user->two_factor_expires_at || now()->greaterThan($user->two_factor_expires_at)) {
            $request->session()->forget('two_factor_pending_user_id');
            return redirect()->route('login')
                ->withErrors(['email' => 'El código expiró. Inicia sesión de nuevo.']);
        }

        if (! Hash::check($request->code, $user->two_factor_code)) {
            RateLimiter::hit($rateLimitKey);
            return back()->withErrors(['code' => 'Código incorrecto. Inténtalo de nuevo.']);
        }

        // Success — clear code and complete login
        RateLimiter::clear($rateLimitKey);

        $user->update([
            'two_factor_code'       => null,
            'two_factor_expires_at' => null,
        ]);

        $request->session()->forget('two_factor_pending_user_id');
        $request->session()->regenerate();

        Auth::loginUsingId($user->id);

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function resend(Request $request): RedirectResponse
    {
        if (! $request->session()->has('two_factor_pending_user_id')) {
            return redirect()->route('login');
        }

        $userId = $request->session()->get('two_factor_pending_user_id');
        $user   = User::find($userId);

        if (! $user) {
            return redirect()->route('login');
        }

        $rateLimitKey = 'two_factor_resend:' . $userId;

        if (RateLimiter::tooManyAttempts($rateLimitKey, 3)) {
            return back()->withErrors(['code' => 'Demasiadas solicitudes de reenvío. Espera un momento.']);
        }

        RateLimiter::hit($rateLimitKey);

        self::generateAndSendCode($user);

        return back()->with('status', 'Se envió un nuevo código a tu correo.');
    }

    public static function generateAndSendCode(User $user): void
    {
        // En local siempre 111111 para poder probar sin acceso al correo del usuario.
        $code = app()->isLocal()
            ? '111111'
            : str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $user->update([
            'two_factor_code'       => Hash::make($code),
            'two_factor_expires_at' => now()->addMinutes(10),
        ]);

        // Test accounts (@example domains) — log code instead of sending email
        if (self::isTestEmail($user->email)) {
            Log::info("[2FA TEST] {$user->email} → código: {$code}");
            return;
        }

        Mail::to($user->email)->send(new TwoFactorCodeMail($user, $code));
    }

    private static function isTestEmail(string $email): bool
    {
        $testDomains = ['example.com', 'example.net', 'example.org'];
        $domain = strtolower(substr($email, strpos($email, '@') + 1));
        return in_array($domain, $testDomains);
    }
}
