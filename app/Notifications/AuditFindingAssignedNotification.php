<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Al responsable: el auditor le registró un hallazgo y tiene que subir evidencia. */
class AuditFindingAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly AuditFinding $finding) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $finding = $this->finding;
        $regulation = $finding->audit->regulation;

        return (new MailMessage)
            ->subject('Nuevo hallazgo de auditoría: ' . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable'  => $notifiable,
                'headline'    => 'Nuevo hallazgo de auditoría',
                'intro'       => 'El auditor registró un hallazgo en la auditoría y te eligió como responsable de mostrar la evidencia.',
                'rows'        => [
                    'Proceso'       => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'         => $finding->point,
                    'Hallazgo'      => $finding->description,
                    'Severidad'     => $finding->severityLabel(),
                    'Fecha límite'  => $finding->due_at?->format('d/m/Y'),
                    'Auditor'       => $finding->audit->auditor?->name,
                ],
                'buttonUrl'   => route('mi-espacio.findings.show', $finding),
                'buttonLabel' => 'Subir evidencia',
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'audit_finding_assigned',
            'finding_id' => $this->finding->id,
            'audit_id'   => $this->finding->audit_id,
            'point'      => $this->finding->point,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
