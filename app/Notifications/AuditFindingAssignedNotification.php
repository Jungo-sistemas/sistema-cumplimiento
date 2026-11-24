<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * El auditor registró un hallazgo. Al responsable (le toca la causa raíz y el plan de acciones) y,
 * con $asProcessOwner, a los responsables del procedimiento, que lo acompañan en el análisis.
 */
class AuditFindingAssignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly AuditFinding $finding,
        public readonly bool $asProcessOwner = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $finding = $this->finding;
        $regulation = $finding->audit->regulation;
        $responsible = $finding->responsible?->name ?? 'el responsable';

        $intro = $this->asProcessOwner
            ? "El auditor registró un hallazgo en un procedimiento del que eres responsable. {$responsible} tiene que definir la causa raíz y las acciones; acompáñalo en el análisis."
            : 'El auditor registró un hallazgo en la auditoría y te eligió como responsable. Define la causa raíz y las acciones para corregirlo y envíalas al auditor.';

        $note = $finding->isCritical()
            ? 'Hallazgo crítico: aplica una contención inmediata (en las primeras ' . config('auditorias.contencion_critico_horas', 24) . ' horas) mientras se define la causa raíz.'
            : null;

        return (new MailMessage)
            ->subject(($finding->isCritical() ? 'Hallazgo crítico de auditoría: ' : 'Nuevo hallazgo de auditoría: ') . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable'  => $notifiable,
                'headline'    => $finding->isCritical() ? 'Hallazgo crítico de auditoría' : 'Nuevo hallazgo de auditoría',
                'intro'       => $intro,
                'rows'        => [
                    'Proceso'     => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Punto'       => $finding->point,
                    'Hallazgo'    => $finding->description,
                    'Severidad'   => $finding->severityLabel(),
                    'Responsable' => $this->asProcessOwner ? $finding->responsible?->name : null,
                    'Causa raíz a más tardar' => $finding->due_at?->format('d/m/Y'),
                    'Auditor'     => $finding->audit->auditor?->name,
                ],
                'note'        => $note,
                'noteTone'    => 'red',
                'buttonUrl'   => route('mi-espacio.findings.show', $finding),
                'buttonLabel' => $this->asProcessOwner ? 'Ver hallazgo' : 'Escribir causa raíz',
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'          => 'audit_finding_assigned',
            'finding_id'    => $this->finding->id,
            'audit_id'      => $this->finding->audit_id,
            'point'         => $this->finding->point,
            'process_owner' => $this->asProcessOwner,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
