<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\AssetType;
use App\Models\RequirementTemplate;
use App\Services\SyncAssetRequirementsService;
use Illuminate\Console\Command;

/**
 * Crea los asset_requirements faltantes para las plantas que ya existían antes de cargar
 * PlantasRequirementTemplateSeeder (Checklist Plantas.csv) — sin tocar los que ya existen
 * (SyncAssetRequirementsService::handle() usa updateOrCreate por (asset_id,
 * requirement_template_id), así que nunca resetea el progreso de un requerimiento que ya
 * existiera antes; solo agrega los pares que faltan contra el catálogo nuevo).
 */
class BackfillPlantasRequirements extends Command
{
    protected $signature = 'compliance:backfill-plantas-requirements {--dry-run : Solo muestra lo que haría, sin guardar cambios}';

    protected $description = 'Crea los asset_requirements faltantes para plantas existentes contra el checklist de Plantas.';

    public function handle(SyncAssetRequirementsService $syncService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $assetType = AssetType::where('name', 'Plantas')->first();

        if (! $assetType) {
            $this->error('No existe el asset type Plantas.');

            return self::FAILURE;
        }

        $templateIds = RequirementTemplate::where('asset_type_id', $assetType->id)->pluck('id');
        $assets = Asset::where('asset_type_id', $assetType->id)->get();

        $this->info("Plantas: {$assets->count()} activos, {$templateIds->count()} requerimientos en el catálogo.");

        if ($dryRun) {
            $existingPairs = AssetRequirement::whereIn('asset_id', $assets->pluck('id'))
                ->whereIn('requirement_template_id', $templateIds)
                ->get(['asset_id', 'requirement_template_id'])
                ->map(fn ($r) => "{$r->asset_id}:{$r->requirement_template_id}")
                ->flip();

            $toCreate = 0;
            foreach ($assets as $asset) {
                foreach ($templateIds as $templateId) {
                    if (! isset($existingPairs["{$asset->id}:{$templateId}"])) {
                        $toCreate++;
                    }
                }
            }

            $this->info("[DRY RUN] Se crearían {$toCreate} asset_requirements nuevos.");

            return self::SUCCESS;
        }

        $before = AssetRequirement::whereIn('asset_id', $assets->pluck('id'))
            ->whereIn('requirement_template_id', $templateIds)
            ->count();

        foreach ($assets as $asset) {
            $syncService->handle($asset);
        }

        $after = AssetRequirement::whereIn('asset_id', $assets->pluck('id'))
            ->whereIn('requirement_template_id', $templateIds)
            ->count();

        $this->info('Completado. ' . ($after - $before) . " asset_requirements nuevos creados ({$after} en total contra este catálogo).");

        return self::SUCCESS;
    }
}
