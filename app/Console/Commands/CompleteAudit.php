<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Services\AuditFindingService;
use Illuminate\Console\Command;

/**
 * Registra la calificación de una auditoría como lo hará el auditor (su pantalla viene después).
 * La auditoría pasa a Historial con su calificación y se avisa a los auditados.
 *
 *   php artisan audits:complete 2 "85 / 100"
 */
class CompleteAudit extends Command
{
    protected $signature = 'audits:complete {audit : id de la auditoría} {calificacion : p. ej. "85 / 100"}';

    protected $description = 'Califica una auditoría y avisa a los auditados (simula al auditor)';

    public function handle(AuditFindingService $service): int
    {
        $audit = Audit::with('regulation')->find($this->argument('audit'));
        if (! $audit) {
            $this->error('No existe esa auditoría.');

            return self::FAILURE;
        }
        if ($audit->status !== Audit::STATUS_PLANNED) {
            $this->error("La auditoría ya está {$audit->status}.");

            return self::FAILURE;
        }

        $service->complete($audit, $this->argument('calificacion'));

        $this->info(sprintf('%s calificada con %s.', $audit->regulation?->code ?? "Auditoría #{$audit->id}", $audit->result));

        return self::SUCCESS;
    }
}
