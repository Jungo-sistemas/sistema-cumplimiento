<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Bug real encontrado probando visualmente "Ver" sobre una versión ya guardada (con un navegador
 * real, no solo leyendo código): el encabezado fijo (RegulationDocxHeaderBuilder — logo, nombre,
 * código, versión, elaboró, aprobó) SÍ se ve en la vista previa del wizard antes de confirmar
 * (processes/preview.blade.php, que sí incluye <template id="doc-header-template">), pero
 * RegulationVersionController::preview() — la pantalla que de verdad usa alguien para revisar un
 * documento YA GUARDADO — nunca lo incluía. document-pagination.js clona ese template en cada
 * hoja pero lo trata como opcional (no truena si falta), así que el resultado era simplemente
 * "sin encabezado", en silencio: el formato ya autorizado con el cliente parecía perdido en
 * pantalla aunque el .docx descargado sí lo trae siempre.
 */
class RegulationVersionPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_ver_una_version_muestra_el_encabezado_fijo_con_los_datos_reales(): void
    {
        $group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'Demo', 'group_id' => $group->id]);
        $type    = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $adminRole = Role::create(['name' => 'Administrador', 'slug' => 'admin']);

        $user = User::factory()->create([
            'group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $adminRole->id, 'status' => 'active',
        ]);

        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-TEST-001', 'name' => 'Procedimiento de prueba', 'is_active' => true,
            'details' => ['quien_elabora' => 'Juan Pérez', 'quien_aprueba' => 'María López', 'fecha_vigencia' => '2026-01-01'],
        ]);

        $version = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 3,
            'body_html' => '<p>Contenido del documento.</p>',
            'file_path' => 'regulations/test/fake.docx', // no necesita existir: la rama .docx usa body_html directo
            'original_name' => 'P-TEST-001_v3.docx', 'disk' => 'private',
            'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'is_current' => true, 'issued_at' => now()->toDateString(),
        ]);

        \Illuminate\Support\Facades\Storage::fake('private');
        \Illuminate\Support\Facades\Storage::disk('private')->put($version->file_path, 'contenido irrelevante');

        $html = $this->actingAs($user)->get(route('regulation-versions.preview', $version))->getContent();

        $this->assertStringContainsString('doc-header-template', $html);
        $this->assertStringContainsString('Procedimiento de prueba', $html);
        $this->assertStringContainsString('P-TEST-001', $html);
        $this->assertStringContainsString('Juan Pérez', $html);
        $this->assertStringContainsString('María López', $html);
        // Versión formateada a 2 dígitos, igual que RegulationDocxHeaderBuilder en el .docx real.
        $this->assertStringContainsString('03', $html);
    }
}
