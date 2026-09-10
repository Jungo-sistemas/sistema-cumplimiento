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
use App\Notifications\ApprovalFlowMemberNotification;
use App\Notifications\ApprovalOverdueEscalationNotification;
use App\Notifications\ApprovalReminderNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\ApprovalStepUnassignedNotification;
use App\Notifications\RegulationAccessRequestedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationReadyToResubmitNotification;
use App\Notifications\RegulationRejectedNotification;
use App\Services\ApprovalFlowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Notification::fake() (usado en ApprovalFlowTest) confirma que se DESPACHA la notificación
 * correcta a la persona correcta, pero NUNCA renderiza la vista Blade real — un error de
 * "Undefined variable" o similar en cualquier plantilla de resources/views/emails/processes/
 * pasaría inadvertido ahí. Esta prueba sí renderiza cada plantilla con datos reales del tipo que
 * de verdad les llega en producción, sin enviar ningún correo (nunca toca el mailer real).
 */
class ApprovalEmailRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_las_plantillas_de_correo_de_procesos_renderizan_sin_error(): void
    {
        Notification::fake();

        $group   = Group::create(['name' => 'Test', 'slug' => 'test', 'is_active' => true]);
        $company = Company::create(['name' => 'Demo', 'group_id' => $group->id]);
        $type    = ProcessType::create(['group_id' => $group->id, 'name' => 'Operaciones', 'is_active' => true]);
        $opRole  = Role::create(['name' => 'Operativo', 'slug' => 'operative']);

        $lider = User::factory()->create(['group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $opRole->id, 'status' => 'active']);
        $liderPos = JobPosition::create(['group_id' => $group->id, 'slug' => 'lider', 'name' => 'Líder']);
        $lider->jobPositions()->attach($liderPos->id);

        $creator   = User::factory()->create(['group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $opRole->id, 'status' => 'active']);
        $requester = User::factory()->create(['group_id' => $group->id, 'scope_level' => 'group', 'role_id' => $opRole->id, 'status' => 'active']);

        $regulation = Regulation::create([
            'group_id' => $group->id, 'company_id' => $company->id, 'process_type_id' => $type->id,
            'code' => 'P-TEST-001', 'name' => 'Procedimiento de prueba', 'impact_level' => 'bajo',
            'approval_status' => 'pending_review', 'flow_locked' => true,
            'details' => ['quien_elabora' => 'Juan Pérez', 'quien_aprueba' => 'María López'],
            'is_active' => true, 'created_by' => $creator->id,
        ]);

        RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 1,
            'body_html' => '<p>Contenido de la versión anterior.</p>',
            'is_current' => false, 'issued_at' => now()->subMonth(),
        ]);
        $current = RegulationVersion::create([
            'regulation_id' => $regulation->id, 'version_number' => 2,
            'body_html' => '<p>Contenido nuevo, editado.</p>',
            'change_description' => 'Se actualizó el paso 2', 'change_justification' => 'Corrección de error operativo',
            'is_current' => true, 'issued_at' => now(),
        ]);

        app(ApprovalFlowService::class)->initFlow($regulation);

        $cases = [
            'approval-requested'          => new ApprovalRequestedNotification($regulation->fresh()),
            'approval-flow-member'        => new ApprovalFlowMemberNotification($regulation, 2),
            'regulation-approved'         => new RegulationApprovedNotification($regulation),
            'regulation-rejected'         => new RegulationRejectedNotification($regulation, 'Falta información', $lider),
            'regulation-ready-to-resubmit' => new RegulationReadyToResubmitNotification($regulation),
            'regulation-access-requested' => new RegulationAccessRequestedNotification($regulation, $requester),
            'approval-reminder'           => new ApprovalReminderNotification($regulation, 7, 1),
            'approval-overdue-escalation' => new ApprovalOverdueEscalationNotification($regulation, $lider, 21),
            'approval-step-unassigned'    => new ApprovalStepUnassignedNotification($regulation, 3, ['Gerente', 'Dirección']),
        ];

        foreach ($cases as $slug => $notification) {
            $mail = $notification->toMail($lider);
            $this->assertInstanceOf(MailMessage::class, $mail, "{$slug}: toMail() no devolvió un MailMessage");

            try {
                $html = View::make($mail->view, $mail->viewData)->render();
            } catch (\Throwable $e) {
                $this->fail("La plantilla de correo '{$slug}' truena al renderizar: " . $e->getMessage());
            }

            $this->assertStringContainsString($regulation->name, $html, "{$slug}: no muestra el nombre del reglamento");
        }

        // También se recorre RegulationRejectedNotification con rejectedBy=null (rechazo del
        // sistema o dato histórico incompleto) — la vista debe tolerarlo sin tronar.
        $mail = (new RegulationRejectedNotification($regulation, 'Motivo', null))->toMail($creator);
        View::make($mail->view, $mail->viewData)->render();
        $this->assertTrue(true);
    }
}
