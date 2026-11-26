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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Un documento rechazado que se corrigió fuera de la plataforma: se sube el archivo y reemplaza
 * al de la versión actual, sin crear otra versión en el historial.
 */
class RejectedCorrectedFileTest extends TestCase
{
    use RefreshDatabase;

    private Group $group;
    private Company $company;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');
        Notification::fake();

        $this->group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $this->company = Company::create(['name' => 'ALCOM', 'group_id' => $this->group->id, 'show_in_processes' => true]);
        $this->admin   = User::factory()->create([
            'name' => 'Laura Admin', 'group_id' => $this->group->id, 'scope_level' => 'group', 'status' => 'active',
            'role_id' => Role::create(['name' => 'Administrador', 'slug' => 'admin'])->id,
        ]);
    }

    /** @return array{Regulation, RegulationVersion} */
    private function regulation(string $status): array
    {
        $type = ProcessType::create(['group_id' => $this->group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $regulation = Regulation::create([
            'group_id' => $this->group->id, 'company_id' => $this->company->id, 'process_type_id' => $type->id,
            'code' => 'P-ALC-001', 'name' => 'ALTA DE PROVEEDORES', 'is_active' => true,
            'approval_status' => $status, 'impact_level' => 'bajo',
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1, 'is_current' => false,
            'file_path' => 'regulations/test/v1.docx', 'original_name' => 'v1.docx', 'disk' => 'private',
            'issued_at' => now()->toDateString(),
        ]);
        Storage::disk('private')->put('regulations/test/v2.docx', 'rechazado');
        Storage::disk('private')->put('regulations/test/v2.docx.preview.pdf', 'pdf');
        $current = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2, 'is_current' => true,
            'file_path' => 'regulations/test/v2.docx', 'original_name' => 'v2.docx', 'disk' => 'private',
            'body_html' => '<p>Editado en línea</p>', 'change_description' => 'Se cambió el alcance',
            'issued_at' => now()->subWeek()->toDateString(),
        ]);

        return [$regulation, $current];
    }

    public function test_el_archivo_corregido_reemplaza_la_version_actual_sin_crear_otra(): void
    {
        [$regulation, $current] = $this->regulation('rejected');

        $this->actingAs($this->admin)
            ->get(route('processes.flow', $regulation))
            ->assertOk()
            ->assertSee('Cargar documento corregido');

        $this->actingAs($this->admin)
            ->post(route('regulation-versions.replaceRejected', $current), [
                'file' => UploadedFile::fake()->create('ALTA DE PROVEEDORES corregido.docx', 50),
                'change_description' => 'Se ajustaron los pasos según el rechazo',
            ])
            ->assertSessionHas('success');

        $this->assertSame(2, $regulation->versions()->count());
        $current->refresh();
        $this->assertSame(2, $current->version_number);
        $this->assertTrue($current->is_current);
        $this->assertSame('ALTA DE PROVEEDORES corregido.docx', $current->original_name);
        $this->assertSame('Se ajustaron los pasos según el rechazo', $current->change_description);
        $this->assertNull($current->body_html); // el contenido ahora es el del archivo subido
        $this->assertNotSame('regulations/test/v2.docx', $current->file_path);
        Storage::disk('private')->assertExists($current->file_path);
        Storage::disk('private')->assertMissing('regulations/test/v2.docx');
        Storage::disk('private')->assertMissing('regulations/test/v2.docx.preview.pdf');

        // Sigue rechazado hasta que se re-envíe.
        $this->assertSame('rejected', $regulation->fresh()->approval_status);
    }

    public function test_sin_descripcion_se_conserva_la_anterior(): void
    {
        [, $current] = $this->regulation('rejected');

        $this->actingAs($this->admin)
            ->post(route('regulation-versions.replaceRejected', $current), [
                'file' => UploadedFile::fake()->create('corregido.pdf', 50),
            ])
            ->assertSessionHas('success');

        $this->assertSame('Se cambió el alcance', $current->fresh()->change_description);
    }

    public function test_solo_en_documentos_rechazados(): void
    {
        [$regulation, $current] = $this->regulation('approved');

        $this->actingAs($this->admin)
            ->get(route('processes.flow', $regulation))
            ->assertDontSee('Cargar documento corregido');

        $this->actingAs($this->admin)
            ->post(route('regulation-versions.replaceRejected', $current), [
                'file' => UploadedFile::fake()->create('corregido.docx', 50),
            ])
            ->assertForbidden();

        $this->assertSame('v2.docx', $current->fresh()->original_name);
    }

    public function test_sin_archivo_avisa_y_no_cambia_nada(): void
    {
        [, $current] = $this->regulation('rejected');

        $this->actingAs($this->admin)
            ->post(route('regulation-versions.replaceRejected', $current), [])
            ->assertSessionHas('error', 'Elige el archivo corregido.');

        $this->assertSame('v2.docx', $current->fresh()->original_name);
    }
}
