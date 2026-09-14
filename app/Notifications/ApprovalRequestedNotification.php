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
        $currentVersion  = $this->regulation->currentVersion;
        $previousVersion = $currentVersion?->previousVersion();

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
                // Qué cambió en esta versión respecto a la anterior — solo el resumen corto que
                // ya se captura al editar (change_description/change_justification). La tabla de
                // antes/después sección por sección (RegulationChangeDiffService) se decidió que
                // solo se vea dentro de la plataforma ("Mis aprobaciones"), nunca en el correo —
                // puede salir muy larga y no es cómodo de leer en un cliente de correo.
                'currentVersion'     => $currentVersion,
                'previousVersion'    => $previousVersion,
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
