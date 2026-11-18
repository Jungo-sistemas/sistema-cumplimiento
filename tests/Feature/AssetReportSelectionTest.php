<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

/**
 * Reporte de cumplimiento desde la lista de activos: "seleccionar todos los del filtro" necesita
 * los ids de todo el filtro (no solo la página), y el Excel aguanta muchos activos.
 */
class AssetReportSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Company $alcom;
    private Company $biomex;
    private User $admin;
    private int $tracto;
    private int $planta;

    protected function setUp(): void
    {
        parent::setUp();

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->alcom  = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $this->biomex = Company::create(['name' => 'BIOMEX', 'group_id' => $group->id]);

        $this->admin = User::factory()->create([
            'group_id' => $group->id, 'company_id' => $this->alcom->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);

        $type = fn ($name) => DB::table('asset_types')->insertGetId([
            'name' => $name, 'slug' => strtolower($name), 'priority_level' => 1, 'warning_days' => 60, 'danger_days' => 30,
        ]);
        $this->tracto = $type('Tracto');
        $this->planta = $type('Plantas');
        $template = DB::table('requirement_templates')->insertGetId(['name' => 'Permiso', 'priority' => 'alta']);

        // 20 tractos de ALCOM (más que una página de 15) y 3 plantas de BIOMEX, cada uno con 2 requerimientos.
        foreach ([[$this->alcom, $this->tracto, 20], [$this->biomex, $this->planta, 3]] as [$company, $typeId, $n]) {
            for ($i = 1; $i <= $n; $i++) {
                $asset = DB::table('assets')->insertGetId([
                    'company_id' => $company->id, 'asset_type_id' => $typeId, 'name' => "{$company->name} {$i}", 'status' => 'active',
                ]);
                foreach ([1, 2] as $_) {
                    DB::table('asset_requirements')->insert([
                        'company_id' => $company->id, 'asset_id' => $asset, 'requirement_template_id' => $template,
                        'type' => 'documento', 'status' => 'pending', 'due_date' => now()->addDays(40)->toDateString(),
                    ]);
                }
            }
        }
    }

    public function test_la_lista_trae_los_ids_de_todo_el_filtro_aunque_este_paginada(): void
    {
        $response = $this->actingAs($this->admin)->get(route('assets.index', ['company_id' => $this->alcom->id]))->assertOk();

        $this->assertCount(15, $response->viewData('assets'));
        $this->assertCount(20, $response->viewData('filteredIds'));
        $response->assertSee('Seleccionar los 20 de ALCOM');

        $byType = $this->actingAs($this->admin)->get(route('assets.index', ['asset_type_id' => $this->planta]));
        $this->assertCount(3, $byType->viewData('filteredIds'));
        $byType->assertSee('Seleccionar los 3 de Plantas');
    }

    public function test_el_excel_trae_todos_los_activos_elegidos(): void
    {
        $ids = DB::table('assets')->pluck('id')->all();

        $response = $this->actingAs($this->admin)->post(route('assets.report'), ['asset_ids' => $ids])->assertOk();

        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        $this->assertSame(['Resumen', 'Detalle'], $book->getSheetNames());
        $this->assertCount(24, $book->getSheetByName('Resumen')->toArray());  // encabezado + 23 activos
        $this->assertCount(47, $book->getSheetByName('Detalle')->toArray());  // encabezado + 46 requerimientos
        $this->assertSame('Total requerimientos', $book->getSheetByName('Resumen')->toArray()[0][4]);
    }
}
