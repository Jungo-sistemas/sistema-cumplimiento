<?php

namespace App\Support;

use App\Enums\RequirementStatus;
use App\Mail\RegulationSharedMail;
use App\Mail\TwoFactorCodeMail;
use App\Mail\UserInvitationMail;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use App\Models\Company;
use App\Models\InvestmentRequest;
use App\Models\InvestmentRequestApproval;
use App\Models\JobPosition;
use App\Models\License;
use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\RegulationShare;
use App\Models\RequirementTemplate;
use App\Models\Role;
use App\Models\User;
use App\Notifications;
use Illuminate\Mail\Mailable;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Vista previa de todos los correos del sistema (Superadmin → Correos), con los mismos
 * Notification/Mailable que se envían de verdad pero alimentados con datos de ejemplo.
 *
 * Los modelos se arman en memoria (new + setRelation) y nunca se guardan: no se muestran datos
 * de clientes, no se envía nada y no se escribe en la base. Algunas notificaciones consultan
 * relaciones (p. ej. aprobaciones previas) — con ids de ejemplo esas lecturas regresan vacío, y
 * aquí se rellenan con datos de ejemplo para que la vista previa muestre el correo completo.
 *
 * Al agregar un correo nuevo al sistema, agrégalo también aquí.
 */
class EmailPreviews
{
    /** Id de ejemplo para armar las ligas de los correos; no corresponde a ningún registro. */
    private const FAKE_ID = 999999;

    /** @return array<string, array{group: string, label: string, build: \Closure}> */
    public function catalog(): array
    {
        return [
            // ── Procesos ──
            'approval-requested' => ['group' => 'Procesos', 'label' => 'Aprobación requerida', 'build' => function () {
                $mail = (new Notifications\ApprovalRequestedNotification($this->regulation()))->toMail($this->user());
                $mail->viewData['previousApprovers'] = collect([
                    $this->regulationApproval('Líder', 'Carlos Ramírez', 3),
                    $this->regulationApproval('Jefe', 'Laura Gómez', 1),
                ]);

                return $mail;
            }],
            'approval-reminder' => ['group' => 'Procesos', 'label' => 'Recordatorio de aprobación', 'build' => fn () => (new Notifications\ApprovalReminderNotification($this->regulation(), 3, 2))->toMail($this->user())],
            'approval-overdue-escalation' => ['group' => 'Procesos', 'label' => 'Aprobación sin resolver (a admins)', 'build' => fn () => (new Notifications\ApprovalOverdueEscalationNotification($this->regulation(), $this->user('Carlos Ramírez', 'carlos.ramirez@ejemplo.com'), 7))->toMail($this->user())],
            'approval-step-unassigned' => ['group' => 'Procesos', 'label' => 'Flujo detenido: puesto sin asignar', 'build' => fn () => (new Notifications\ApprovalStepUnassignedNotification($this->regulation(), 2, ['Jefe']))->toMail($this->user())],
            'regulation-approved' => ['group' => 'Procesos', 'label' => 'Documento aprobado', 'build' => fn () => (new Notifications\RegulationApprovedNotification($this->regulation()))->toMail($this->user())],
            'regulation-rejected' => ['group' => 'Procesos', 'label' => 'Documento rechazado', 'build' => fn () => (new Notifications\RegulationRejectedNotification($this->regulation(), 'Falta actualizar el anexo F-EJE-010 y la tabla de responsables.', $this->user('Laura Gómez', 'laura.gomez@ejemplo.com')))->toMail($this->user())],
            'regulation-ready-to-resubmit' => ['group' => 'Procesos', 'label' => 'Documento corregido, listo para reenviar', 'build' => fn () => (new Notifications\RegulationReadyToResubmitNotification($this->regulation()))->toMail($this->user())],
            'regulation-access-requested' => ['group' => 'Procesos', 'label' => 'Solicitud de acceso', 'build' => fn () => (new Notifications\RegulationAccessRequestedNotification($this->regulation(), $this->user('Carlos Ramírez', 'carlos.ramirez@ejemplo.com')))->toMail($this->user())],
            'regulation-access-granted' => ['group' => 'Procesos', 'label' => 'Acceso otorgado', 'build' => fn () => (new Notifications\RegulationAccessGrantedNotification($this->regulation()))->toMail($this->user())],
            'regulation-shared' => ['group' => 'Procesos', 'label' => 'Procedimiento compartido', 'build' => function () {
                $share = (new RegulationShare)->forceFill(['id' => self::FAKE_ID, 'token' => 'ejemplo-token']);
                $share->setRelation('recipient', $this->user());

                return new RegulationSharedMail($this->regulation(), $share, $this->user('Laura Gómez', 'laura.gomez@ejemplo.com'));
            }],

            // ── Auditorías ──
            'audit-upcoming' => ['group' => 'Auditorías', 'label' => 'Tienes una auditoría (días antes)', 'build' => fn () => (new Notifications\AuditUpcomingNotification($this->audit()))->toMail($this->user())],
            'audit-rescheduled' => ['group' => 'Auditorías', 'label' => 'Cambió la fecha de auditoría', 'build' => fn () => (new Notifications\AuditScheduledNotification($this->audit(), true))->toMail($this->user())],
            'audit-completed' => ['group' => 'Auditorías', 'label' => 'Resultado de auditoría', 'build' => function () {
                $mail = (new Notifications\AuditCompletedNotification($this->audit()))->toMail($this->user());
                $mail->viewData['rows']['Hallazgos'] = '2';

                return $mail;
            }],
            'audit-finding-assigned' => ['group' => 'Auditorías', 'label' => 'Nuevo hallazgo (responsable)', 'build' => fn () => (new Notifications\AuditFindingAssignedNotification($this->finding()))->toMail($this->user())],
            'audit-finding-critical-owner' => ['group' => 'Auditorías', 'label' => 'Hallazgo crítico (responsable del procedimiento)', 'build' => fn () => (new Notifications\AuditFindingAssignedNotification($this->finding('critico'), asProcessOwner: true))->toMail($this->user('Laura Gómez', 'laura.gomez@ejemplo.com'))],
            'audit-root-cause-submitted' => ['group' => 'Auditorías', 'label' => 'Causa raíz por validar (auditor)', 'build' => fn () => (new Notifications\AuditFindingSubmittedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_IN_REVIEW, AuditFindingSubmission::KIND_ROOT_CAUSE)))->toMail($this->user())],
            'audit-root-cause-approved' => ['group' => 'Auditorías', 'label' => 'Causa raíz aprobada: seguimiento', 'build' => fn () => (new Notifications\AuditFindingReviewedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_ACCEPTED, AuditFindingSubmission::KIND_ROOT_CAUSE)))->toMail($this->user())],
            'audit-root-cause-returned' => ['group' => 'Auditorías', 'label' => 'Causa raíz devuelta', 'build' => fn () => (new Notifications\AuditFindingReviewedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_REJECTED, AuditFindingSubmission::KIND_ROOT_CAUSE)))->toMail($this->user())],
            'audit-evidence-submitted' => ['group' => 'Auditorías', 'label' => 'Evidencia por verificar (auditor)', 'build' => function () {
                $mail = (new Notifications\AuditFindingSubmittedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_IN_REVIEW)))->toMail($this->user());
                $mail->viewData['rows']['Archivos'] = '2';

                return $mail;
            }],
            'audit-evidence-accepted' => ['group' => 'Auditorías', 'label' => 'Evidencia verificada: hallazgo cerrado', 'build' => fn () => (new Notifications\AuditFindingReviewedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_ACCEPTED)))->toMail($this->user())],
            'audit-evidence-rejected' => ['group' => 'Auditorías', 'label' => 'Evidencia rechazada', 'build' => fn () => (new Notifications\AuditFindingReviewedNotification($this->finding(), $this->submission(AuditFindingSubmission::STATUS_REJECTED)))->toMail($this->user())],
            'audit-finding-alert-critical' => ['group' => 'Auditorías', 'label' => 'Aviso: hallazgo crítico', 'build' => fn () => (new Notifications\AuditFindingAlertNotification($this->finding('critico'), Notifications\AuditFindingAlertNotification::REASON_CRITICAL))->toMail($this->user())],
            'audit-finding-alert-overdue' => ['group' => 'Auditorías', 'label' => 'Aviso: plazo vencido', 'build' => fn () => (new Notifications\AuditFindingAlertNotification($this->finding('critico'), Notifications\AuditFindingAlertNotification::REASON_OVERDUE))->toMail($this->user())],

            // ── Inversiones (Anexo 8) ──
            'investment-approval-requested' => ['group' => 'Inversiones', 'label' => 'Aprobación de inversión requerida', 'build' => function () {
                $mail = (new Notifications\InvestmentApprovalRequestedNotification($this->investment()))->toMail($this->user());
                $mail->viewData['position'] = 2;
                $mail->viewData['total'] = 3;
                $mail->viewData['previousApprovers'] = collect([$this->investmentApproval(1, 'Carlos Ramírez', 'approved')]);

                return $mail;
            }],
            'investment-approved' => ['group' => 'Inversiones', 'label' => 'Inversión aprobada', 'build' => function () {
                $mail = (new Notifications\InvestmentRequestDecidedNotification($this->investment(), 'approved'))->toMail($this->user());
                $mail->viewData['approvals'] = collect([
                    $this->investmentApproval(1, 'Carlos Ramírez', 'approved'),
                    $this->investmentApproval(2, 'Laura Gómez', 'approved'),
                ]);

                return $mail;
            }],
            'investment-rejected' => ['group' => 'Inversiones', 'label' => 'Inversión rechazada', 'build' => function () {
                $rejectedBy = $this->user('Laura Gómez', 'laura.gomez@ejemplo.com');
                $mail = (new Notifications\InvestmentRequestDecidedNotification($this->investment(), 'rejected', 'El monto excede el presupuesto autorizado para este trimestre.', $rejectedBy))->toMail($this->user());
                $mail->viewData['approvals'] = collect([
                    $this->investmentApproval(1, 'Carlos Ramírez', 'approved'),
                    $this->investmentApproval(2, 'Laura Gómez', 'rejected'),
                    $this->investmentApproval(3, 'Roberto Díaz', 'cancelled'),
                ]);

                return $mail;
            }],

            // ── Cumplimiento ──
            'requirements-due-1' => ['group' => 'Cumplimiento', 'label' => 'Requisitos que vencen mañana', 'build' => fn () => (new Notifications\RequirementDueSoonNotification($this->requirements(1), 1))->toMail($this->user())],
            'requirements-due-7' => ['group' => 'Cumplimiento', 'label' => 'Requisitos que vencen en 7 días', 'build' => fn () => (new Notifications\RequirementDueSoonNotification($this->requirements(7), 7))->toMail($this->user())],
            'requirements-due-30' => ['group' => 'Cumplimiento', 'label' => 'Requisitos que vencen en 30 días', 'build' => fn () => (new Notifications\RequirementDueSoonNotification($this->requirements(30), 30))->toMail($this->user())],
            'requirements-due-60' => ['group' => 'Cumplimiento', 'label' => 'Requisitos que vencen en 60 días', 'build' => fn () => (new Notifications\RequirementDueSoonNotification($this->requirements(60), 60))->toMail($this->user())],

            // ── Cuenta y acceso ──
            'user-invitation' => ['group' => 'Cuenta y acceso', 'label' => 'Invitación a la plataforma', 'build' => function () {
                $user = $this->user()->forceFill(['invite_token' => 'ejemplo-token', 'invite_expires_at' => now()->addDays(3)]);
                $user->setRelation('role', (new Role)->forceFill(['name' => 'Operativo']));

                return new UserInvitationMail($user);
            }],
            'two-factor-code' => ['group' => 'Cuenta y acceso', 'label' => 'Código de verificación', 'build' => fn () => new TwoFactorCodeMail($this->user(), '482913')],
            'reset-password' => ['group' => 'Cuenta y acceso', 'label' => 'Restablecer contraseña', 'build' => fn () => (new Notifications\ResetPasswordNotification('token-de-ejemplo'))->toMail($this->user())],

            // ── Licencias (Superadmin) ──
            'license-expiring-15' => ['group' => 'Licencias', 'label' => 'Licencia vence en 15 días', 'build' => fn () => (new Notifications\LicenseExpiringNotification($this->license(15), 15))->toMail($this->user())],
            'license-expiring-1' => ['group' => 'Licencias', 'label' => 'Licencia vence mañana', 'build' => fn () => (new Notifications\LicenseExpiringNotification($this->license(1), 1))->toMail($this->user())],
            'license-expired' => ['group' => 'Licencias', 'label' => 'Licencia vencida', 'build' => fn () => (new Notifications\LicenseExpiredNotification($this->license(-1)))->toMail($this->user())],
        ];
    }

    /** @return array{subject: string, html: string} */
    public function render(string $key): array
    {
        $entry = $this->catalog()[$key] ?? null;
        abort_unless($entry, 404);

        $mail = ($entry['build'])();

        if ($mail instanceof MailMessage) {
            return ['subject' => (string) $mail->subject, 'html' => (string) $mail->render()];
        }

        /** @var Mailable $mail */
        $html = (string) $mail->render();
        $subject = $mail->subject ?: (method_exists($mail, 'envelope') ? $mail->envelope()->subject : '');

        return ['subject' => (string) $subject, 'html' => $html];
    }

    // ── Datos de ejemplo (en memoria, nunca se guardan) ──────────────────────────────────────

    private function user(string $name = 'Ana Martínez', string $email = 'ana.martinez@ejemplo.com'): User
    {
        $user = (new User)->forceFill(['id' => self::FAKE_ID, 'name' => $name, 'email' => $email]);
        $user->setRelation('company', $this->company());

        return $user;
    }

    private function company(): Company
    {
        return (new Company)->forceFill(['id' => self::FAKE_ID, 'name' => 'Empresa Ejemplo S.A. de C.V.', 'process_name' => 'EJEMPLO']);
    }

    private function regulation(): Regulation
    {
        $regulation = (new Regulation)->forceFill([
            'id'            => self::FAKE_ID,
            'name'          => 'Control de documentos',
            'code'          => 'P-EJE-001',
            'document_type' => 'Procedimiento',
            'impact_level'  => 'medio',
            'details'       => ['quien_elabora' => 'Ana Martínez — Jefa de Calidad'],
        ]);
        $regulation->setRelation('company', $this->company());
        $regulation->setRelation('creator', $this->user());

        return $regulation;
    }

    private function regulationApproval(string $position, string $name, int $daysAgo): RegulationApproval
    {
        $approval = (new RegulationApproval)->forceFill(['status' => 'approved', 'decided_at' => now()->subDays($daysAgo)]);
        $approval->setRelation('user', $this->user($name));
        $approval->setRelation('jobPosition', (new JobPosition)->forceFill(['name' => $position]));

        return $approval;
    }

    private function audit(): Audit
    {
        $audit = (new Audit)->forceFill([
            'id'           => self::FAKE_ID,
            'planned_for'  => now()->startOfMonth(),
            'scheduled_at' => now()->addDays(3)->setTime(10, 0),
            'result'       => 'Satisfactoria',
        ]);
        $audit->setRelation('regulation', $this->regulation());
        $audit->setRelation('auditor', $this->user('Roberto Díaz', 'roberto.diaz@ejemplo.com'));
        $audit->setRelation('company', $this->company());

        return $audit;
    }

    private function finding(string $severity = 'no_critico'): AuditFinding
    {
        $finding = (new AuditFinding)->forceFill([
            'id'          => self::FAKE_ID,
            'point'       => '4.2 Control de registros',
            'description' => 'Los registros de capacitación del último trimestre no tienen firma del responsable.',
            'severity'    => $severity,
            'status'      => AuditFinding::STATUS_ROOT_CAUSE_PENDING,
            'due_at'      => now()->addDays(3),
        ]);
        $finding->setRelation('audit', $this->audit());
        $finding->setRelation('responsible', $this->user());

        return $finding;
    }

    private function submission(string $status, string $kind = AuditFindingSubmission::KIND_EVIDENCE): AuditFindingSubmission
    {
        $submission = (new AuditFindingSubmission)->forceFill([
            'id'             => self::FAKE_ID,
            'kind'           => $kind,
            'root_cause'     => 'El formato de la lista de asistencia no tenía un espacio para la firma del responsable.',
            'actions'        => "Agregar la firma del responsable al formato.\nRecabar las firmas faltantes del trimestre.",
            'status'         => $status,
            'comment'        => 'Adjunto las listas de asistencia firmadas.',
            'review_comment' => $status === AuditFindingSubmission::STATUS_REJECTED
                ? 'Falta la firma del responsable en la lista de octubre.'
                : 'Evidencia completa.',
        ]);
        $submission->setRelation('user', $this->user());

        return $submission;
    }

    private function investment(): InvestmentRequest
    {
        $request = (new InvestmentRequest)->forceFill([
            'id'            => self::FAKE_ID,
            'concept'       => 'Compra de montacargas para almacén',
            'amount'        => 450000,
            'deadline_at'   => now()->addDays(10),
            'original_name' => 'Cotizacion_montacargas.pdf',
        ]);
        $request->setRelation('company', $this->company());
        $request->setRelation('entity', null);
        $request->setRelation('requester', $this->user('Carlos Ramírez', 'carlos.ramirez@ejemplo.com'));

        return $request;
    }

    private function investmentApproval(int $order, string $name, string $status): InvestmentRequestApproval
    {
        $approval = (new InvestmentRequestApproval)->forceFill([
            'sequence_order' => $order,
            'status'         => $status,
            'decided_at'     => in_array($status, ['approved', 'rejected'], true) ? now()->subDay() : null,
        ]);
        $approval->setRelation('user', $this->user($name));

        return $approval;
    }

    private function requirements(int $days): \Illuminate\Support\Collection
    {
        return collect([
            ['Tractocamión T-120', 'Verificación de emisiones', $days],
            ['Planta Norte', 'Licencia de funcionamiento', $days],
            ['Semirremolque S-045', 'Inspección físico-mecánica', $days + 1],
        ])->map(function ($row) {
            [$asset, $template, $inDays] = $row;
            $requirement = (new AssetRequirement)->forceFill([
                'type'     => $template,
                'due_date' => now()->addDays($inDays),
                'status'   => RequirementStatus::PENDING,
            ]);
            $requirement->setRelation('asset', (new Asset)->forceFill(['name' => $asset]));
            $requirement->setRelation('template', (new RequirementTemplate)->forceFill(['name' => $template]));

            return $requirement;
        });
    }

    private function license(int $days): License
    {
        $license = (new License)->forceFill([
            'licensable_type'   => Company::class,
            'expires_at'        => now()->addDays($days),
            'price'             => 12500,
            'includes_procesos' => true,
        ]);
        $license->setRelation('licensable', $this->company());

        return $license;
    }
}
