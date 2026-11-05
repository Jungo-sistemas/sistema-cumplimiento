<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsDocxWithImages;
use Tests\TestCase;

/**
 * Reparar una versión editada con el diagrama deformado (F-MDI-043) a partir del .docx original:
 * cambia solo las imágenes y conserva el resto, incluidas las etiquetas que se pusieron al editar.
 */
class FixRegulationVersionImagesTest extends TestCase
{
    use BuildsDocxWithImages;
    use RefreshDatabase;

    public function test_cambia_las_imagenes_por_las_del_original_y_conserva_las_etiquetas(): void
    {
        Storage::fake('private');

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ATQ', 'group_id' => $group->id]);
        $type = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'F-MDI-043', 'name' => 'DISCIPLINA OPERATIVA ATQS', 'is_active' => true,
        ]);

        // Como quedó tras editar: la imagen cruda (400×200) y una etiqueta de persona.
        $raw = base64_encode($this->twoColorPng());
        $body = '<p>Responsable: <span data-type="mention" data-id="7">@Ana</span></p>'
            . '<p><img src="data:image/png;base64,' . $raw . '" style="width: 400px; height: 200px;" /></p>';
        Storage::disk('private')->put('regulations/v2.docx', 'docx viejo');
        Storage::disk('private')->put('regulations/v2.docx.preview.pdf', 'pdf viejo');
        $version = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2, 'is_current' => true,
            'file_path' => 'regulations/v2.docx', 'original_name' => 'v2.docx', 'body_html' => $body,
        ]);

        // En el Word original la imagen se mostraba cuadrada (estirada a 200×200).
        $original = $this->docx($this->drawing('rIdImg', 200, 200));

        $this->artisan('fix:regulation-version-images', ['version' => $version->id, 'original' => $original, '--dry-run' => true])
            ->assertSuccessful();
        $this->assertSame($body, $version->fresh()->body_html);

        $this->artisan('fix:regulation-version-images', ['version' => $version->id, 'original' => $original])
            ->assertSuccessful();

        $html = $version->fresh()->body_html;
        $this->assertStringContainsString('<span data-type="mention" data-id="7">@Ana</span>', $html);
        $this->assertStringContainsString('width: 200px; height: 200px;', $html);
        preg_match('/base64,([^"]+)/', $html, $b);
        $size = getimagesizefromstring(base64_decode($b[1]));
        $this->assertEqualsWithDelta(1.0, $size[0] / $size[1], 0.01);

        $disk = Storage::disk('private');
        $this->assertStringStartsWith('PK', $disk->get('regulations/v2.docx'));
        $this->assertFalse($disk->exists('regulations/v2.docx.preview.pdf'));
        $this->assertCount(1, array_filter($disk->files('regulations'), fn ($f) => str_contains($f, '.respaldo-')));
    }

    public function test_si_no_coincide_el_numero_de_imagenes_no_toca_nada(): void
    {
        Storage::fake('private');

        $group = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'ATQ', 'group_id' => $group->id]);
        $type = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'F-X', 'name' => 'X', 'is_active' => true,
        ]);
        $body = '<p>Sin imágenes</p>';
        $version = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2, 'is_current' => true,
            'file_path' => 'regulations/x.docx', 'original_name' => 'x.docx', 'body_html' => $body,
        ]);

        $this->artisan('fix:regulation-version-images', ['version' => $version->id, 'original' => $this->docx($this->drawing('rIdImg', 200, 200))])
            ->assertFailed();
        $this->assertSame($body, $version->fresh()->body_html);
    }
}
