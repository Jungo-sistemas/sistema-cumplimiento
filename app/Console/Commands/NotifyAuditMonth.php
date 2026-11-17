<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Notifications\AuditMonthStartedNotification;
use Illuminate\Console\Command;

/**
 * Aviso "este mes te toca una auditoría". Corre a diario (no solo el día 1) para que también
 * avise de auditorías que se programaron ya empezado el mes; cada auditoría se avisa una sola vez.
 */
class NotifyAuditMonth extends Command
{
    protected $signature = 'audits:notify-month {--dry-run : Solo mostrar a quién se avisaría}';

    protected $description = 'Avisa a los auditados las auditorías programadas cuyo mes ya empezó (sin decir el día)';

    public function handle(): int
    {
        $audits = Audit::query()
            ->where('status', Audit::STATUS_PLANNED)
            ->monthStarted()
            ->whereNull('month_notified_at')
            ->with(['regulation', 'company', 'auditor', 'auditees' => fn ($q) => $q->where('status', 'active')])
            ->get();

        $sent = 0;
        foreach ($audits as $audit) {
            foreach ($audit->auditees as $auditee) {
                $this->line(sprintf('  %s — %s (%s)', $audit->regulation?->code ?? "#{$audit->regulation_id}", $auditee->name, $audit->plannedMonthLabel()));
                if (! $this->option('dry-run')) {
                    $auditee->notify(new AuditMonthStartedNotification($audit));
                    $sent++;
                }
            }

            if (! $this->option('dry-run')) {
                $audit->update(['month_notified_at' => now()]);
            }
        }

        $this->info($this->option('dry-run')
            ? "{$audits->count()} auditoría(s) por avisar (simulación, no se envió nada)."
            : "{$audits->count()} auditoría(s) avisadas, {$sent} aviso(s) enviados.");

        return self::SUCCESS;
    }
}
