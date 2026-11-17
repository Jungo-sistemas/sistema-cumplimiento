<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * "Este mes te toca una auditoría": se manda al empezar el mes programado. Nunca lleva el día —
 * ese lo acuerdan auditor y auditado por fuera.
 */
class AuditMonthStartedNotification extends Notification implements ShouldQueue
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
            ->subject('Este mes te toca una auditoría: ' . trim(($regulation->code ? $regulation->code . ' ' : '') . $regulation->name))
            ->view('emails.audits.month-started', [
                'notifiable' => $notifiable,
                'audit'      => $this->audit,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'audit_month_started',
            'audit_id'        => $this->audit->id,
            'regulation_id'   => $this->audit->regulation_id,
            'regulation_name' => $this->audit->regulation?->name,
            'month'           => $this->audit->plannedMonthLabel(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
