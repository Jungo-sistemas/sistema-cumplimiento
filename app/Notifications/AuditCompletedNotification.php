<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** A los auditados: el auditor calificó la auditoría. */
class AuditCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Audit $audit) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $regulation = $this->audit->regulation;
        $findings = $this->audit->findings()->count();

        return (new MailMessage)
            ->subject('Resultado de tu auditoría: ' . trim(($regulation?->code ? $regulation->code . ' ' : '') . $regulation?->name))
            ->view('emails.audits.notice', [
                'notifiable'  => $notifiable,
                'headline'    => 'Resultado de tu auditoría',
                'intro'       => 'El auditor registró la calificación de tu auditoría.',
                'rows'        => [
                    'Proceso'      => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Fecha'        => $this->audit->scheduledLabel(),
                    'Calificación' => $this->audit->result,
                    'Hallazgos'    => (string) $findings,
                    'Auditor'      => $this->audit->auditor?->name,
                ],
                'buttonUrl'   => route('mi-espacio.audits', ['tab' => 'historial']),
                'buttonLabel' => 'Ver mis auditorías',
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'     => 'audit_completed',
            'audit_id' => $this->audit->id,
            'result'   => $this->audit->result,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
