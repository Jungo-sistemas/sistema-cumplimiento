<?php

namespace Tests\Feature;

use App\Http\Controllers\RegulationController;
use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * "Cargar" con la opción de reemplazar a un desactualizado (buscado por código): la versión nueva
 * queda aprobada y el desactualizado se va a Obsoleto sin poder regresar. Y el código de un proceso
 * es único dentro del grupo entre los documentos vivos.
 */
class ReplaceOutdatedOnUploadTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private ProcessType $type;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('private');

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id, 'show_in_processes' => true]);
        $this->type    = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $this->admin   = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
    }

    private function regulation(string $code, string $name, ?string $status): Regulation
    {
        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $this->type->id,
            'document_type' => 'Procedimiento', 'code' => $code, 'name' => $name, 'is_active' => true,
            'approval_status' => $status, 'details' => ['quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto'],
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true,
            'file_path' => 'regulations/test/fake.docx', 'original_name' => 'fake.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);

        return $regulation;
    }

    private function upload(array $overrides = [])
    {
        return $this->actingAs($this->admin)->post(route('processes.storeCargar'), $overrides + [
            'company_id' => $this->company->id, 'process_type_id' => $this->type->id, 'document_type' => 'Procedimiento',
            'evaluation_impact' => 'medio',
            'nombre' => 'Alta de proveedores', 'codigo' => 'P-COM-001', 'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto',
            'file' => UploadedFile::fake()->create('nuevo.pdf', 50), 'approval_mode' => 'approved',
            'issued_at' => now()->toDateString(), 'valid_until' => now()->addYear()->toDateString(),
        ]);
    }

    public function test_el_buscador_solo_encuentra_desactualizados_por_codigo(): void
    {
        $this->regulation('P-COM-001', 'MANUAL VIEJO', 'outdated');
        $this->regulation('P-COM-002', 'APROBADO', 'approved');
        $sent = $this->regulation('P-COM-003', 'YA EN OBSOLETO', 'outdated');
        $sent->update(['obsoleted_at' => now()]);

        $this->actingAs($this->admin)->get(route('processes.cargar', ['company_id' => $this->company->id]))
            ->assertOk()
            ->assertSee('Reemplaza a un documento desactualizado');

        $this->actingAs($this->admin)->getJson(route('processes.cargar.outdated', ['q' => 'p-com']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.code', 'P-COM-001')
            ->assertJsonPath('0.quien_elabora', 'Ana');
    }

    public function test_reemplazar_con_la_opcion_de_flujo_deja_el_nuevo_sin_flujo_y_el_viejo_en_obsoleto(): void
    {
        $old = $this->regulation('P-COM-001', 'MANUAL VIEJO', 'outdated');

        $this->upload(['replaces_id' => $old->id, 'approval_mode' => 'flow', 'valid_until' => null])
            ->assertSessionHasNoErrors();

        $new = Regulation::where('code', 'P-COM-001')->whereNull('obsoleted_at')->sole();
        $this->assertNull($new->approval_status);
        $this->assertSame($new->id, $old->fresh()->replaced_by_id);
        $this->assertNotNull($old->fresh()->obsoleted_at);
    }

    public function test_reemplazar_aprueba_el_nuevo_y_manda_el_viejo_a_obsoleto_sin_regreso(): void
    {
        $old = $this->regulation('P-COM-001', 'MANUAL VIEJO', 'outdated');

        $this->upload(['replaces_id' => $old->id])->assertSessionHas('success');

        $new = Regulation::where('code', 'P-COM-001')->whereNull('obsoleted_at')->sole();
        $this->assertSame('approved', $new->approval_status);
        $this->assertSame('ALTA DE PROVEEDORES', $new->name);
        $this->assertNotNull($new->currentVersion->valid_until);

        $old->refresh();
        $this->assertNotNull($old->obsoleted_at);
        $this->assertSame($new->id, $old->replaced_by_id);
        // El viejo no aparece en el historial del nuevo.
        $this->assertSame(1, $new->versions()->count());

        $this->actingAs($this->admin)->get(route('processes.obsoleto'))
            ->assertSee('MANUAL VIEJO')
            ->assertSee('Reemplazado por')
            ->assertDontSee('Regresar a Procedimientos');

        $this->actingAs($this->admin)->post(route('processes.obsoleto.restore', $old))->assertSessionHas('error');
        $this->assertNotNull($old->fresh()->obsoleted_at);
    }

    public function test_solo_se_pueden_reemplazar_desactualizados(): void
    {
        $approved = $this->regulation('P-COM-001', 'APROBADO', 'approved');

        $this->upload(['replaces_id' => $approved->id, 'codigo' => 'P-COM-009'])->assertSessionHasErrors('replaces_id');

        $this->assertNull($approved->fresh()->obsoleted_at);
        $this->assertSame(1, Regulation::count());
    }

    public function test_el_codigo_no_se_puede_repetir_entre_documentos_vivos(): void
    {
        $this->regulation('P-COM-001', 'EXISTENTE', 'approved');

        // Sin reemplazar, mismo código (con minúsculas y espacios) → rechazado.
        $this->upload(['codigo' => ' p-com-001 '])->assertSessionHasErrors('codigo');
        $this->assertSame(1, Regulation::count());

        // Uno en Obsoleto no cuenta.
        $obsolete = $this->regulation('P-COM-050', 'EN OBSOLETO', 'outdated');
        $obsolete->update(['obsoleted_at' => now()]);
        $this->upload(['codigo' => 'P-COM-050'])->assertSessionHasNoErrors();

        // Y como ya hay otro vivo con su código, ese tampoco se puede regresar.
        $this->actingAs($this->admin)->post(route('processes.obsoleto.restore', $obsolete))->assertSessionHas('error');
        $this->assertNotNull($obsolete->fresh()->obsoleted_at);
    }

    public function test_el_nivel_de_impacto_es_obligatorio_al_cargar_un_proceso_pero_no_un_anexo(): void
    {
        $this->upload(['evaluation_impact' => ''])->assertSessionHasErrors('evaluation_impact');
        $this->assertSame(0, Regulation::count());

        $this->upload(['evaluation_impact' => '', 'is_annex' => 1, 'codigo' => 'F-COM-001'])->assertSessionHasNoErrors();
        $this->assertSame(1, Regulation::where('is_annex', true)->count());
    }

    public function test_editar_info_basica_no_permite_un_codigo_repetido(): void
    {
        $this->regulation('P-COM-001', 'UNO', 'approved');
        $two = $this->regulation('P-COM-002', 'DOS', 'approved');

        $this->actingAs($this->admin)->put(route('processes.updateBasic', $two), [
            'process_type_id' => $this->type->id, 'document_type' => 'Procedimiento', 'nombre' => 'DOS',
            'codigo' => 'P-COM-001', 'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto',
            'fecha_vigencia' => now()->toDateString(), 'motivo_creacion' => 'x',
        ])->assertSessionHasErrors('codigo');

        $this->assertSame('P-COM-002', $two->fresh()->code);
    }

    public function test_el_word_de_ia_lleva_el_nombre_del_proceso(): void
    {
        $method = new \ReflectionMethod(RegulationController::class, 'aiDocxName');

        $this->assertSame('ATENCIÓN A QUEJAS DE CLIENTES_v1.docx', $method->invoke(app(RegulationController::class), 'Atención a quejas  de clientes', 1));
        $this->assertSame('ALTA PROVEEDORES_v3.docx', $method->invoke(app(RegulationController::class), 'Alta: proveedores?', 3));
    }
}
