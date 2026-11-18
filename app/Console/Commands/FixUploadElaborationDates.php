<?php

namespace App\Console\Commands;

use App\Models\Regulation;
use App\Services\RegulationDocxHeaderRefresher;
use Illuminate\Console\Command;

/**
 * "Cargar" guardaba la fecha de VENCIMIENTO en details.fecha_vigencia, que es la "Fecha de
 * elaboración" (Info básica y encabezado del documento). Aquí se corrige a la fecha de emisión de
 * la versión 1 en los documentos subidos cuyo valor coincide justo con el vencimiento de esa
 * versión — el patrón exacto del bug. Si el documento ya se editó en línea (su Word trae el
 * encabezado del sistema), también se le reescribe el encabezado.
 */
class FixUploadElaborationDates extends Command
{
    protected $signature = 'fix:upload-elaboration-dates
                            {--dry-run : Muestra qué procesos cambiarían sin guardar nada}';

    protected $description = 'Corrige la "Fecha de elaboración" de los procesos subidos con Cargar, que guardaba la fecha de vencimiento';

    public function handle(RegulationDocxHeaderRefresher $refresher): int
    {
        $dryRun = $this->option('dry-run');
        $count = 0;

        Regulation::withoutGlobalScopes()->withTrashed()
            ->with(['versions' => fn ($q) => $q->reorder('version_number')])
            ->chunkById(100, function ($regulations) use ($dryRun, $refresher, &$count) {
                foreach ($regulations as $regulation) {
                    $first = $regulation->versions->first();
                    $details = $regulation->details ?? [];
                    $stored = $details['fecha_vigencia'] ?? null;

                    // Solo lo subido con Cargar (sin HTML del sistema) con el vencimiento guardado como elaboración.
                    if (! $first || $first->body_html || ! $stored || ! $first->valid_until || ! $first->issued_at
                        || $first->valid_until->toDateString() !== $stored
                        || $first->issued_at->toDateString() === $stored) {
                        continue;
                    }

                    $fixed = $first->issued_at->toDateString();
                    $count++;
                    $this->line(($dryRun ? '[dry-run] ' : '') . "{$regulation->id} {$regulation->code} — {$regulation->name}: {$stored} → {$fixed}");

                    if (! $dryRun) {
                        $regulation->update(['details' => array_merge($details, ['fecha_vigencia' => $fixed])]);

                        // Si falla el encabezado de uno, la fecha ya quedó y se sigue con los demás.
                        try {
                            $refresher->refresh($regulation);
                        } catch (\Throwable $e) {
                            report($e);
                            $this->warn("   No se pudo actualizar el encabezado de {$regulation->id}: {$e->getMessage()}");
                        }
                    }
                }
            });

        $this->info(($dryRun ? '[dry-run] Procesos a corregir: ' : 'Procesos corregidos: ') . $count);

        return self::SUCCESS;
    }
}
