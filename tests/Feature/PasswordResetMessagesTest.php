<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * "Olvidé mi contraseña" responde igual exista o no el correo (para no revelar qué correos tienen
 * cuenta) y todos sus mensajes salen en español.
 */
class PasswordResetMessagesTest extends TestCase
{
    use RefreshDatabase;

    private const SENT = 'Si el correo está registrado, te enviamos un enlace';

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'ana@example.com', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id,
        ]);
    }

    public function test_responde_igual_exista_o_no_el_correo(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->from(route('password.request'))->post(route('password.email'), ['email' => 'nadie@example.com'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasNoErrors();
        $this->get(route('password.request'))->assertSee(self::SENT);

        $this->from(route('password.request'))->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasNoErrors();
        $this->get(route('password.request'))->assertSee(self::SENT);

        Notification::assertSentTo($user, ResetPasswordNotification::class);
    }

    public function test_un_segundo_intento_inmediato_tampoco_da_error(): void
    {
        Notification::fake();
        $user = $this->user();

        $this->post(route('password.email'), ['email' => $user->email]);
        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
    }

    public function test_restablecer_con_enlace_valido_e_invalido(): void
    {
        $user = $this->user();

        $this->from(route('password.reset', 'token-falso'))->post(route('password.store'), [
            'token' => 'token-falso', 'email' => $user->email,
            'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
        ])->assertSessionHasErrors(['email' => 'El enlace para restablecer la contraseña no es válido o ya expiró, o el correo no coincide. Solicita un enlace nuevo.']);

        $this->post(route('password.store'), [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'corta', 'password_confirmation' => 'otra',
        ])->assertSessionHasErrors(['password' => 'Las contraseñas no coinciden.']);

        $this->post(route('password.store'), [
            'token' => Password::createToken($user), 'email' => $user->email,
            'password' => 'NuevaClave123', 'password_confirmation' => 'NuevaClave123',
        ])->assertRedirect(route('login'))
          ->assertSessionHas('status', 'Tu contraseña se actualizó. Ya puedes iniciar sesión.');

        $this->assertTrue(Hash::check('NuevaClave123', $user->fresh()->password));
    }
}
