<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Al auditor: el responsable subió evidencia de un hallazgo. Sin botón todavía — la pantalla del
 * auditor para revisarla viene después.
 */
class AuditFindingEvidenceSubmittedNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject('Evidencia por revisar: ' . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable' => $notifiable,
                'headline'   => 'Evidencia por revisar',
                'intro'      => ($this->submission->user?->name ?? 'El responsable') . ' subió evidencia de un hallazgo.',
                'rows'       => [
                    'Proceso'  => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'    => $finding->point,
                    'Archivos' => (string) $this->submission->files()->count(),
                ],
                'note'       => $this->submission->comment ? 'Comentario: ' . $this->submission->comment : null,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'audit_finding_evidence_submitted',
            'finding_id'    => $this->finding->id,
            'submission_id' => $this->submission->id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
