<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al auditor: el responsable envió la causa raíz y las acciones, o la evidencia del seguimiento.
 * Sin botón todavía: la pantalla del auditor para revisarlas viene después.
 */
class AuditFindingSubmittedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AuditFinding $finding,
        public readonly AuditFindingSubmission $submission,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $finding = $this->finding;
        $regulation = $finding->audit->regulation;
        $rootCause = $this->submission->isRootCause();
        $who = $this->submission->user?->name ?? 'El responsable';

        return (new MailMessage)
            ->subject(($rootCause ? 'Causa raíz por validar: ' : 'Evidencia por verificar: ') . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable' => $notifiable,
                'headline'   => $rootCause ? 'Causa raíz por validar' : 'Evidencia por verificar',
                'intro'      => $rootCause
                    ? "{$who} envió la causa raíz y las acciones de un hallazgo. Dale el visto bueno o devuélvelas con observaciones."
                    : "{$who} subió la evidencia del seguimiento de un hallazgo. Confirma que la acción se implementó.",
                'rows'       => [
                    'Proceso'   => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'     => $finding->point,
                    'Severidad' => $finding->severityLabel(),
                    'Causa raíz' => $rootCause ? $this->submission->root_cause : null,
                    'Acciones'  => $rootCause ? $this->submission->actions : null,
                    'Archivos'  => $rootCause ? null : (string) $this->submission->files()->count(),
                    'Revisar a más tardar' => $finding->due_at?->format('d/m/Y'),
                ],
                'note'       => $this->submission->comment ? 'Comentario: ' . $this->submission->comment : null,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'audit_finding_submitted',
            'kind'          => $this->submission->kind,
            'finding_id'    => $this->finding->id,
            'submission_id' => $this->submission->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
