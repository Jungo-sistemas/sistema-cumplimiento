<?php

namespace App\Console\Commands;

use App\Models\RegulationApproval;
use App\Models\User;
use App\Notifications\ApprovalOverdueEscalationNotification;
use App\Notifications\ApprovalReminderNotification;
use Illuminate\Console\Command;

class NotifyPendingApprovalReminders extends Command
{
    protected $signature = 'approvals:notify-reminders
                            {--dry-run : Muestra qué pasaría sin enviar correos}';

    protected $description = 'Manda recordatorios (cada 7 días, hasta 3) a aprobadores con una aprobación pendiente sin resolver, y escala a los admins de Procesos al llegar al tope';

    private const REMINDER_INTERVAL_DAYS = 7;
    private const MAX_REMINDERS = 3;

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');

        $pending = RegulationApproval::where('status', 'pending')
            ->where('reminders_sent', '<', self::MAX_REMINDERS)
            ->whereNotNull('user_id')
            ->with(['user', 'regulation.company'])
            ->get();

        $sent = 0;

        foreach ($pending as $approval) {
            if (! $approval->user || ! $approval->regulation) {
                continue;
            }

            // updated_at es el momento en que esta fila realmente quedó en "pending" — coincide con
            // created_at para quienes nacieron pending directo, y se actualiza al promoverse desde
            // "waiting" (ApprovalFlowService::promoteNextWaitingApprover). created_at NO sirve para
            // estos últimos: no refleja cuándo les tocó actuar, solo cuándo se creó el registro.
            $daysOverdue = (int) $approval->updated_at->diffInDays(now());

            $nextReminderNumber = $approval->reminders_sent + 1;
            $dueInDays = $nextReminderNumber * self::REMINDER_INTERVAL_DAYS;

            if ($daysOverdue < $dueInDays) {
                continue;
            }

            $regulation = $approval->regulation;

            if ($dryRun) {
                $this->line("  [dry-run] recordatorio #{$nextReminderNumber} → {$approval->user->email} ({$regulation->name}, {$daysOverdue}d)");
                $sent++;
                continue;
            }

            $approval->user->notify(new ApprovalReminderNotification($regulation, $daysOverdue, $nextReminderNumber));

            // Timestamps apagados a propósito: un update() normal movería "updated_at" y arruinaría
            // la referencia de "cuándo quedó pending" que usamos arriba para calcular daysOverdue.
            $approval->timestamps = false;
            $approval->update([
                'reminders_sent'        => $nextReminderNumber,
                'last_reminder_sent_at' => now(),
            ]);

            if ($nextReminderNumber >= self::MAX_REMINDERS) {
                $this->escalateToProcessAdmins($approval, $regulation, $daysOverdue);
            }

            $sent++;
        }

        if ($dryRun) {
            $this->line("[dry-run] Se habría(n) enviado {$sent} recordatorio(s)");
        } else {
            $this->info("Recordatorios enviados: {$sent}");
        }

        return self::SUCCESS;
    }

    /**
     * Mismo filtro que ApprovalFlowService::notifyIfCorrectedAfterRejection(): solo admins con
     * acceso al módulo de Procesos y a la empresa del reglamento — no todos los admins del portal.
     */
    private function escalateToProcessAdmins(RegulationApproval $approval, $regulation, int $daysOverdue): void
    {
        $admins = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->get()
            ->filter(fn (User $u) => $u->canAccessCompany($regulation->company))
            ->filter(fn (User $u) => $u->canAccessModule('procesos'))
            ->filter(fn (User $u) => $u->wantsNotificationsFor('procesos'));

        foreach ($admins as $admin) {
            $admin->notify(new ApprovalOverdueEscalationNotification($regulation, $approval->user, $daysOverdue));
        }
    }
}
