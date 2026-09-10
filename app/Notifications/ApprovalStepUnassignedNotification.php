<?php

namespace App\Notifications;

use App\Models\Regulation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * ApprovalFlowService::createStepRecords() acaba de resolver un paso del flujo a CERO usuarios
 * (ningún puesto involucrado tiene a nadie asignado, o el admin asignó usuarios específicos en
 * flow_user_map que ya no existen/no están activos) — sin este aviso, el reglamento queda en
 * "pending_authorization" para siempre, sin ningún registro de aprobación pendiente y sin que
 * nadie se entere: no hay a quién recordarle nada (ApprovalReminderNotification tampoco actúa,
 * porque no hay ninguna fila "pending" sobre la cual correr) — el documento queda huérfano en
 * silencio. Se avisa a los mismos admins que pueden corregirlo (asignar el puesto en el flujo).
 */
class ApprovalStepUnassignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Regulation $regulation,
        public readonly int $step,
        /** @var array<int, string> */
        public readonly array $missingPositions,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Flujo de aprobación detenido — puesto sin asignar: ' . $this->regulation->name)
            ->view('emails.processes.approval-step-unassigned', [
                'notifiable'        => $notifiable,
                'regulation'        => $this->regulation,
                'step'              => $this->step,
                'missingPositions'  => $this->missingPositions,
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'              => 'approval_step_unassigned',
            'regulation_id'     => $this->regulation->id,
            'regulation_name'   => $this->regulation->name,
            'step'              => $this->step,
            'missing_positions' => $this->missingPositions,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
