<?php

namespace App\Application\Compliance;

use App\Models\AssetRequirement;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Nivel de cumplimiento de los requerimientos de los activos: qué tanto de lo que se pide ya está
 * cumplido, en general y dividido por impacto (la prioridad Alta/Media/Baja de la plantilla del
 * requerimiento). Se puede acotar por empresa, tipo de activo e impacto.
 *
 * Estado de cada requerimiento (los cancelados no cuentan):
 *  - cumplido:   completado y su documento sigue vigente (sin vencimiento o vence hoy o después)
 *  - en_tramite: en trámite con la autoridad
 *  - vencido:    completado con el documento ya vencido, o sin completar y con la fecha límite pasada
 *  - pendiente:  todo lo demás
 */
final class ComplianceLevelService
{
    public const IMPACTS = [
        'alta'  => 'Alta',
        'media' => 'Media',
        'baja'  => 'Baja',
        'sin'   => 'Sin impacto definido',
    ];

    public const STATES = [
        'cumplido'   => 'Cumplido',
        'en_tramite' => 'En trámite',
        'pendiente'  => 'Pendiente',
        'vencido'    => 'Vencido',
    ];

    private const IMPACT_SQL = "COALESCE(NULLIF(LOWER(TRIM(requirement_templates.priority)), ''), 'sin')";

    /**
     * @param  int[]  $companyIds  empresas que el usuario puede ver
     * @param  array{company_id?:int|null, asset_type_id?:int|null, impact?:string|null}  $filters
     */
    public function summary(array $companyIds, array $filters = []): array
    {
        $empty = fn () => array_fill_keys(array_keys(self::STATES), 0) + ['total' => 0];

        $rows = $this->baseQuery($companyIds, $filters)
            ->selectRaw('assets.company_id, companies.name as company_name, assets.asset_type_id, asset_types.name as asset_type_name')
            ->selectRaw(self::IMPACT_SQL . ' as impact')
            ->selectRaw('COUNT(*) as total');

        foreach (array_keys(self::STATES) as $state) {
            [$sql, $bindings] = $this->stateCondition($state);
            $rows->selectRaw("SUM(CASE WHEN {$sql} THEN 1 ELSE 0 END) as {$state}", $bindings);
        }

        $rows = $rows->groupBy('assets.company_id', 'companies.name', 'assets.asset_type_id', 'asset_types.name')
            ->groupByRaw(self::IMPACT_SQL)
            ->toBase()
            ->get();

        $overall   = $empty();
        $byImpact  = array_map(fn () => $empty(), self::IMPACTS);
        $byCompany = [];
        $byType    = [];

        foreach ($rows as $r) {
            $byCompany[$r->company_id] ??= ['name' => $r->company_name] + $empty();
            $byType[$r->asset_type_id] ??= ['name' => $r->asset_type_name ?? 'Sin tipo'] + $empty();
            $impact = array_key_exists($r->impact, self::IMPACTS) ? $r->impact : 'sin';

            foreach (array_merge(array_keys(self::STATES), ['total']) as $k) {
                $n = (int) $r->{$k};
                $overall[$k] += $n;
                $byImpact[$impact][$k] += $n;
                $byCompany[$r->company_id][$k] += $n;
                $byType[$r->asset_type_id][$k] += $n;
            }
        }

        $withPct = function (array $row) {
            $row['pct'] = $row['total'] > 0 ? round($row['cumplido'] * 100 / $row['total'], 1) : null;
            return $row;
        };

        $sortByName = fn (array $list) => collect($list)->map($withPct)->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)->values()->all();

        return [
            'overall'    => $withPct($overall),
            'by_impact'  => collect($byImpact)
                ->map(fn ($row, $key) => $withPct(['key' => $key, 'name' => self::IMPACTS[$key]] + $row))
                // "Sin impacto definido" solo si hay requerimientos así.
                ->reject(fn ($row) => $row['key'] === 'sin' && $row['total'] === 0)
                ->values()->all(),
            'by_company' => $sortByName($byCompany),
            'by_type'    => $sortByName($byType),
        ];
    }

    /**
     * Cada requerimiento con su estado, para el detalle exportable. Se recorre con cursor para no
     * cargar decenas de miles de modelos en memoria.
     */
    public function detail(array $companyIds, array $filters = []): \Generator
    {
        $query = $this->baseQuery($companyIds, $filters)
            ->leftJoin('asset_requirement_documents as doc', 'doc.id', '=', 'asset_requirements.current_document_id')
            ->select([
                'companies.name as empresa',
                'asset_types.name as tipo_activo',
                'assets.name as activo',
                'assets.code as codigo',
                'assets.no_economico',
                'requirement_templates.name as requerimiento',
                'asset_requirements.type',
                'asset_requirements.due_date',
                'asset_requirements.expires_at',
                'doc.original_name as documento',
            ])
            ->selectRaw(self::IMPACT_SQL . ' as impact');

        $whens = [];
        $bindings = [];
        foreach (array_keys(self::STATES) as $state) {
            [$sql, $b] = $this->stateCondition($state);
            $whens[] = "WHEN {$sql} THEN '{$state}'";
            array_push($bindings, ...$b);
        }

        $query->selectRaw('CASE ' . implode(' ', $whens) . " ELSE 'pendiente' END as estado", $bindings)
            ->orderBy('companies.name')->orderBy('asset_types.name')->orderBy('assets.name')
            ->orderBy('requirement_templates.name');

        foreach ($query->toBase()->cursor() as $r) {
            yield [
                'empresa'       => $r->empresa,
                'tipo_activo'   => $r->tipo_activo ?? '—',
                'activo'        => $r->activo,
                'codigo'        => $r->no_economico ?: ($r->codigo ?: ''),
                'requerimiento' => $r->requerimiento ?? $r->type ?? 'Requerimiento',
                'impacto'       => self::IMPACTS[$r->impact] ?? self::IMPACTS['sin'],
                'estado'        => self::STATES[$r->estado] ?? $r->estado,
                'fecha_limite'  => $r->due_date ? Carbon::parse($r->due_date)->format('d/m/Y') : '',
                'vigencia'      => $r->expires_at ? Carbon::parse($r->expires_at)->format('d/m/Y') : '',
                'documento'     => $r->documento ?? '',
            ];
        }
    }

    /**
     * Requerimientos más próximos a vencer (de hoy en adelante), paginados. La fecha es la del
     * vencimiento del documento si ya se cumplió, o la fecha límite si sigue pendiente. Los ya
     * vencidos no salen aquí (cuentan como "Vencido" en la gráfica) ni los que están en trámite.
     */
    public function upcoming(array $companyIds, array $filters = [], int $perPage = 20)
    {
        $today = Carbon::today()->toDateString();
        $dueSql = "CASE WHEN asset_requirements.status = 'completed' THEN asset_requirements.expires_at ELSE asset_requirements.due_date END";

        return $this->baseQuery($companyIds, $filters)
            ->whereNotIn('asset_requirements.status', ['in_transit', 'expired'])
            ->whereRaw("{$dueSql} >= ?", [$today])
            ->select([
                'asset_requirements.id',
                'asset_requirements.asset_id',
                'asset_requirements.status',
                'asset_requirements.type',
                'assets.name as asset_name',
                'companies.name as company_name',
                'asset_types.name as asset_type_name',
                'requirement_templates.name as template_name',
            ])
            ->selectRaw(self::IMPACT_SQL . ' as impact')
            ->selectRaw("{$dueSql} as vence")
            ->orderByRaw("{$dueSql} asc")
            ->orderBy('asset_requirements.id')
            ->paginate($perPage, ['*'], 'pagina')
            ->withQueryString();
    }

    /** Filtros válidos a partir de la petición (los que no aplican se ignoran). */
    public static function filtersFrom(array $input, array $companyIds): array
    {
        $companyId = isset($input['empresa']) && $input['empresa'] !== '' ? (int) $input['empresa'] : null;
        $typeId    = isset($input['tipo_activo']) && $input['tipo_activo'] !== '' ? (int) $input['tipo_activo'] : null;
        $impact    = $input['impacto'] ?? null;

        return [
            'company_id'    => in_array($companyId, $companyIds, true) ? $companyId : null,
            'asset_type_id' => $typeId ?: null,
            'impact'        => array_key_exists((string) $impact, self::IMPACTS) ? $impact : null,
        ];
    }

    private function baseQuery(array $companyIds, array $filters): Builder
    {
        return AssetRequirement::query()
            ->join('assets', 'assets.id', '=', 'asset_requirements.asset_id')
            ->join('companies', 'companies.id', '=', 'assets.company_id')
            ->leftJoin('asset_types', 'asset_types.id', '=', 'assets.asset_type_id')
            ->leftJoin('requirement_templates', 'requirement_templates.id', '=', 'asset_requirements.requirement_template_id')
            ->whereIn('assets.company_id', ! empty($filters['company_id']) ? [$filters['company_id']] : $companyIds)
            ->where('asset_requirements.status', '!=', 'cancelled')
            ->when($filters['asset_type_id'] ?? null, fn ($q, $id) => $q->where('assets.asset_type_id', $id))
            ->when($filters['impact'] ?? null, fn ($q, $impact) => $q->whereRaw(self::IMPACT_SQL . ' = ?', [$impact]));
    }

    /** @return array{0:string,1:array} condición SQL (portable) de cada estado */
    private function stateCondition(string $state): array
    {
        $today = Carbon::today()->toDateString();

        return match ($state) {
            'cumplido' => [
                "asset_requirements.status = 'completed' AND (asset_requirements.expires_at IS NULL OR asset_requirements.expires_at >= ?)",
                [$today],
            ],
            'en_tramite' => ["asset_requirements.status = 'in_transit'", []],
            'vencido' => [
                "((asset_requirements.status = 'completed' AND asset_requirements.expires_at < ?)"
                . " OR (asset_requirements.status NOT IN ('completed', 'in_transit') AND (asset_requirements.status = 'expired' OR asset_requirements.due_date < ?)))",
                [$today, $today],
            ],
            'pendiente' => [
                "asset_requirements.status NOT IN ('completed', 'in_transit', 'expired')"
                . " AND (asset_requirements.due_date IS NULL OR asset_requirements.due_date >= ?)",
                [$today],
            ],
        };
    }
}
