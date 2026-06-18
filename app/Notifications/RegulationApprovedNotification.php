<?php

namespace App\Notifications;

use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegulationApprovedNotification extends Notification
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
            ->subject('Documento aprobado: ' . $this->regulation->name)
            ->view('emails.processes.regulation-approved', [
                'notifiable' => $notifiable,
                'regulation' => $this->regulation,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'regulation_approved',
            'regulation_id'   => $this->regulation->id,
            'regulation_name' => $this->regulation->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
