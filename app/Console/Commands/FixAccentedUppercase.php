<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\Document;
use App\Models\Regulation;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Corrige registros guardados antes del fix de strtoupper() vs Str::upper(): strtoupper() no es
 * multibyte-safe, así que dejaba las letras acentuadas en minúscula dentro de textos que debían
 * quedar en mayúsculas (ej. "ATENCIóN" en vez de "ATENCIÓN"). Como el resto del texto ya estaba
 * bien mayusculado, basta con volver a aplicar Str::upper() sobre el valor YA guardado — es
 * idempotente: si ya está bien, no cambia nada.
 */
class FixAccentedUppercase extends Command
{
    protected $signature = 'fix:accented-uppercase
                            {--dry-run : Muestra qué registros cambiarían sin guardar nada}';

    protected $description = 'Corrige acentos en mayúsculas mal convertidos por el bug de strtoupper() (nombre/código de reglamentos, nombre de documentos, ubicación de activos)';

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $total = 0;

        $total += $this->fixColumn(Regulation::query(), 'name', $dryRun);
        $total += $this->fixColumn(Regulation::query()->whereNotNull('code'), 'code', $dryRun);
        $total += $this->fixColumn(Document::query(), 'name', $dryRun);
        $total += $this->fixColumn(Asset::query()->whereNotNull('location')->where('location', '!=', ''), 'location', $dryRun);

        $this->info(($dryRun ? '[dry-run] ' : '') . "Total de registros " . ($dryRun ? 'a corregir' : 'corregidos') . ": {$total}");

        return self::SUCCESS;
    }

    private function fixColumn($query, string $column, bool $dryRun): int
    {
        $model = $query->getModel();
        $label = class_basename($model) . ".{$column}";
        $count = 0;

        $query->select(['id', $column])->chunkById(200, function ($rows) use ($column, $label, $dryRun, &$count) {
            foreach ($rows as $row) {
                $original = $row->{$column};
                $fixed = Str::upper($original);

                if ($fixed === $original) {
                    continue;
                }

                // No basta con "Str::upper cambiaría esto" — eso también sería cierto para un
                // valor en formato normal que nunca pasó por el bug (ej. "Nuevo León" sembrado por
                // un seeder), y lo dejaría en MAYÚSCULAS sin necesidad. Solo se corrige si el valor
                // guardado YA es exactamente lo que produce el strtoupper() viejo (o sea, ya pasó
                // por él) — ese es el patrón real del bug, no cualquier texto con acentos.
                if (strtoupper($original) !== $original) {
                    continue;
                }

                $count++;

                if ($dryRun) {
                    $this->line("  [dry-run] {$label} #{$row->id}: \"{$original}\" -> \"{$fixed}\"");
                } else {
                    $row->{$column} = $fixed;
                    $row->timestamps = false; // no es un cambio de contenido real, solo ortográfico
                    $row->save();
                }
            }
        });

        if ($count > 0) {
            $this->info(($dryRun ? '[dry-run] ' : '') . "{$label}: {$count} registro(s)");
        }

        return $count;
    }
}
