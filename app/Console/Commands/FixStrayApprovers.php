<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Services\ApprovalFlowService;
use Illuminate\Console\Command;

/**
 * Ubica los procesos cuyo flujo abierto tiene aprobadores que el admin no eligió: los metió la
 * regla vieja que llenaba los puestos vacíos con todo el puesto (P-SAV-111 quedó con 16 líderes,
 * nov 2026). Sin --aplicar solo los lista. Con --aplicar les rearma el flujo desde el paso en
 * curso con la regla actual (los pasos sin nadie elegido se saltan) y avisa a quien quede con el
 * turno; las decisiones ya tomadas se quedan como historial.
 */
class FixStrayApprovers extends Command
{
    protected $signature = 'procesos:revisar-aprobadores {--aplicar : Rearma el flujo de los afectados}';

    protected $description = 'Lista (y con --aplicar corrige) los flujos con aprobadores que no se eligieron';

    public function handle(ApprovalFlowService $flow): int
    {
        $affected = Regulation::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->whereIn('approval_status', ['pending_review', 'pending_authorization'])
            ->whereNotNull('flow_user_map')
            ->with(['company' => fn ($q) => $q->withoutGlobalScopes()])
            ->orderBy('code')
            ->get()
            ->map(fn (Regulation $r) => [$r, $flow->strayOpenApprovals($r)])
            ->filter(fn ($row) => $row[1]->isNotEmpty())
            ->values();

        if ($affected->isEmpty()) {
            $this->info('Ningún flujo abierto tiene aprobadores que no se eligieron.');

            return self::SUCCESS;
        }

        $this->warn("{$affected->count()} procesos con aprobadores que no se eligieron:");
        $this->table(
            ['ID', 'Empresa', 'Código', 'Nombre', 'Paso', 'Sobran'],
            $affected->map(fn ($row) => [
                $row[0]->id,
                $row[0]->company?->procesos_name ?? '—',
                $row[0]->code ?? '—',
                mb_strimwidth((string) $row[0]->name, 0, 40, '…'),
                $row[1]->min('step_number'),
                $row[1]->count(),
            ])->all()
        );

        if (! $this->option('aplicar')) {
            $this->line('No se cambió nada. Corre con --aplicar para rearmar sus flujos.');

            return self::SUCCESS;
        }

        foreach ($affected as [$regulation]) {
            $step = $flow->rebuildOpenFlow($regulation);
            $this->line("{$regulation->code}: " . ($step ? "queda en el paso {$step}" : 'ya no quedaban pasos con aprobadores: queda aprobado'));
        }

        $this->info('Listo.');

        return self::SUCCESS;
    }
}
