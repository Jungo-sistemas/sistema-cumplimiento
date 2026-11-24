<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al responsable: el auditor revisó su causa raíz (aprobada → empieza el seguimiento; devuelta con
 * observaciones) o su evidencia (aceptada → hallazgo cerrado; rechazada → subir otra).
 */
class AuditFindingReviewedNotification extends Notification implements ShouldQueue
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

    private function accepted(): bool
    {
        return $this->submission->status === AuditFindingSubmission::STATUS_ACCEPTED;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $finding = $this->finding;
        $regulation = $finding->audit->regulation;
        $rootCause = $this->submission->isRootCause();

        [$subject, $headline, $intro, $button] = match (true) {
            $rootCause && $this->accepted() => [
                'Causa raíz aprobada: ', 'Causa raíz aprobada — inicia el seguimiento',
                'El auditor aprobó tu causa raíz y las acciones. Ejecútalas y sube la evidencia de que se aplicaron.',
                'Subir evidencia',
            ],
            $rootCause => [
                'Causa raíz devuelta: ', 'Causa raíz devuelta con observaciones',
                'El auditor tiene observaciones sobre tu causa raíz o las acciones. Revísalas y vuelve a enviarlas.',
                'Corregir causa raíz',
            ],
            $this->accepted() => [
                'Hallazgo cerrado: ', 'Evidencia verificada — hallazgo cerrado',
                'El auditor confirmó que la acción se implementó y el hallazgo quedó cerrado.',
                'Ver hallazgo',
            ],
            default => [
                'Evidencia rechazada: ', 'Evidencia rechazada',
                'El auditor rechazó tu evidencia. Revisa el motivo y sube una nueva.',
                'Subir nueva evidencia',
            ],
        };

        return (new MailMessage)
            ->subject($subject . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable'  => $notifiable,
                'headline'    => $headline,
                'intro'       => $intro,
                'rows'        => [
                    'Proceso' => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'   => $finding->point,
                    'Siguiente plazo' => $finding->status !== AuditFinding::STATUS_CLOSED ? $finding->due_at?->format('d/m/Y') : null,
                    'Auditor' => $finding->audit->auditor?->name,
                ],
                'note'        => $this->submission->review_comment
                    ? ($this->accepted() ? 'Comentario del auditor: ' : 'Observaciones del auditor: ') . $this->submission->review_comment
                    : null,
                'noteTone'    => $this->accepted() ? 'green' : 'red',
                'buttonUrl'   => route('mi-espacio.findings.show', $finding),
                'buttonLabel' => $button,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'audit_finding_reviewed',
            'kind'       => $this->submission->kind,
            'finding_id' => $this->finding->id,
            'accepted'   => $this->accepted(),
            'reason'     => $this->submission->review_comment,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
