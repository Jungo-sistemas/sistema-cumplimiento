<?php

namespace Tests\Feature;

use App\Application\Compliance\ComplianceLevelService;
use App\Models\Company;
use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ComplianceLevelDashboardTest extends TestCase
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
        $other = Group::create(['name' => 'Otro', 'slug' => 'otro', 'is_active' => true]);
        $this->alcom  = Company::create(['name' => 'ALCOM', 'group_id' => $group->id]);
        $this->biomex = Company::create(['name' => 'BIOMEX', 'group_id' => $group->id]);
        $ajena        = Company::create(['name' => 'AJENA', 'group_id' => $other->id]);

        $this->admin = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $group->id, 'company_id' => $this->alcom->id,
            'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);

        $this->tracto = DB::table('asset_types')->insertGetId(['name' => 'Tracto', 'slug' => 'tracto', 'priority_level' => 1, 'warning_days' => 60, 'danger_days' => 30]);
        $this->planta = DB::table('asset_types')->insertGetId(['name' => 'Plantas', 'slug' => 'plantas', 'priority_level' => 1, 'warning_days' => 60, 'danger_days' => 30]);

        $today = now()->toDateString();
        $past = now()->subDays(10)->toDateString();
        $future = now()->addDays(90)->toDateString();

        // ALCOM / Tracto, impacto alta: cumplido, completado pero vencido, pendiente, vencido sin completar, cancelado (no cuenta)
        $a1 = $this->asset($this->alcom, $this->tracto, 'T-01');
        // Entidades: ASEA (una escrita " asea " para probar que se normaliza), CRE y sin entidad.
        $this->req($a1, 'alta', 'completed', $future, $future, 'ASEA');
        $this->req($a1, 'alta', 'completed', $past, $past, 'ASEA');
        $this->req($a1, 'alta', 'pending', $future, null, ' asea ');
        $this->req($a1, 'alta', 'pending', $past, null, 'CRE');
        $this->req($a1, 'alta', 'cancelled', $past, null, 'ASEA');

        // BIOMEX / Plantas: media cumplido (sin vencimiento), baja en trámite, sin impacto pendiente
        $b1 = $this->asset($this->biomex, $this->planta, 'P-01');
        $this->req($b1, 'media', 'completed', $today, null, 'CRE');
        $this->req($b1, 'baja', 'in_transit', $past);
        $this->req($b1, null, 'pending', $future);

        // Otro grupo: nunca cuenta
        $this->req($this->asset($ajena, $this->tracto, 'X-01'), 'alta', 'completed', $future, $future);
    }

    private function asset(Company $company, int $typeId, string $name): int
    {
        return DB::table('assets')->insertGetId([
            'company_id' => $company->id, 'asset_type_id' => $typeId, 'name' => $name, 'status' => 'active',
        ]);
    }

    private function req(int $assetId, ?string $priority, string $status, string $due, ?string $expires = null, ?string $authority = null): void
    {
        static $n = 0;
        $n++;
        $templateId = DB::table('requirement_templates')->insertGetId([
            'name' => "Requisito {$n}", 'priority' => $priority ? ucfirst($priority) : null, 'authority' => $authority,
        ]);
        DB::table('asset_requirements')->insert([
            'company_id' => DB::table('assets')->where('id', $assetId)->value('company_id'),
            'asset_id' => $assetId, 'requirement_template_id' => $templateId, 'type' => 'documento',
            'status' => $status, 'due_date' => $due, 'expires_at' => $expires,
        ]);
    }

    private function summary(array $input = []): array
    {
        $ids = [$this->alcom->id, $this->biomex->id];

        return app(ComplianceLevelService::class)->summary($ids, ComplianceLevelService::filtersFrom($input, $ids));
    }

    public function test_calcula_el_cumplimiento_general_y_por_impacto(): void
    {
        $s = $this->summary();

        $this->assertSame(
            ['cumplido' => 2, 'en_tramite' => 1, 'pendiente' => 2, 'vencido' => 2, 'total' => 7, 'pct' => 28.6],
            $s['overall']
        );

        $impact = collect($s['by_impact'])->keyBy('key');
        $this->assertSame([1, 4, 25.0], [$impact['alta']['cumplido'], $impact['alta']['total'], $impact['alta']['pct']]);
        $this->assertSame([1, 1, 100.0], [$impact['media']['cumplido'], $impact['media']['total'], $impact['media']['pct']]);
        $this->assertSame([0, 1], [$impact['baja']['cumplido'], $impact['baja']['en_tramite']]);
        $this->assertSame(1, $impact['sin']['total']);

        $this->assertSame(['ALCOM', 'BIOMEX'], array_column($s['by_company'], 'name'));
    }

    public function test_filtra_por_empresa_tipo_de_activo_e_impacto(): void
    {
        $this->assertSame(4, $this->summary(['empresa' => $this->alcom->id])['overall']['total']);
        $this->assertSame(3, $this->summary(['tipo_activo' => $this->planta])['overall']['total']);
        $this->assertSame(1, $this->summary(['impacto' => 'media'])['overall']['total']);
        $this->assertSame(0, $this->summary(['empresa' => $this->biomex->id, 'impacto' => 'alta'])['overall']['total']);

        // Una empresa fuera del alcance se ignora (se ve todo lo permitido, no la ajena).
        $ajena = Company::where('name', 'AJENA')->value('id');
        $this->assertSame(7, $this->summary(['empresa' => $ajena])['overall']['total']);
    }

    public function test_corte_y_filtro_por_entidad(): void
    {
        $byAuthority = collect($this->summary()['by_authority'])->keyBy('key');

        $this->assertSame(['ASEA', 'CRE', 'sin'], $byAuthority->keys()->all()); // "Sin entidad" al final
        $this->assertSame([3, 1, 1, 1], [$byAuthority['ASEA']['total'], $byAuthority['ASEA']['cumplido'], $byAuthority['ASEA']['vencido'], $byAuthority['ASEA']['pendiente']]);
        $this->assertSame([2, 1, 1], [$byAuthority['CRE']['total'], $byAuthority['CRE']['cumplido'], $byAuthority['CRE']['vencido']]);
        $this->assertSame('Sin entidad', $byAuthority['sin']['name']);
        $this->assertSame(2, $byAuthority['sin']['total']);

        $this->assertSame(3, $this->summary(['entidad' => 'asea'])['overall']['total']);
        $this->assertSame(2, $this->summary(['entidad' => 'sin'])['overall']['total']);
        $this->assertSame(1, $this->summary(['entidad' => 'CRE', 'empresa' => $this->biomex->id])['overall']['total']);
        $this->assertSame(0, $this->summary(['entidad' => 'PROFECO'])['overall']['total']);

        $this->assertSame(['ASEA', 'CRE'], app(ComplianceLevelService::class)->authorities());
    }

    public function test_el_tablero_muestra_la_grafica_con_filtros(): void
    {
        $this->actingAs($this->admin)
            ->get(route('dashboard', ['impacto' => 'alta']))
            ->assertOk()
            ->assertSee('Nivel de cumplimiento')
            ->assertSee('chartLevelImpact', false)
            ->assertSee('Quitar filtros')
            ->assertSee('Por entidad')
            ->assertSee('<option value="ASEA"', false)
            ->assertDontSee('Fechas en riesgo');
    }

    public function test_proximos_a_vencer_ordenados_paginados_y_con_filtros(): void
    {
        $ids = [$this->alcom->id, $this->biomex->id];
        $service = app(ComplianceLevelService::class);

        // Fixture: ALCOM cumplido (vence doc en 90 días) y pendiente (90 días); BIOMEX media cumplido hoy
        // sin vencimiento (no sale), sin impacto pendiente (90 días). Vencidos, en trámite y cancelado no salen.
        $all = $service->upcoming($ids);
        $this->assertSame(3, $all->total());

        $this->assertSame(2, $service->upcoming($ids, ComplianceLevelService::filtersFrom(['empresa' => $this->alcom->id], $ids))->total());

        $paged = $service->upcoming($ids, [], 2);
        $this->assertSame(2, $paged->lastPage());
        $this->assertCount(2, $paged->items());
    }

    public function test_exporta_el_excel_con_resumen_y_detalle(): void
    {
        $response = $this->actingAs($this->admin)
            ->get(route('dashboard.level.export', ['empresa' => $this->alcom->id]))
            ->assertOk();

        $file = $response->baseResponse->getFile()->getPathname();
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);

        $this->assertSame(['Resumen', 'Detalle'], $book->getSheetNames());
        $resumen = $book->getSheetByName('Resumen')->toArray();
        $this->assertContains(['Empresa', 'ALCOM', null, null, null, null, null], $resumen);
        $this->assertContains(['Entidad', 'Todas', null, null, null, null, null], $resumen);
        $this->assertContains('Por entidad', array_column($resumen, 0));

        $detalle = $book->getSheetByName('Detalle')->toArray();
        $this->assertCount(5, $detalle); // encabezado + 4 (el cancelado no cuenta)
        $this->assertSame('Empresa', $detalle[0][0]);
        $this->assertEqualsCanonicalizing(
            ['Cumplido', 'Vencido', 'Pendiente', 'Vencido'],
            array_column(array_slice($detalle, 1), 7)
        );
    }
}
