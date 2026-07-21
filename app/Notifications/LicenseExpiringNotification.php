<?php

namespace App\Notifications;

use App\Models\License;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class LicenseExpiringNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly License $license,
        public readonly int $daysUntilExpiration,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->license->licensable->name ?? 'Cliente';

        $subject = $this->daysUntilExpiration <= 1
            ? "¡Vence mañana! Licencia de {$name}"
            : "Licencia de {$name} vence en {$this->daysUntilExpiration} días";

        return (new MailMessage)
            ->subject($subject)
            ->view('emails.notifications.license-expiring', [
                'license'              => $this->license,
                'daysUntilExpiration'  => $this->daysUntilExpiration,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'license_id'            => $this->license->id,
            'licensable_type'       => $this->license->licensable_type,
            'licensable_id'         => $this->license->licensable_id,
            'days_until_expiration' => $this->daysUntilExpiration,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
