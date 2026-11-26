<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\Regulation;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

/**
 * Anexos que menciona cada paso del checklist: los formatos ("F-…") de la misma empresa que están
 * cargados en Procesos y cuyo código aparece en el texto del paso. Cada uno abre su versión
 * publicada (la última aprobada, la misma que ve el auditado), como "Ver". Los que nunca se han
 * aprobado no se muestran.
 *
 * Igual que la vista "Ver" (RegulationVersionController::linkRegulationCodes), se buscan los
 * códigos que existen de verdad, del más largo al más corto, en vez de adivinar su forma: así da
 * igual si vienen en mayúsculas o minúsculas, cuántos dígitos tienen o si llevan sufijo.
 */
class ChecklistAnnexes
{
    /**
     * @return array<int|string, array<int, array{code:string, name:string, url:string}>> con las mismas claves que $items
     */
    public static function forItems(Audit $audit, Collection $items): array
    {
        if (! $audit->regulation) {
            return [];
        }

        // Los formatos vivos de la empresa (solo id, código y nombre), del código más largo al más corto.
        $formats = Regulation::query()
            ->where('group_id', $audit->regulation->group_id)
            ->where('company_id', $audit->regulation->company_id)
            ->where('is_active', true)
            ->whereNull('obsoleted_at')
            ->whereRaw('UPPER(TRIM(code)) LIKE ?', ['F-%'])
            ->get(['id', 'code', 'name'])
            ->sortByDesc(fn (Regulation $format) => mb_strlen(trim($format->code)))
            ->values();
        if ($formats->isEmpty()) {
            return [];
        }

        // Los que menciona cada criterio; se conservan las claves de $items (la vista usa las mismas).
        $mentioned = $items->map(fn ($item) => self::mentionedIn($item->activity, $formats));

        // Solo se cargan las versiones publicadas de los que sí se mencionan.
        $published = $formats->whereIn('id', $mentioned->flatten()->unique()->all())
            ->load('publishedVersion')
            ->filter(fn (Regulation $format) => $format->publishedVersion)
            ->keyBy('id');

        return $mentioned->map(fn (array $ids) => collect($ids)
            ->map(fn (int $id) => $published->get($id))
            ->filter()
            ->sortBy(fn (Regulation $format) => trim($format->code), SORT_NATURAL)
            ->map(fn (Regulation $format) => [
                'code' => trim($format->code),
                'name' => $format->name,
                'url'  => route('regulation-versions.preview', $format->publishedVersion),
            ])
            ->values()->all()
        )->all();
    }

    /**
     * Ids de los formatos cuyo código aparece en el texto del paso, sin importar mayúsculas. Un código
     * no cuenta si es el inicio de otro más largo ("F-ALC-301" dentro de "F-ALC-301-02").
     */
    private static function mentionedIn(?array $activity, Collection $formats): array
    {
        if (! $activity) {
            return [];
        }
        $text = implode(' ', Arr::flatten($activity));

        return $formats
            ->filter(fn (Regulation $format) => mb_stripos($text, trim($format->code)) !== false
                && preg_match('/(?<![\d-])' . preg_quote(trim($format->code), '/') . '(?![\p{L}\d]|-[\p{L}\d])/iu', $text))
            ->pluck('id')
            ->all();
    }
}
