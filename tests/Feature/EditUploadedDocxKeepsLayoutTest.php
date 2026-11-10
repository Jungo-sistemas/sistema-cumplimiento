<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\RegulationChangeTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\DocxLayoutPatcherTest;
use ZipArchive;

/**
 * Guardar la edición en línea de un Word subido usa el .docx anterior como guía (DocxLayoutPatcher);
 * los documentos de IA, cuyo formato vive en el HTML, se siguen reconstruyendo.
 */
class EditUploadedDocxKeepsLayoutTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Regulation $regulation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();
        $this->mock(RegulationChangeTableService::class, fn ($mock) => $mock->shouldReceive('generate')->andReturn(null));

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ALCOM', 'group_id' => $group->id, 'show_in_processes' => true]);
        $this->admin = User::factory()->create([
            'group_id' => $group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
        $this->regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id,
            'process_type_id' => ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true])->id,
            'code' => 'P-MDI-053', 'name' => 'Disciplina operativa', 'is_active' => true, 'approval_status' => null,
        ]);
    }

    private function version(?string $bodyHtml): RegulationVersion
    {
        $path = "regulations/{$this->regulation->company_id}/{$this->regulation->id}/versions/doc_v1.docx";
        $tmp = tempnam(sys_get_temp_dir(), 'fixture_') . '.docx';
        DocxLayoutPatcherTest::writeSampleDocx($tmp);
        Storage::disk('private')->put($path, file_get_contents($tmp));
        @unlink($tmp);

        return RegulationVersion::create([
            'regulation_id' => $this->regulation->id, 'version_number' => 1, 'body_html' => $bodyHtml,
            'file_path' => $path, 'original_name' => 'doc_v1.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(), 'is_current' => true, 'uploaded_by' => $this->admin->id,
        ]);
    }

    private function save(RegulationVersion $version, string $html): RegulationVersion
    {
        $this->actingAs($this->admin)->post(route('regulation-versions.saveEdit', $version), [
            'content' => $html, 'change_justification' => 'Se corrigió una viñeta.',
        ])->assertRedirect();

        return $this->regulation->versions()->where('is_current', true)->firstOrFail();
    }

    private function documentXml(RegulationVersion $version): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'saved_') . '.docx';
        file_put_contents($tmp, Storage::disk('private')->get($version->file_path));
        $zip = new ZipArchive();
        $zip->open($tmp);
        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tmp);

        return $xml;
    }

    private function editorHtml(): string
    {
        return '<p><span style="color: #002060; font-weight: bold;">OBJETIVO</span></p>'
            . '<p>Establecer el procedimiento para administrar las metas.</p>'
            . '<p><span style="color: #002060; font-weight: bold;">TÓPICOS</span></p>'
            . '<p>Asignación mensual de metas.</p><p>Módulo de avisos y notificaciones.</p><p>Revisión periódica de las plataformas.</p>'
            . '<p><span style="color: #002060; font-weight: bold;">DEFINICIONES</span></p>'
            . '<table><tbody><tr><td><p>TÉRMINO</p></td><td><p>DEFINICIÓN</p></td></tr>'
            . '<tr><td><p>ATQ</p></td><td><p>Autotanque de gas.</p></td></tr>'
            . '<tr><td><p>PTA</p></td><td><p>Planta de almacenamiento.</p></td></tr></tbody></table>'
            . '<p>Detonante del proceso:</p>';
    }

    public function test_editar_un_word_subido_conserva_su_formato(): void
    {
        $new = $this->save($this->version(null), $this->editorHtml());

        $this->assertTrue($new->keeps_docx_layout);
        $this->assertTrue($new->hasDocxLayout());

        $xml = $this->documentXml($new);
        $this->assertStringContainsString('Módulo de avisos y notificaciones.', $xml);
        // Títulos con borde, viñetas con su estilo y la tabla con sus bordes, como en el original.
        $this->assertSame(3, substr_count($xml, '<w:pBdr>'));
        $this->assertSame(3, substr_count($xml, 'w:val="Listaconvietas"'));
        $this->assertStringContainsString('<w:tblBorders>', $xml);
        // El encabezado del sistema con la versión nueva.
        $this->assertStringContainsString('w:headerReference', $xml);

        // La siguiente edición vuelve a parchar sobre esta versión.
        $third = $this->save($new, str_replace('Autotanque de gas.', 'Autotanque de gas L.P.', $this->editorHtml()));
        $this->assertTrue($third->keeps_docx_layout);
        $this->assertSame(3, substr_count($this->documentXml($third), 'w:val="Listaconvietas"'));
    }

    public function test_un_documento_de_ia_se_sigue_reconstruyendo_desde_su_html(): void
    {
        $new = $this->save($this->version('<p>Texto generado por IA.</p>'), '<p>Texto generado por IA, editado.</p>');

        $this->assertFalse($new->keeps_docx_layout);
        $this->assertStringNotContainsString('Listaconvietas', $this->documentXml($new));
    }
}
