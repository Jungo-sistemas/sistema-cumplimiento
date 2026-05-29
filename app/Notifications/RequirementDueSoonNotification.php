<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Collection;

class RequirementDueSoonNotification extends Notification
{
    use Queueable;

    /**
     * @param Collection $requirements  AssetRequirement items grouped for this user
     * @param int        $daysUntilDue  The threshold that triggered this batch (1, 7, 30 or 60)
     */
    public function __construct(
        public readonly Collection $requirements,
        public readonly int $daysUntilDue,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = match (true) {
            $this->daysUntilDue <= 1  => '¡Alerta! Requisitos que vencen MAÑANA',
            $this->daysUntilDue <= 7  => "Requisitos que vencen en {$this->daysUntilDue} días",
            $this->daysUntilDue <= 30 => "Aviso: requisitos próximos a vencer (30 días)",
            default                   => "Recordatorio: requisitos que vencen en 60 días",
        };

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.notifications.requirement-due-soon', [
                'notifiable'   => $notifiable,
                'requirements' => $this->requirements,
                'daysUntilDue' => $this->daysUntilDue,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'days_until_due'    => $this->daysUntilDue,
            'requirement_count' => $this->requirements->count(),
            'requirement_ids'   => $this->requirements->pluck('id')->all(),
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}