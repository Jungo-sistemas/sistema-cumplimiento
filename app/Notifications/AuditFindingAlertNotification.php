<?php

namespace App\Notifications;

use App\Models\AuditFinding;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Aviso de auditorías: un hallazgo crítico nuevo ($reason = critico) o el plazo de una etapa
 * vencido ($reason = vencido). Les llega a quienes tienen "Avisos de auditorías" en Usuarios
 * y, si se venció, también a quien le tocaba actuar.
 */
class AuditFindingAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const REASON_CRITICAL = 'critico';
    public const REASON_OVERDUE  = 'vencido';

    public function __construct(
        public readonly AuditFinding $finding,
        public readonly string $reason,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $finding = $this->finding;
        $regulation = $finding->audit->regulation;
        $overdue = $this->reason === self::REASON_OVERDUE;
        $waitingOn = in_array($finding->status, AuditFinding::NEEDS_AUDITOR, true)
            ? ($finding->audit->auditor?->name ?? 'el auditor') . ' (auditor)'
            : ($finding->responsible?->name ?? 'el responsable') . ' (auditado)';

        return (new MailMessage)
            ->subject(($overdue ? 'Plazo vencido en hallazgo: ' : 'Hallazgo crítico registrado: ') . $finding->point)
            ->view('emails.audits.notice', [
                'notifiable' => $notifiable,
                'headline'   => $overdue ? 'Plazo vencido en un hallazgo' : 'Hallazgo crítico registrado',
                'intro'      => $overdue
                    ? 'Se venció el plazo de la etapa «' . $finding->stageLabel() . "» de un hallazgo. Le toca a {$waitingOn}."
                    : 'El auditor registró un hallazgo crítico: una omisión que pone en riesgo la operación o a una persona.',
                'rows'       => [
                    'Proceso'     => trim(($regulation?->code ? $regulation->code . ' — ' : '') . $regulation?->name),
                    'Empresa'     => $finding->audit->company?->procesos_name ?? $finding->audit->company?->name,
                    'Punto'       => $finding->point,
                    'Hallazgo'    => $finding->description,
                    'Severidad'   => $finding->severityLabel(),
                    'Responsable' => $finding->responsible?->name,
                    'Auditor'     => $finding->audit->auditor?->name,
                    'Plazo'       => $finding->due_at?->format('d/m/Y'),
                ],
                'buttonUrl'   => route('mi-espacio.findings.show', $finding),
                'buttonLabel' => 'Ver hallazgo',
            ]);
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'       => 'audit_finding_alert',
            'reason'     => $this->reason,
            'finding_id' => $this->finding->id,
            'point'      => $this->finding->point,
        ];
    }

    public function toArray(object $notifiable): array
    {
        return $this->toDatabase($notifiable);
    }
}
