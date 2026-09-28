<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Services\ApprovalFlowService;
use Illuminate\Console\Command;

/**
 * Corrige reglamentos ya cargados por regulations:import-legacy antes de que ese comando
 * empezara a mandarlos a revisión real (ver ImportLegacyRegulations): quedaron con
 * approval_status=approved y sin ningún registro de aprobación.
 *
 * No basta con voltear el campo — sin registros de RegulationApproval el reglamento queda
 * en un callejón sin salida (nadie, ni un admin, puede editarlo, y la pantalla de flujo sale
 * vacía y contradictoria). Por eso aquí también se llama a
 * ApprovalFlowService::initFlow($notify: false) para crear el flujo real, igual que hace ya
 * el importador corregido — con $notify=false para no mandar de golpe un correo de "tienes que
 * aprobar esto" a cada líder/jefe/gerente por cada documento histórico.
 *
 * Solo toca reglamentos con is_legacy=true (los que vinieron de la carga masiva) — nunca los
 * que se subieron manualmente "con aprobación previa" (RegulationController::storeCargar),
 * que no llevan is_legacy=true y deben seguir tal cual.
 */
class FixLegacyRegulationsApprovalStatus extends Command
{
    protected $signature = 'regulations:fix-legacy-approval-status
        {--dry-run : Muestra lo que se haría sin escribir en base de datos}';

    protected $description = 'Pasa a "en revisión" (con flujo de aprobación real, sin correos) los reglamentos legado que quedaron como aprobados por el importador antes de su fix.';

    public function handle(ApprovalFlowService $flowService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $regulations = Regulation::where('is_legacy', true)
            ->where('approval_status', 'approved')
            ->with('company:id,name')
            ->get();

        if ($regulations->isEmpty()) {
            $this->info('No hay reglamentos legado en estado "approved" — nada que corregir (¿ya se corrió este comando antes?).');

            return self::SUCCESS;
        }

        $fixed = 0;
        $skippedNoImpact = [];
        $skippedHasApprovals = [];

        foreach ($regulations as $regulation) {
            $label = "#{$regulation->id} {$regulation->code} \"{$regulation->name}\" ({$regulation->company?->name})";

            if (! $regulation->impact_level || empty(ApprovalFlowService::getFlowSteps($regulation->impact_level))) {
                $skippedNoImpact[] = $label;

                continue;
            }

            // Por seguridad: si de alguna forma ya tiene registros de aprobación (p. ej. alguien
            // ya le asignó flujo a mano desde la app después de la carga), no lo toco — no quiero
            // pisar un flujo que ya esté en curso.
            if ($regulation->approvals()->exists()) {
                $skippedHasApprovals[] = $label;

                continue;
            }

            $this->line(($dryRun ? '[dry-run] ' : '') . "{$label} -> pending_review, flujo iniciado (impacto: {$regulation->impact_level})");

            if ($dryRun) {
                $fixed++;

                continue;
            }

            $regulation->update([
                'approval_status' => 'pending_review',
                'flow_locked' => true,
            ]);

            $flowService->initFlow($regulation, [], false);

            $fixed++;
        }

        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Corregidos: {$fixed}.");

        if (! empty($skippedNoImpact)) {
            $this->warn('Sin "Impacto" válido — no se les puede armar un flujo automático, hay que asignárselo a mano desde la app:');
            foreach ($skippedNoImpact as $s) {
                $this->line("  - {$s}");
            }
        }

        if (! empty($skippedHasApprovals)) {
            $this->warn('Ya tenían registros de aprobación — se dejaron tal cual, por seguridad:');
            foreach ($skippedHasApprovals as $s) {
                $this->line("  - {$s}");
            }
        }

        return self::SUCCESS;
    }
}
