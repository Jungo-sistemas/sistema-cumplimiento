<?php

namespace App\Notifications;

use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalRequestedNotification extends Notification implements ShouldQueue
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
                'notifiable'         => $notifiable,
                'regulation'         => $this->regulation,
                'elaboradoPor'       => $this->regulation->details['quien_elabora'] ?? $this->regulation->creator?->name,
                // Quién ya aprobó en pasos anteriores (o antes en la misma fila, si el paso es
                // secuencial) — para que el aprobador vea por quiénes ya pasó el documento antes
                // de llegar a él, sin tener que entrar al flujo a revisarlo.
                'previousApprovers'  => $this->regulation->approvals()
                    ->where('status', 'approved')
                    ->with(['user', 'jobPosition'])
                    ->orderBy('step_number')
                    ->orderBy('id')
                    ->get(),
                // Qué cambió en la versión (descripción, justificación, síntesis, antes/después)
                // solo se ve dentro de la plataforma ("Mis aprobaciones" / flujo), nunca en el
                // correo.
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
