<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\RegulationDocxHeaderBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

/**
 * Editar la información básica debe verse en el documento: el encabezado del Word del sistema se
 * reescribe (y su PDF convertido se regenera). Y "Fecha de elaboración" ya no es el vencimiento.
 */
class EditBasicRefreshesDocumentTest extends TestCase
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

    /** Proceso con un .docx con el encabezado del sistema (como los generados con IA). */
    private function systemDocument(?string $bodyHtml = '<p>Cuerpo</p>'): array
    {
        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $this->type->id,
            'document_type' => 'Procedimiento', 'code' => 'P-COM-001', 'name' => 'ALTA DE PROVEEDORES', 'is_active' => true,
            'approval_status' => 'approved',
            'details' => ['quien_elabora' => 'Ana Vieja', 'quien_aprueba' => 'Beto', 'fecha_vigencia' => '2026-01-10', 'motivo_creacion' => 'x'],
        ]);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        app(RegulationDocxHeaderBuilder::class)->apply($section, [
            'nombre' => 'ALTA DE PROVEEDORES', 'codigo' => 'P-COM-001', 'version' => '01',
            'quien_elabora' => 'Ana Vieja', 'quien_aprueba' => 'Beto', 'fecha_vigencia' => '2026-01-10',
        ]);
        $section->addText('Cuerpo del procedimiento que no debe cambiar');
        $tmp = tempnam(sys_get_temp_dir(), 'docx');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        $path = "regulations/{$this->company->id}/{$regulation->id}/versions/doc_v1.docx";
        Storage::disk('private')->put($path, file_get_contents($tmp));
        Storage::disk('private')->put($path . '.preview.pdf', 'pdf viejo');
        @unlink($tmp);

        $version = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => true, 'body_html' => $bodyHtml,
            'file_path' => $path, 'original_name' => 'doc_v1.docx', 'disk' => 'private',
            'issued_at' => '2026-01-10', 'valid_until' => '2027-01-10',
        ]);

        return [$regulation, $version];
    }

    /** Texto del encabezado que el documento usa de verdad (el viejo queda en el zip sin referencia) + el cuerpo. */
    private function headerText(string $path): string
    {
        $file = Storage::disk('private')->path($path);
        IOFactory::load($file); // el Word resultante se puede abrir

        $zip = new \ZipArchive();
        $zip->open($file);
        $document = $zip->getFromName('word/document.xml');
        $rels = $zip->getFromName('word/_rels/document.xml.rels');

        preg_match('#<w:headerReference[^>]*r:id="([^"]+)"#', $document, $ref);
        preg_match('#<Relationship[^>]*Id="' . preg_quote($ref[1], '#') . '"[^>]*Target="([^"]+)"#', $rels, $target)
            || preg_match('#<Relationship[^>]*Target="([^"]+)"[^>]*Id="' . preg_quote($ref[1], '#') . '"#', $rels, $target);
        $header = $zip->getFromName('word/' . ltrim($target[1], '/'));
        $zip->close();

        return strip_tags($header) . '|BODY:' . strip_tags($document);
    }

    private function updateBasic(Regulation $regulation, array $overrides = [])
    {
        return $this->actingAs($this->admin)->put(route('processes.updateBasic', $regulation), $overrides + [
            'process_type_id' => $this->type->id, 'document_type' => 'Procedimiento', 'nombre' => 'ALTA DE PROVEEDORES',
            'codigo' => 'P-COM-001', 'quien_elabora' => 'Ana Nueva', 'quien_aprueba' => 'Beto',
            'fecha_vigencia' => '2026-03-15', 'motivo_creacion' => 'x',
        ]);
    }

    public function test_editar_info_basica_reescribe_el_encabezado_del_word_y_borra_el_pdf_viejo(): void
    {
        [$regulation, $version] = $this->systemDocument();

        $this->updateBasic($regulation)->assertSessionHasNoErrors();

        $header = $this->headerText($version->file_path);
        $this->assertStringContainsString('Ana Nueva', $header);
        $this->assertStringContainsString('15/03/2026', $header);
        $this->assertStringContainsString('Cuerpo del procedimiento que no debe cambiar', $header);
        $this->assertFalse(Storage::disk('private')->exists($version->file_path . '.preview.pdf'));
        $this->assertSame('2026-03-15', $regulation->fresh()->details['fecha_vigencia']);
    }

    public function test_un_word_subido_tal_cual_no_se_toca(): void
    {
        [$regulation, $version] = $this->systemDocument(bodyHtml: null);
        $before = Storage::disk('private')->get($version->file_path);

        $this->updateBasic($regulation)->assertSessionHasNoErrors();

        $this->assertSame($before, Storage::disk('private')->get($version->file_path));
        $this->assertSame('Ana Nueva', $regulation->fresh()->details['quien_elabora']);
    }

    public function test_cargar_guarda_la_fecha_de_emision_como_fecha_de_elaboracion(): void
    {
        $this->actingAs($this->admin)->post(route('processes.storeCargar'), [
            'company_id' => $this->company->id, 'process_type_id' => $this->type->id, 'document_type' => 'Procedimiento',
            'evaluation_impact' => 'medio', 'nombre' => 'Manual', 'codigo' => 'P-COM-777',
            'quien_elabora' => 'Ana', 'quien_aprueba' => 'Beto', 'file' => UploadedFile::fake()->create('m.pdf', 50),
            'approval_mode' => 'approved', 'issued_at' => '2026-02-01', 'valid_until' => '2027-02-01',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-02-01', Regulation::where('code', 'P-COM-777')->first()->details['fecha_vigencia']);
    }

    public function test_el_comando_no_truena_con_un_proceso_en_la_papelera_editado_en_linea(): void
    {
        // v1 subida tal cual (con el vencimiento como elaboración) y v2 editada en línea (encabezado del sistema).
        [$regulation, $v1] = $this->systemDocument(bodyHtml: null);
        $v1->update(['is_current' => false]);
        $v2 = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2, 'is_current' => true, 'body_html' => '<p>x</p>',
            'file_path' => $v1->file_path, 'original_name' => 'doc_v2.docx', 'disk' => 'private',
            'issued_at' => '2026-02-01', 'valid_until' => '2027-02-01',
        ]);
        $regulation->update(['details' => array_merge($regulation->details, ['fecha_vigencia' => '2027-01-10'])]);
        $regulation->delete(); // papelera

        $this->artisan('fix:upload-elaboration-dates')->assertSuccessful();

        $this->assertSame('2026-01-10', Regulation::withTrashed()->find($regulation->id)->details['fecha_vigencia']);
        $this->assertStringContainsString('10/01/2026', $this->headerText($v2->file_path));
    }

    public function test_el_comando_corrige_la_fecha_de_elaboracion_de_lo_ya_subido(): void
    {
        [$regulation] = $this->systemDocument(bodyHtml: null);
        $regulation->update(['details' => array_merge($regulation->details, ['fecha_vigencia' => '2027-01-10'])]);

        $this->artisan('fix:upload-elaboration-dates', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame('2027-01-10', $regulation->fresh()->details['fecha_vigencia']);

        $this->artisan('fix:upload-elaboration-dates')->assertSuccessful();
        $this->assertSame('2026-01-10', $regulation->fresh()->details['fecha_vigencia']);
    }
}
