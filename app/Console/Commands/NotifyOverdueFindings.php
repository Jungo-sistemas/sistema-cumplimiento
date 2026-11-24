<?php

namespace App\Console\Commands;

use App\Services\AuditFindingService;
use Illuminate\Console\Command;

/**
 * Hallazgos con el plazo de su etapa vencido: avisa (una vez por etapa) a quien le toca actuar y a
 * quienes tienen "Avisos de auditorías". El escalamiento a jefe directo, director y Consejo se
 * define después.
 */
class NotifyOverdueFindings extends Command
{
    protected $signature = 'audits:findings-overdue {--dry-run : Solo contar los que se avisarían}';

    protected $description = 'Avisa los hallazgos de auditoría con el plazo de su etapa vencido';

    public function handle(AuditFindingService $service): int
    {
        $count = $service->notifyOverdue($this->option('dry-run'));

        $this->info($this->option('dry-run')
            ? "{$count} hallazgo(s) con plazo vencido por avisar (simulación, no se envió nada)."
            : "{$count} hallazgo(s) con plazo vencido avisados.");

        return self::SUCCESS;
    }
}
