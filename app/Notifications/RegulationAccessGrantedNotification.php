<?php

namespace App\Notifications;

use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Un admin agregó al usuario como responsable de un reglamento (le dio acceso para editarlo) —
 * se le avisa para que sepa que ya puede entrar a trabajarlo, sin tener que preguntar.
 */
class RegulationAccessGrantedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Regulation $regulation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Se te dio acceso al reglamento: ' . $this->regulation->name)
            ->view('emails.processes.regulation-access-granted', [
                'notifiable' => $notifiable,
                'regulation' => $this->regulation,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'regulation_access_granted',
            'regulation_id'   => $this->regulation->id,
            'regulation_name' => $this->regulation->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
