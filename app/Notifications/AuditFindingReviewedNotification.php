<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al responsable: el auditor aceptó su evidencia (hallazgo cerrado) o la rechazó con motivo. */
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

        return (new MailMessage)
            ->subject(($this->accepted() ? 'Evidencia aceptada: ' : 'Evidencia rechazada: ') . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable'  => $notifiable,
                'headline'    => $this->accepted() ? 'Evidencia aceptada — hallazgo cerrado' : 'Evidencia rechazada',
                'intro'       => $this->accepted()
                    ? 'El auditor aceptó tu evidencia y el hallazgo quedó cerrado.'
                    : 'El auditor rechazó tu evidencia. Revisa el motivo y sube una nueva.',
                'rows'        => [
                    'Proceso' => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'   => $finding->point,
                    'Auditor' => $finding->audit->auditor?->name,
                ],
                'note'        => $this->submission->review_comment
                    ? ($this->accepted() ? 'Comentario del auditor: ' : 'Motivo del rechazo: ') . $this->submission->review_comment
                    : null,
                'noteTone'    => $this->accepted() ? 'green' : 'red',
                'buttonUrl'   => route('mi-espacio.findings.show', $finding),
                'buttonLabel' => $this->accepted() ? 'Ver hallazgo' : 'Subir nueva evidencia',
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'audit_finding_reviewed',
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
