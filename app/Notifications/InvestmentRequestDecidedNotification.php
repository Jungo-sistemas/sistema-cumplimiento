<?php

namespace App\Notifications;

use App\Models\InvestmentRequest;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Resultado final del flujo para quien capturó la solicitud y el solicitante:
 * $outcome = 'approved' (aprobaron todos) o 'rejected' (alguien la rechazó, con su motivo).
 */
class InvestmentRequestDecidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly InvestmentRequest $investmentRequest,
        public readonly string $outcome,
        public readonly ?string $comments = null,
        public readonly ?User $rejectedBy = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approved = $this->outcome === 'approved';

        return (new MailMessage)
            ->subject(($approved ? 'Solicitud de inversión aprobada' : 'Solicitud de inversión rechazada') . ' #' . $this->investmentRequest->id)
            ->view('emails.investments.request-decided', [
                'notifiable'        => $notifiable,
                'investmentRequest' => $this->investmentRequest->loadMissing(['company', 'requester']),
                'approved'          => $approved,
                'comments'          => $this->comments,
                'rejectedBy'        => $this->rejectedBy,
                'approvals'         => $this->investmentRequest->approvals()->with('user')->get(),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'                  => 'investment_request_' . $this->outcome,
            'investment_request_id' => $this->investmentRequest->id,
            'concept'               => $this->investmentRequest->concept,
            'comments'              => $this->comments,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
