<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use App\Support\TrustedDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** "Recordar este equipo" en la verificación en dos pasos. */
class TrustedDeviceTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $this->user = User::factory()->create([
            'email' => 'ana@example.com', 'password' => Hash::make('secreta123'),
            'group_id' => $group->id, 'company_id' => $company->id, 'scope_level' => 'company', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Operativo', 'slug' => 'operative'])->id,
        ]);
    }

    private function login(?string $trustedCookie = null, ?User $as = null)
    {
        $request = $trustedCookie ? $this->withCookie(TrustedDevice::COOKIE, $trustedCookie) : $this;

        return $request->post(route('login'), ['email' => ($as ?? $this->user)->email, 'password' => 'secreta123']);
    }

    /** Inicia sesión con el código (111111 en local/pruebas) y regresa la respuesta del código. */
    private function loginWithCode(bool $remember)
    {
        $this->login()->assertRedirect(route('two-factor'));
        $this->user->update(['two_factor_code' => Hash::make('123456'), 'two_factor_expires_at' => now()->addMinutes(10)]);

        return $this->post(route('two-factor.store'), ['code' => '123456'] + ($remember ? ['remember_device' => '1'] : []));
    }

    private function logout(): void
    {
        Auth::logout();
        $this->flushSession();
    }

    public function test_la_pantalla_del_codigo_ofrece_recordar_el_equipo(): void
    {
        $this->login();
        $this->get(route('two-factor'))->assertOk()->assertSee('Recordar este equipo por ' . TrustedDevice::days() . ' días');
    }

    public function test_sin_recordar_vuelve_a_pedir_el_codigo(): void
    {
        $response = $this->loginWithCode(remember: false);
        $response->assertCookieMissing(TrustedDevice::COOKIE);
        $this->assertAuthenticatedAs($this->user);

        $this->logout();
        $this->login()->assertRedirect(route('two-factor'));
        $this->assertGuest();
    }

    public function test_recordar_el_equipo_entra_sin_codigo_y_mantiene_la_sesion(): void
    {
        $response = $this->loginWithCode(remember: true);
        $cookie = $response->getCookie(TrustedDevice::COOKIE)?->getValue();
        $this->assertNotNull($cookie);
        $this->assertNotNull($response->getCookie(Auth::guard('web')->getRecallerName())); // sesión recordada

        $this->logout();
        $this->login($cookie)->assertRedirect()->assertRedirectContains('/');
        $this->assertAuthenticatedAs($this->user);
        $this->assertNull($this->user->fresh()->two_factor_code); // no se generó (ni envió) un código nuevo
    }

    public function test_la_confianza_vence_a_los_dias(): void
    {
        $cookie = $this->loginWithCode(remember: true)->getCookie(TrustedDevice::COOKIE)->getValue();
        $this->logout();

        $this->travel(TrustedDevice::days())->days();
        $this->travel(1)->minutes();
        $this->login($cookie)->assertRedirect(route('two-factor'));
        $this->assertGuest();
    }

    public function test_cambiar_la_contrasena_deja_de_confiar_en_el_equipo(): void
    {
        $cookie = $this->loginWithCode(remember: true)->getCookie(TrustedDevice::COOKIE)->getValue();
        $this->logout();

        $this->user->update(['password' => Hash::make('secreta123')]); // misma clave, nuevo hash
        $this->login($cookie)->assertRedirect(route('two-factor'));
    }

    public function test_la_cookie_de_otra_persona_no_sirve(): void
    {
        $cookie = $this->loginWithCode(remember: true)->getCookie(TrustedDevice::COOKIE)->getValue();
        $this->logout();

        $otro = User::factory()->create([
            'email' => 'luis@example.com', 'password' => Hash::make('secreta123'),
            'group_id' => $this->user->group_id, 'company_id' => $this->user->company_id,
            'scope_level' => 'company', 'status' => 'active', 'role_id' => $this->user->role_id,
        ]);
        $this->login($cookie, $otro)->assertRedirect(route('two-factor'));
        $this->assertGuest();
    }

    public function test_con_cero_dias_siempre_pide_el_codigo(): void
    {
        config(['auth.two_factor_remember_days' => 0]);

        $this->loginWithCode(remember: true)->assertCookieMissing(TrustedDevice::COOKIE);
        $this->get(route('two-factor')); // sin sesión pendiente: no truena
        $this->logout();
        $this->login()->assertRedirect(route('two-factor'));
    }
}
