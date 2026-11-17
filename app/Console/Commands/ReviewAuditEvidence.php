<?php

namespace App\Console\Commands;

use App\Models\AuditFinding;
use App\Services\AuditFindingService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Acepta o rechaza la evidencia de un hallazgo como lo hará el auditor (su pantalla viene después).
 *
 *   php artisan audits:review-evidence 5 rechazar --motivo="La foto no muestra la fecha de la bitácora"
 *   php artisan audits:review-evidence 5 aceptar
 */
class ReviewAuditEvidence extends Command
{
    protected $signature = 'audits:review-evidence {hallazgo : id del hallazgo} {decision : aceptar o rechazar} {--motivo= : obligatorio al rechazar}';

    protected $description = 'Acepta (cierra) o rechaza con motivo la evidencia de un hallazgo (simula al auditor)';

    public function handle(AuditFindingService $service): int
    {
        $finding = AuditFinding::find($this->argument('hallazgo'));
        $decision = $this->argument('decision');

        if (! $finding || ! in_array($decision, ['aceptar', 'rechazar'], true)) {
            $this->error(! $finding ? 'No existe ese hallazgo.' : 'La decisión debe ser "aceptar" o "rechazar".');

            return self::FAILURE;
        }

        try {
            $service->review($finding, $decision === 'aceptar', $this->option('motivo'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info($decision === 'aceptar'
            ? "Evidencia aceptada: hallazgo #{$finding->id} cerrado."
            : "Evidencia rechazada: el responsable verá el motivo y podrá subir otra.");

        return self::SUCCESS;
    }
}
