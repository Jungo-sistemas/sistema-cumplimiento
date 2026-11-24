<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Tienes una auditoría": le llega al auditado unos días antes de la fecha que fijó el auditor
 * (config auditorias.aviso_dias_antes). Es el primer aviso que recibe; antes no la ve.
 */
class AuditUpcomingNotification extends Notification implements ShouldQueue
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

        return (new MailMessage)
            ->subject('Tienes una auditoría el ' . $this->audit->scheduledLabel() . ': ' . trim(($regulation->code ? $regulation->code . ' ' : '') . $regulation->name))
            ->view('emails.audits.upcoming', [
                'notifiable' => $notifiable,
                'audit'      => $this->audit,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'audit_upcoming',
            'audit_id'        => $this->audit->id,
            'regulation_id'   => $this->audit->regulation_id,
            'regulation_name' => $this->audit->regulation?->name,
            'scheduled_at'    => $this->audit->scheduled_at?->toIso8601String(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
