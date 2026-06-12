<?php

namespace App\Notifications;

use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification
{
    use Queueable;

    public function __construct(public readonly Regulation $regulation) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Aprobación requerida: ' . $this->regulation->name)
            ->view('emails.processes.approval-requested', [
                'notifiable' => $notifiable,
                'regulation' => $this->regulation,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'approval_requested',
            'regulation_id'   => $this->regulation->id,
            'regulation_name' => $this->regulation->name,
            'impact_level'    => $this->regulation->impact_level,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
