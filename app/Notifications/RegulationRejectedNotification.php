<?php

namespace App\Notifications;

use App\Models\Regulation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RegulationRejectedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Regulation $regulation,
        public readonly string $comments,
        public readonly ?User $rejectedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Documento rechazado: ' . $this->regulation->name)
            ->view('emails.processes.regulation-rejected', [
                'notifiable' => $notifiable,
                'regulation' => $this->regulation,
                'comments'   => $this->comments,
                'rejectedBy' => $this->rejectedBy,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'            => 'regulation_rejected',
            'regulation_id'   => $this->regulation->id,
            'regulation_name' => $this->regulation->name,
            'comments'        => $this->comments,
            'rejected_by'     => $this->rejectedBy?->name,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
