<?php

namespace App\Notifications;

use App\Models\Audit;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** El auditor registró (o cambió) el día y la hora acordados de la auditoría. */
class AuditScheduledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Audit $audit,
        public readonly bool $rescheduled = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $regulation = $this->audit->regulation;
        $name = trim(($regulation->code ? $regulation->code . ' ' : '') . $regulation->name);

        return (new MailMessage)
            ->subject(($this->rescheduled ? 'Cambió la fecha de tu auditoría: ' : 'Fecha de tu auditoría: ') . $name)
            ->view('emails.audits.scheduled', [
                'notifiable'  => $notifiable,
                'audit'       => $this->audit,
                'rescheduled' => $this->rescheduled,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'audit_scheduled',
            'audit_id'        => $this->audit->id,
            'regulation_id'   => $this->audit->regulation_id,
            'regulation_name' => $this->audit->regulation?->name,
            'scheduled_at'    => $this->audit->scheduled_at?->toIso8601String(),
            'rescheduled'     => $this->rescheduled,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
