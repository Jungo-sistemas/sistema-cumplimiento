<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssetRequirement;
use App\Enums\RequirementStatus;
use App\Notifications\RequirementDueSoonNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;

class NotifyDueSoonRequirements extends Command
{
    protected $signature = 'requirements:notify-due-soon
                            {--dry-run : Muestra a quién se notificaría sin enviar correos}';

    protected $description = 'Notifica por email a los responsables de activos con requisitos próximos a vencer (60, 30, 7 y 1 día)';

    // Días exactos en los que se dispara una notificación
    private const THRESHOLDS = [60, 30, 7, 1];

    public function handle(): int
    {
        $today = now()->startOfDay();

        foreach (self::THRESHOLDS as $days) {
            $targetDate = $today->copy()->addDays($days)->toDateString();

            $requirements = AssetRequirement::query()
                ->whereDate('due_date', $targetDate)
                ->whereNotIn('status', [
                    RequirementStatus::COMPLETED,
                    RequirementStatus::CANCELLED,
                    RequirementStatus::EXPIRED,
                ])
                ->with(['asset.responsible', 'template'])
                ->get();

            if ($requirements->isEmpty()) {
                continue;
            }

            // Agrupar por usuario responsable del activo
            $byUser = $requirements
                ->filter(fn ($r) => $r->asset?->responsible !== null)
                ->groupBy(fn ($r) => $r->asset->responsible->id);

            foreach ($byUser as $userId => $userRequirements) {
                $user = $userRequirements->first()->asset->responsible;
                $collection = Collection::make($userRequirements);

                if ($this->option('dry-run')) {
                    $this->line("  [dry-run] {$days}d → {$user->email} ({$collection->count()} requisito(s))");
                    continue;
                }

                $user->notify(new RequirementDueSoonNotification($collection, $days));
            }

            if (!$this->option('dry-run')) {
                $this->info("Notificados: {$days} días → " . $byUser->count() . " usuario(s)");
            }
        }

        return self::SUCCESS;
    }
}