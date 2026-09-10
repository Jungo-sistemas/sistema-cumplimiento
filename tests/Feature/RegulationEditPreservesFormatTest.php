<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\Role;
use App\Models\User;
use App\Services\ApprovalFlowService;
use App\Services\RegulationChangeTableService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\Image as PhpWordImage;
use PhpOffice\PhpWord\IOFactory;
use Tests\TestCase;

/**
 * Escenario pedido: subir/editar un documento YA APROBADO (con diagrama de flujo embebido y el
 * encabezado/formato ya autorizado), agregar etiquetas @persona y #documento durante la edición,
 * y verificar que ni el diagrama ni el formato se destruyen, y que el documento vuelve
 * correctamente a revisión (no se queda "aprobado" con contenido que nadie revisó).
 *
 * A propósito NO llama a la IA real: RegulationChangeTableService se mockea (requiere una
 * `change_justification` no vacía para intentar llamar a la API de Anthropic — real, con
 * credenciales reales en .env — y esta prueba no necesita esa tabla para verificar preservación
 * de formato). AiProcedureGenerationService::sanitizeHtmlForWord() sí se usa real: es una función
 * pura de texto, sin llamadas externas.
 */
class RegulationEditPreservesFormatTest extends TestCase
{
    use RefreshDatabase;

    // PNG válido de 1x1 — placeholder del diagrama de flujo embebido (mismo mecanismo que
    // AiProcedureGenerationService::insertFlowDiagram(): <img src="data:image/png;base64,...">).
    private const TINY_PNG_B64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

    public function test_editar_documento_aprobado_con_menciones_preserva_diagrama_y_encabezado(): void
    {
        Storage::fake('private');
        Notification::fake();

        [$group, $company, $editor, $lider, $otherRegulation] = $this->setUpFixtures();

        $flow = app(ApprovalFlowService::class);

        $diagramImg = '<img src="data:image/png;base64,' . self::TINY_PNG_B64 . '" />';
        $originalBodyHtml = '<p style="text-align:center;color:#1A5276;font-weight:bold;">DIAGRAMA DE FLUJO DEL PROCESO</p>'
            . '<p style="text-align:center;">' . $diagramImg . '</p>'
            . '<p>Paso 1. Recepción de la solicitud.</p>';

        $regulation = Regulation::create([
            'group_id'        => $group->id,
            'company_id'      => $company->id,
            'process_type_id' => ProcessType::first()->id,
            'code'            => 'P-TEST-001',
            'name'            => 'Procedimiento de prueba',
            'impact_level'    => 'bajo',
            'approval_status' => 'approved',
            'flow_locked'     => true,
            'details'         => [
                'quien_elabora'  => 'Juan Pérez',
                'quien_aprueba'  => 'María López',
                'fecha_vigencia' => '2026-01-01',
            ],
            'is_active'  => true,
            'created_by' => $editor->id,
        ]);

        $version = RegulationVersion::create([
            'regulation_id'    => $regulation->id,
            'version_number'   => 1,
            'body_html'        => $originalBodyHtml,
            'file_path'        => "regulations/{$company->id}/{$regulation->id}/versions/doc_v1.docx",
            'original_name'    => 'doc_v1.docx',
            'disk'             => 'private',
            'mime_type'        => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'issued_at'        => now()->subMonths(2)->toDateString(),
            'valid_until'      => now()->addMonths(10)->toDateString(),
            'is_current'       => true,
            'uploaded_by'      => $editor->id,
        ]);

        // Flujo inicial ya se dio por completado antes de esta prueba (approval_status=approved
        // se seteó directo arriba); no hay approvals reales para esta versión — consistente con
        // un documento aprobado hace tiempo, antes de que existiera este registro de prueba.

        // Contenido "editado" tal como lo entregaría el editor TipTap: el diagrama intacto +
        // una mención @persona (pastilla, solo visual) + una mención #documento (link real).
        $personaSpan = '<span class="mention-persona" data-type="persona" data-id="' . $lider->id . '">@' . $lider->name . '</span>';
        $docLink     = '<a href="' . route('processes.show', ['regulation' => $otherRegulation->id, 'open_pdf' => 1]) . '" data-type="documento" data-id="' . $otherRegulation->id . '">#' . $otherRegulation->code . '</a>';

        $editedContent = '<p style="text-align:center;color:#1A5276;font-weight:bold;">DIAGRAMA DE FLUJO DEL PROCESO</p>'
            . '<p style="text-align:center;">' . $diagramImg . '</p>'
            . '<p>Paso 1. Recepción de la solicitud. Notificar a ' . $personaSpan . ' y consultar ' . $docLink . '.</p>';

        $this->mock(RegulationChangeTableService::class, function ($mock) {
            $mock->shouldReceive('generate')->andReturn(null);
        });

        $response = $this->actingAs($editor)->post(route('regulation-versions.saveEdit', $version), [
            'content'               => $editedContent,
            'change_description'    => 'Se agregó referencia a otro documento y se notificó al líder',
            'change_justification'  => '1. Agregar mención al líder. 2. Referenciar P-OTRO-002.',
        ]);

        $response->assertRedirect(route('processes.show', $regulation));

        $regulation->refresh();
        $this->assertCount(2, $regulation->versions()->get());

        $newVersion = $regulation->versions()->where('is_current', true)->firstOrFail();
        $this->assertEquals(2, $newVersion->version_number);
        $this->assertFalse($version->fresh()->is_current);

        // ── 1. El diagrama de flujo sobrevive tal cual (misma imagen, mismo base64) ──
        $this->assertStringContainsString(self::TINY_PNG_B64, $newVersion->body_html);

        // ── 2. Las menciones sobreviven al saneador (sanitizeHtmlForWord no las toca) ──
        $this->assertStringContainsString('@' . $lider->name, $newVersion->body_html);
        $this->assertStringContainsString('data-type="documento"', $newVersion->body_html);
        $this->assertStringContainsString((string) $otherRegulation->code, $newVersion->body_html);

        // ── 3. El documento ya no puede seguir "aprobado" con contenido sin revisar ──
        $this->assertEquals('pending_review', $regulation->approval_status);
        $this->assertDatabaseHas('regulation_approvals', [
            'regulation_id' => $regulation->id,
            'step_number'   => 1,
            'user_id'       => $lider->id,
            'status'        => 'pending',
        ]);

        // ── 4. El .docx generado es válido y el encabezado/diagrama quedaron embebidos de verdad ──
        $stored = Storage::disk('private')->get($newVersion->file_path);
        $this->assertNotNull($stored, 'El .docx de la nueva versión no se guardó en el disco.');

        $tmpPath = tempnam(sys_get_temp_dir(), 'test_docx_') . '.docx';
        file_put_contents($tmpPath, $stored);

        try {
            $phpWord = IOFactory::load($tmpPath);

            $sections = $phpWord->getSections();
            $this->assertNotEmpty($sections, 'El .docx generado no tiene secciones — quedó corrupto.');

            $hasImage = false;
            foreach ($sections as $section) {
                $hasImage = $hasImage || $this->containsImage($section);

                // El encabezado fijo (RegulationDocxHeaderBuilder) debe seguir presente e
                // idéntico en estructura — se aplica siempre, sin depender del contenido editado.
                $headers = $section->getHeaders();
                $this->assertNotEmpty($headers, 'El encabezado fijo (formato ya autorizado) se perdió al editar.');
            }

            $this->assertTrue($hasImage, 'El diagrama de flujo no quedó embebido como imagen real en el .docx.');
        } finally {
            @unlink($tmpPath);
        }
    }

    private function containsImage(object $container): bool
    {
        if ($container instanceof PhpWordImage) {
            return true;
        }

        if (! method_exists($container, 'getElements')) {
            return false;
        }

        foreach ($container->getElements() as $element) {
            if ($this->containsImage($element)) {
                return true;
            }
        }

        return false;
    }

    /** @return array{0: Group, 1: Company, 2: User, 3: User, 4: Regulation} */
    private function setUpFixtures(): array
    {
        $group       = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company     = Company::create(['name' => 'Demo', 'group_id' => $group->id]);
        $processType = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);

        $adminRole = Role::create(['name' => 'Administrador', 'slug' => 'admin']);
        $opRole    = Role::create(['name' => 'Operativo', 'slug' => 'operative']);

        $editor = User::factory()->create([
            'group_id' => $group->id, 'scope_level' => 'group',
            'role_id'  => $adminRole->id, 'status' => 'active',
        ]);

        $liderPos = JobPosition::create(['group_id' => $group->id, 'slug' => 'lider', 'name' => 'Líder']);
        $lider    = User::factory()->create([
            'group_id' => $group->id, 'scope_level' => 'group',
            'role_id'  => $opRole->id, 'status' => 'active',
        ]);
        $lider->jobPositions()->attach($liderPos->id);

        $otherRegulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $processType->id,
            'code' => 'P-OTRO-002', 'name' => 'Otro procedimiento', 'is_active' => true,
        ]);

        return [$group, $company, $editor, $lider, $otherRegulation];
    }
}
