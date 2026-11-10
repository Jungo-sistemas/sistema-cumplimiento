<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Ajuste de empresas en Procesos: ocultar, renombrar solo en Procesos y agregar JUNGO/PACTO.
 */
class AdjustProcessCompaniesTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = Group::create(['name' => 'Vigía', 'slug' => 'vigia', 'is_active' => true]);

        foreach (['ALCOM', 'VIDENCI', 'MIGAR CAPITAL', 'MIGAR REAL STATE', 'ASESORÍAS DAVAL', 'TI', 'FISCAL', 'GASERA VILLA DE REYES', 'TOTAL PROPANE', 'CAPITAL HUMANO', 'PACTO'] as $name) {
            Company::create(['name' => $name, 'group_id' => $this->group->id, 'show_in_processes' => $name !== 'PACTO']);
        }
    }

    private function company(string $name): Company
    {
        return Company::where('name', $name)->firstOrFail();
    }

    public function test_en_modo_prueba_no_cambia_nada(): void
    {
        $this->artisan('procesos:ajustar-empresas', ['--dry-run' => true])
            ->expectsOutput('Renombrar en Procesos: VIDENCI → SAVSA')
            ->assertSuccessful();

        $this->assertTrue((bool) $this->company('TI')->show_in_processes);
        $this->assertNull($this->company('VIDENCI')->process_name);
        $this->assertFalse(Company::where('name', 'JUNGO')->exists());
    }

    public function test_oculta_renombra_solo_en_procesos_y_agrega(): void
    {
        $this->artisan('procesos:ajustar-empresas')
            // Las que no existen en este grupo se saltan con aviso, sin tocar otra.
            ->expectsOutput('No encontré ninguna empresa como «VERITAS%»: se salta.')
            ->assertSuccessful();

        foreach (['TI', 'FISCAL', 'GASERA VILLA DE REYES', 'TOTAL PROPANE', 'MIGAR REAL STATE', 'CAPITAL HUMANO'] as $hidden) {
            $this->assertFalse((bool) $this->company($hidden)->show_in_processes, $hidden);
        }
        $this->assertTrue((bool) $this->company('ALCOM')->show_in_processes);

        // El nombre real no cambia (Cumplimiento); solo el de Procesos.
        $this->assertSame('SAVSA', $this->company('VIDENCI')->procesos_name);
        $this->assertSame('MIGAR', $this->company('MIGAR CAPITAL')->procesos_name);
        $this->assertSame('DAVAL', $this->company('ASESORÍAS DAVAL')->procesos_name);
        $this->assertSame('ALCOM', $this->company('ALCOM')->procesos_name);

        $this->assertTrue((bool) $this->company('PACTO')->show_in_processes);
        $this->assertTrue((bool) $this->company('JUNGO')->show_in_processes);

        // Correrlo otra vez no duplica nada.
        $this->artisan('procesos:ajustar-empresas')->assertSuccessful();
        $this->assertSame(1, Company::where('name', 'JUNGO')->count());
    }

    public function test_procesos_muestra_el_nombre_de_procesos(): void
    {
        $this->artisan('procesos:ajustar-empresas')->assertSuccessful();

        $admin = User::factory()->create([
            'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);

        $this->actingAs($admin)->get(route('processes.index'))
            ->assertOk()
            ->assertSee('SAVSA')
            ->assertSee('DAVAL')
            ->assertDontSee('VIDENCI')
            ->assertDontSee('MIGAR CAPITAL')
            ->assertDontSee('TOTAL PROPANE');
    }
}
