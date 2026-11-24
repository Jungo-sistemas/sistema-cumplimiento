<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * "Recordar este equipo" de la verificación en dos pasos.
 *
 * Al marcarlo, el equipo guarda una cookie (cifrada por Laravel, como todas) con el usuario, la
 * fecha en que vence y una huella de su contraseña. Mientras no venza, iniciar sesión en ese
 * equipo solo pide la contraseña, sin código. Cambiar la contraseña invalida la huella, así que
 * deja de confiar en todos los equipos. Los días salen de config auth.two_factor_remember_days.
 */
class TrustedDevice
{
    public const COOKIE = 'jc_trusted_device';

    public static function days(): int
    {
        return max(0, (int) config('auth.two_factor_remember_days', 3));
    }

    public static function enabled(): bool
    {
        return self::days() > 0;
    }

    /** Cookie que marca este equipo como confiable para el usuario. */
    public static function cookie(User $user): Cookie
    {
        $expires = now()->addDays(self::days());

        return cookie(self::COOKIE, json_encode([
            'u' => $user->id,
            'e' => $expires->timestamp,
            'p' => self::fingerprint($user),
        ]), self::days() * 24 * 60);
    }

    /** Minutos que le quedan a la confianza de este equipo para el usuario (0 = no es confiable). */
    public static function remainingMinutes(Request $request, User $user): int
    {
        if (! self::enabled()) {
            return 0;
        }

        $data = json_decode((string) $request->cookie(self::COOKIE), true);
        if (! is_array($data) || (int) ($data['u'] ?? 0) !== $user->id || ! is_string($data['p'] ?? null)
            || ! hash_equals(self::fingerprint($user), $data['p'])) {
            return 0;
        }

        $expires = (int) ($data['e'] ?? 0);

        return $expires > now()->timestamp ? (int) ceil(($expires - now()->timestamp) / 60) : 0;
    }

    private static function fingerprint(User $user): string
    {
        return hash_hmac('sha256', $user->id . '|' . $user->password, (string) config('app.key'));
    }
}
