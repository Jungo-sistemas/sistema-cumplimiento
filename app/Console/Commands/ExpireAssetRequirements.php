<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AssetRequirement;
use App\Enums\RequirementStatus;

class ExpireAssetRequirements extends Command
{
    protected $signature = 'requirements:expire {--dry-run : No escribe cambios, solo muestra cuántos expiraría}';
    protected $description = 'Marca como EXPIRED los requirements vencidos que no estén completed/cancelled';

    public function handle(): int
    {
        $query = AssetRequirement::query()
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->whereNotIn('status', [
                RequirementStatus::COMPLETED,
                RequirementStatus::CANCELLED,
                RequirementStatus::EXPIRED,
                RequirementStatus::IN_TRANSIT,
            ]);

        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry run: expiraría {$count} requirement(s).");
            return self::SUCCESS;
        }

        $updated = $query->update([
            'status' => RequirementStatus::EXPIRED,
        ]);

        $this->info("Expirados: {$updated} requirement(s).");
        return self::SUCCESS;
    }
}