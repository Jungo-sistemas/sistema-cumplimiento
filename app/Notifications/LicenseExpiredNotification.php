<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LicenseExpiredNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly License $license,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->license->licensable->name ?? 'Cliente';

        return (new MailMessage)
            ->subject("Acceso suspendido: venció la licencia de {$name}")
            ->view('emails.notifications.license-expired', [
                'license' => $this->license,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'license_id'      => $this->license->id,
            'licensable_type' => $this->license->licensable_type,
            'licensable_id'   => $this->license->licensable_id,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
