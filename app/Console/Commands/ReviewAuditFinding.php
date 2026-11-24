<?php

namespace App\Console\Commands;

use App\Models\AuditFinding;
use App\Services\AuditFindingService;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Revisa lo que el responsable envió, como lo hará el auditor (su pantalla viene después): la causa
 * raíz (aprobar = inicia el seguimiento; rechazar = la devuelve con observaciones) o la evidencia
 * (aprobar = cierra el hallazgo; rechazar = pide otra).
 *
 *   php artisan audits:review-finding 5 aprobar
 *   php artisan audits:review-finding 5 rechazar --motivo="La causa describe el problema, no por qué pasó"
 */
class ReviewAuditFinding extends Command
{
    protected $signature = 'audits:review-finding {hallazgo : id del hallazgo} {decision : aprobar o rechazar} {--motivo= : obligatorio al rechazar}';

    protected $description = 'Aprueba o devuelve la causa raíz, o verifica / rechaza la evidencia de un hallazgo (simula al auditor)';

    public function handle(AuditFindingService $service): int
    {
        $finding = AuditFinding::find($this->argument('hallazgo'));
        $decision = $this->argument('decision');

        if (! $finding || ! in_array($decision, ['aprobar', 'rechazar'], true)) {
            $this->error(! $finding ? 'No existe ese hallazgo.' : 'La decisión debe ser "aprobar" o "rechazar".');

            return self::FAILURE;
        }

        $wasRootCause = $finding->status === AuditFinding::STATUS_ROOT_CAUSE_REVIEW;

        try {
            $service->review($finding, $decision === 'aprobar', $this->option('motivo'));
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $finding->refresh();
        $this->info(match (true) {
            $wasRootCause && $decision === 'aprobar' => "Causa raíz aprobada: inicia el seguimiento. Evidencia a más tardar el {$finding->due_at->format('d/m/Y')}.",
            $wasRootCause                            => 'Causa raíz devuelta: el responsable verá las observaciones y la corregirá.',
            $decision === 'aprobar'                  => "Evidencia verificada: hallazgo #{$finding->id} cerrado.",
            default                                  => 'Evidencia rechazada: el responsable verá el motivo y subirá otra.',
        });

        return self::SUCCESS;
    }
}
