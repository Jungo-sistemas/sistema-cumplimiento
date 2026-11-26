<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Regulation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Anexos que menciona cada paso del checklist: los códigos de formato ("F-NNN-000") que aparecen en
 * el texto del paso y que existen en Procesos como documentos de la misma empresa, con versión
 * vigente. Cada uno abre su documento directo, como "Ver". Lo que no está cargado no se muestra.
 */
class ChecklistAnnexes
{
    /** Código de formato: "F-", las letras de la empresa y el número ("F-MDI-034", "F-ALC-002a"). */
    private const CODE = '/\bF-[A-Z]{2,6}-\d{1,4}[a-z]?\b/u';

    /**
     * @return array<int, array<int, array{code:string, name:string, url:string}>> por índice del criterio
     */
    public static function forItems(Audit $audit, Collection $items): array
    {
        $codes = $items->values()->map(fn ($item) => self::codesIn($item->activity));
        $all = $codes->flatten()->unique()->values()->all();
        if (! $all || ! $audit->regulation) {
            return [];
        }

        // Mismo grupo y empresa que el proceso auditado; el código se compara en mayúsculas y sin
        // espacios, igual que el filtro de procesos P- del auditor.
        $documents = Regulation::query()
            ->where('group_id', $audit->regulation->group_id)
            ->where('company_id', $audit->regulation->company_id)
            ->where('is_active', true)
            ->whereNull('obsoleted_at')
            ->whereIn(DB::raw('UPPER(TRIM(code))'), $all)
            ->with('currentVersion')
            ->get()
            ->filter(fn (Regulation $document) => $document->currentVersion)
            ->keyBy(fn (Regulation $document) => strtoupper(trim($document->code)));

        return $codes->map(fn (array $list) => collect($list)
            ->map(fn (string $code) => $documents->get($code))
            ->filter()
            ->map(fn (Regulation $document) => [
                'code' => $document->code,
                'name' => $document->name,
                'url'  => route('regulation-versions.preview', $document->currentVersion),
            ])
            ->values()->all()
        )->all();
    }

    /** Los códigos de formato de todo el texto del paso, en mayúsculas, en orden y sin repetir. */
    public static function codesIn(?array $activity): array
    {
        if (! $activity) {
            return [];
        }
        preg_match_all(self::CODE, implode(' ', Arr::flatten($activity)), $matches);

        return array_values(array_unique(array_map('strtoupper', $matches[0])));
    }
}
