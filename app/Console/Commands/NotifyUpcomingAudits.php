<?php

namespace App\Console\Commands;

use App\Models\Audit;
use Illuminate\Console\Command;

/**
 * Aviso "tienes una auditoría": a los auditados de las auditorías cuya fecha (la fija el auditor)
 * cae dentro de los próximos días de aviso (config auditorias.aviso_dias_antes). Corre a diario;
 * cada auditoría se avisa una sola vez. Las que el auditor agenda ya dentro del plazo se avisan en
 * ese momento (Audit::schedule()).
 */
class NotifyUpcomingAudits extends Command
{
    protected $signature = 'audits:notify-upcoming {--dry-run : Solo mostrar a quién se avisaría}';

    protected $description = 'Avisa a los auditados las auditorías cuya fecha está a los días de aviso o menos';

    public function handle(): int
    {
        $audits = Audit::query()
            ->noticeDue()
            ->with(['regulation', 'company', 'auditor', 'auditees' => fn ($q) => $q->where('status', 'active')])
            ->get();

        $sent = 0;
        foreach ($audits as $audit) {
            foreach ($audit->auditees as $auditee) {
                $this->line(sprintf('  %s — %s (%s)', $audit->regulation?->code ?? "#{$audit->regulation_id}", $auditee->name, $audit->scheduledLabel()));
            }

            if (! $this->option('dry-run')) {
                $sent += $audit->sendNotice();
            }
        }

        $this->info($this->option('dry-run')
            ? "{$audits->count()} auditoría(s) por avisar (simulación, no se envió nada)."
            : "{$audits->count()} auditoría(s) avisadas, {$sent} aviso(s) enviados.");

        return self::SUCCESS;
    }
}
