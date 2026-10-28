<?php

namespace App\Notifications;

use App\Models\InvestmentRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class InvestmentApprovalRequestedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly InvestmentRequest $investmentRequest) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $approvals = $this->investmentRequest->approvals()->with('user')->get();
        $mine      = $approvals->firstWhere('user_id', $notifiable->id);

        return (new MailMessage)
            ->subject('Aprobación requerida: solicitud de inversión #' . $this->investmentRequest->id)
            ->view('emails.investments.approval-requested', [
                'notifiable'        => $notifiable,
                'investmentRequest' => $this->investmentRequest->loadMissing(['company', 'requester']),
                'position'          => $mine?->sequence_order,
                'total'             => $approvals->count(),
                // Quiénes ya aprobaron antes de llegar a esta persona.
                'previousApprovers' => $approvals->where('status', 'approved'),
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'                  => 'investment_approval_requested',
            'investment_request_id' => $this->investmentRequest->id,
            'concept'               => $this->investmentRequest->concept,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
