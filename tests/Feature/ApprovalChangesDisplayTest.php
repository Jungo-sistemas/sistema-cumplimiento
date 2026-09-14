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
use App\Notifications\ApprovalRequestedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Decisión explícita del usuario (2026-09): la tabla de "qué cambió" sección por sección
 * (RegulationChangeDiffService) puede salir muy larga cuando varias secciones cambiaron en la
 * misma edición — el correo de aprobación ya NO debe traerla en absoluto (solo el resumen corto
 * change_description/change_justification), y la pantalla "Mis aprobaciones" dentro de la
 * plataforma debe limitarla a 1 sola fila (la más reciente) en vez de listarlas todas.
 */
class ApprovalChangesDisplayTest extends TestCase
{
    use RefreshDatabase;

    private function section(string $title, string $text): string
    {
        return '<p style="text-align: justify; margin-top: 10pt; margin-bottom: 4pt;">'
            . '<span style="color: #1A5276; font-weight: bold; text-transform: uppercase;">' . $title . '</span></p>'
            . "<p>{$text}</p>";
    }

    private function bodyHtml(string $objetivo, string $anexos): string
    {
        $html = $this->section('Objetivo', $objetivo);
        foreach (['Alcance', 'Tópicos', 'Indicadores', 'Definiciones y Abreviaturas', 'Diagrama de Flujo del Proceso', 'Descripción del Proceso / Actividades', 'Riesgos conocidos y errores frecuentes', 'Requerimientos normativos y legales'] as $t) {
            $html .= $this->section($t, "Contenido de {$t}.");
        }

        return $html . $this->section('Anexos', $anexos);
    }

    public function test_el_correo_de_aprobacion_no_incluye_la_tabla_de_secciones(): void
    {
        Notification::fake();

        $group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'Demo', 'group_id' => $group->id]);
        $type    = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $opRole  = Role::create(['name' => 'Operativo', 'slug' => 'operative']);

        $lider = User::factory()->create(['group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $opRole->id, 'status' => 'active']);
        $lider->jobPositions()->attach(JobPosition::create(['group_id' => $group->id, 'slug' => 'lider', 'name' => 'Líder'])->id);

        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-TEST-001', 'name' => 'Procedimiento de prueba', 'impact_level' => 'bajo',
            'approval_status' => 'pending_review', 'flow_locked' => true, 'details' => [], 'is_active' => true,
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1,
            'body_html' => $this->bodyHtml('Objetivo viejo.', 'Anexo viejo.'),
            'is_current' => false, 'issued_at' => now()->subMonth(),
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2,
            'body_html' => $this->bodyHtml('Objetivo NUEVO distinto.', 'Anexo NUEVO distinto.'),
            'change_description' => 'Se actualizó el objetivo y los anexos',
            'is_current' => true, 'issued_at' => now(),
        ]);

        app(ApprovalFlowService::class)->initFlow($regulation->fresh());

        $mail = (new ApprovalRequestedNotification($regulation->fresh()))->toMail($lider);
        $html = View::make($mail->view, $mail->viewData)->render();

        // Sin tabla de secciones en absoluto — ni encabezados "Antes"/"Después", ni el título de
        // ninguna de las dos secciones que sí cambiaron.
        $this->assertStringNotContainsString('Antes', $html);
        $this->assertStringNotContainsString('Después', $html);
        $this->assertStringNotContainsString('Objetivo NUEVO', $html);
        $this->assertStringNotContainsString('Anexo NUEVO', $html);

        // El resumen corto sí se sigue mostrando.
        $this->assertStringContainsString('Se actualizó el objetivo y los anexos', $html);
    }

    public function test_mis_aprobaciones_muestra_solo_la_seccion_mas_reciente(): void
    {
        $group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'Demo', 'group_id' => $group->id]);
        $type    = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $opRole  = Role::create(['name' => 'Operativo', 'slug' => 'operative']);

        $lider = User::factory()->create(['group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $opRole->id, 'status' => 'active']);
        $lider->jobPositions()->attach(JobPosition::create(['group_id' => $group->id, 'slug' => 'lider', 'name' => 'Líder'])->id);

        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-TEST-002', 'name' => 'Otro procedimiento', 'impact_level' => 'bajo',
            'approval_status' => 'pending_review', 'flow_locked' => true, 'details' => [], 'is_active' => true,
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1,
            'body_html' => $this->bodyHtml('Objetivo viejo.', 'Anexo viejo.'),
            'is_current' => false, 'issued_at' => now()->subMonth(),
        ]);
        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2,
            'body_html' => $this->bodyHtml('Objetivo NUEVO distinto.', 'Anexo NUEVO distinto.'),
            'is_current' => true, 'issued_at' => now(),
        ]);

        app(ApprovalFlowService::class)->initFlow($regulation->fresh());

        $html = $this->actingAs($lider)->get(route('my-approvals.index'))->getContent();

        // De las 2 secciones que en verdad cambiaron (Objetivo y Anexos), solo debe verse 1 en la tabla.
        $objetivoAparece = str_contains($html, 'Objetivo NUEVO');
        $anexosAparece   = str_contains($html, 'Anexo NUEVO');

        $this->assertNotEquals($objetivoAparece, $anexosAparece, 'Deben aparecer exactamente una de las dos secciones cambiadas, no ambas ni ninguna.');
    }
}
