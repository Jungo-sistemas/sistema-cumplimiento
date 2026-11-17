<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * "Vista de auditado" (Mi espacio): lo que ve cualquier persona del grupo sobre su propio trabajo —
 * sus documentos, auditorías y actividades. Los admins llegan aquí con el cambio
 * "Gestión / Vista de auditado" del encabezado; los demás roles solo tienen esta vista.
 *
 * Las auditorías y actividades todavía no tienen tablas (AUD-1), así que esas pantallas salen
 * vacías por ahora; Mis documentos ya trae los procesos reales.
 */
class AuditeeController extends Controller
{
    public function __construct()
    {
        // Las páginas compartidas (p. ej. consultar un proceso) se muestran con el menú de la
        // última vista que eligió el admin; entrar a Mi espacio la deja en "auditado".
        $this->middleware(function (Request $request, \Closure $next) {
            $request->session()->put('view_mode', 'auditado');

            return $next($request);
        })->except('switchView');
    }

    public function home(Request $request)
    {
        $user = $request->user();
        $mentionedIds = $this->mentionedRegulationIds($user);
        $documents = $this->publishedQuery($user)
            ->where(fn ($q) => $q->whereIn('company_id', $this->companiesFor($user)->pluck('id'))->orWhereIn('id', $mentionedIds))
            ->get();

        return view('mi-espacio.home', [
            'activeAuditsCount'  => 0,
            'activitiesCount'    => 0,
            'releasedDocsCount'  => $this->decorate($documents, $mentionedIds)->where('auditee_status', 'Liberado')->count(),
            'nextItems'          => collect(),
        ]);
    }

    public function requests()
    {
        return view('mi-espacio.requests', ['requests' => collect()]);
    }

    /**
     * Mis documentos: solo consulta, siempre la última versión aprobada. Dos pestañas:
     * - "Te mencionan": los procesos de cualquier empresa del grupo cuya versión publicada lo
     *   menciona con @persona.
     * - "Procesos de mi empresa": las carpetas por tipo de proceso, igual que en Procesos. Con una
     *   empresa entra directo; con varias (o acceso a todas) primero elige la empresa con tarjetas.
     *   El buscador busca en todas sus empresas.
     * Abre en "Te mencionan" si tiene alguna mención; si no, en la de su empresa.
     */
    public function documents(Request $request)
    {
        $user = $request->user();
        $companies = $this->companiesFor($user);
        $mentionedIds = $this->mentionedRegulationIds($user);
        $search = trim((string) $request->q);

        $tab = match ($request->query('tab')) {
            'menciones' => 'menciones',
            'empresa'   => 'empresa',
            default     => $mentionedIds ? 'menciones' : 'empresa',
        };

        // Sin empresa y sin acceso de grupo no hay procesos de "su empresa" que mostrar.
        $missingCompany = $companies->isEmpty() && ! $user->hasGroupScope() && ! $user->isGlobalScope();

        $data = [
            'tab'             => $tab,
            'companies'       => $companies,
            'search'          => $search,
            'mentionsCount'   => count($mentionedIds),
            'missingCompany'  => $missingCompany,
            'selectedCompany' => null,
            'selectedFolder'  => null,
            'folders'         => collect(),
            'documents'       => collect(),
            'companyCards'    => collect(),
        ];

        if ($tab === 'menciones') {
            $query = $this->publishedQuery($user)->whereIn('id', $mentionedIds);
            $this->applySearch($query, $search);
            $data['documents'] = $this->decorate($query->orderBy('name')->get(), $mentionedIds);

            return view('mi-espacio.documents', $data);
        }

        // Búsqueda: en todas sus empresas (o en la elegida), en una sola lista.
        $selectedCompany = $companies->count() === 1
            ? $companies->first()
            : $companies->firstWhere('id', $request->integer('company_id'));
        $data['selectedCompany'] = $selectedCompany;

        $companyQuery = fn () => $this->publishedQuery($user)
            ->whereIn('company_id', $selectedCompany ? [$selectedCompany->id] : $companies->pluck('id'));

        if ($search !== '') {
            $query = $companyQuery();
            $this->applySearch($query, $search);
            $data['documents'] = $this->decorate($query->orderBy('name')->get(), $mentionedIds);

            return view('mi-espacio.documents', $data);
        }

        // Varias empresas (o todas) sin elegir: tarjetas de empresa.
        if (! $selectedCompany) {
            $counts = $companyQuery()->reorder()->selectRaw('company_id, count(*) as total')->groupBy('company_id')->pluck('total', 'company_id');
            $data['companyCards'] = $companies->map(fn (Company $c) => ['company' => $c, 'count' => (int) ($counts[$c->id] ?? 0)]);

            return view('mi-espacio.documents', $data);
        }

        $processTypes = ProcessType::where('group_id', $user->group_id)->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();
        $folderId = $request->integer('process_type_id') ?: null;

        // Dentro de una carpeta: sus procesos publicados.
        if ($folderId) {
            $type = $processTypes->firstWhere('id', $folderId);
            $data['selectedFolder'] = ['id' => $folderId, 'number' => $type?->sort_order, 'name' => $type?->name ?? 'Carpeta'];
            $data['documents'] = $this->decorate(
                $companyQuery()->where('process_type_id', $folderId)->orderBy('code')->orderBy('name')->get(),
                $mentionedIds
            );

            return view('mi-espacio.documents', $data);
        }

        // Las carpetas de la empresa, una por tipo de proceso.
        $counts = $companyQuery()->reorder()->selectRaw('process_type_id, count(*) as total')->groupBy('process_type_id')->pluck('total', 'process_type_id');
        $data['folders'] = $processTypes->map(fn ($type) => [
            'id'     => $type->id,
            'number' => $type->sort_order,
            'name'   => $type->name,
            'count'  => (int) ($counts[$type->id] ?? 0),
        ]);

        return view('mi-espacio.documents', $data);
    }

    /**
     * Consulta de un proceso desde Mi espacio: solo lectura y siempre la última versión aprobada,
     * aunque en Procesos se esté editando una nueva.
     */
    public function showDocument(Request $request, Regulation $regulation)
    {
        $user = $request->user();
        // La empresa completa: canAccessCompany() usa group_id e is_restricted.
        $regulation->load(['publishedVersion', 'currentVersion', 'processType:id,name', 'company']);
        $published = $regulation->publishedVersion;

        abort_unless($published && $regulation->is_active && ! $regulation->obsoleted_at && $regulation->group_id === $user->group_id, 404);
        abort_unless($user->canAccessCompany($regulation->company) || $published->mentionsUser($user), 403);

        $this->decorate(collect([$regulation]), $published->mentionsUser($user) ? [$regulation->id] : []);

        return view('mi-espacio.document', [
            'regulation' => $regulation,
            'version'    => $published,
        ]);
    }

    public function audits(Request $request)
    {
        $user = $request->user();

        return view('mi-espacio.audits', [
            'tab'       => $request->query('tab') === 'historial' ? 'historial' : 'activas',
            'companies' => $this->companiesFor($user),
            'audits'    => collect(),
        ]);
    }

    public function activities(Request $request)
    {
        return view('mi-espacio.activities', [
            'tab'        => $request->query('tab') === 'concluidas' ? 'concluidas' : 'por-atender',
            'activities' => collect(),
        ]);
    }

    public function help()
    {
        return view('mi-espacio.help');
    }

    // Inicio de cada módulo, para el selector de módulos de la vista de auditado.
    private const MODULE_HOMES = [
        'cumplimiento' => 'dashboard',
        'procesos'     => 'gestion.home',
    ];

    /**
     * Cambio "Gestión / Mi espacio" del encabezado de Procesos y Auditorías. Desde el selector de
     * módulos de Mi espacio llega con ?modulo= para pasar a Gestión directo en ese módulo.
     */
    public function switchView(Request $request, string $mode)
    {
        $user = $request->user();

        // Quien no tiene Gestión (solo lectura) siempre cae en su vista de auditado.
        if ($mode === 'auditado' || $user->isAuditeeOnly()) {
            abort_unless($user->canUseAuditeeView(), 403);
            $request->session()->put('view_mode', 'auditado');

            return redirect()->route('mi-espacio.home');
        }

        $request->session()->put('view_mode', 'gestion');

        return redirect()->route(self::MODULE_HOMES[$request->query('modulo')] ?? 'gestion.home');
    }

    /** Empresas del usuario que se muestran en Procesos (mismo criterio que el listado de procesos). */
    private function companiesFor(User $user): Collection
    {
        return Company::query()
            ->when(! $user->isGlobalScope(), fn ($q) => $q->whereIn('id', $user->accessibleCompanyIds()))
            ->where('show_in_processes', true)
            ->where('otras', false)
            ->orderByRaw(Company::PROCESOS_NAME_SQL)
            ->get(['id', 'name', 'process_name']);
    }

    /**
     * Procesos del grupo con una versión aprobada que consultar (no anexos, ni en papelera ni
     * obsoletos). Los que nunca se han aprobado no salen: todavía no hay nada que consultar.
     */
    private function publishedQuery(User $user): Builder
    {
        return Regulation::with(['publishedVersion', 'currentVersion', 'processType:id,name', 'company:id,name,process_name'])
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->where('is_annex', false)
            ->whereNull('obsoleted_at')
            ->whereHas('versions', fn ($v) => $v->whereNotNull('approved_at'));
    }

    private function applySearch(Builder $query, string $search): void
    {
        if ($search === '') {
            return;
        }

        $like = '%' . mb_strtolower($search) . '%';
        $query->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])->orWhereRaw('LOWER(code) LIKE ?', [$like]));
    }

    /** Estado que ve el auditado, según la versión publicada, y si se está actualizando. */
    private function decorate(Collection $regulations, array $mentionedIds): Collection
    {
        return $regulations->each(function (Regulation $regulation) use ($mentionedIds) {
            $published = $regulation->publishedVersion;

            // "Obsoleto" es lo mismo que la vista Obsoleto de Procesos: la versión ya venció.
            $regulation->auditee_status = match (true) {
                $regulation->approval_status === 'outdated'          => 'Desactualizado',
                (bool) $published?->valid_until?->isPast()           => 'Obsoleto',
                default                                              => 'Liberado',
            };
            $regulation->mentions_user = in_array($regulation->id, $mentionedIds, true);
            // Hay una versión más nueva en revisión: aquí se sigue mostrando la aprobada.
            $regulation->being_updated = $published && $regulation->currentVersion
                && $regulation->currentVersion->version_number > $published->version_number;
        });
    }

    /**
     * Procesos del grupo cuya última versión aprobada menciona al usuario con @persona. El LIKE
     * solo acota; la mención se confirma con RegulationVersion::mentionsUser() sobre la versión
     * publicada (una mención agregada en una edición que sigue en revisión todavía no cuenta).
     *
     * @return list<int>
     */
    private function mentionedRegulationIds(User $user): array
    {
        $candidates = RegulationVersion::query()
            ->whereNotNull('approved_at')
            ->where('body_html', 'like', '%person-mention%')
            ->where('body_html', 'like', '%data-id="' . $user->id . '"%')
            ->whereHas('regulation', fn ($q) => $q->where('group_id', $user->group_id))
            ->distinct()
            ->pluck('regulation_id');

        if ($candidates->isEmpty()) {
            return [];
        }

        return Regulation::with('publishedVersion')
            ->whereIn('id', $candidates)
            ->get(['id'])
            ->filter(fn (Regulation $r) => (bool) $r->publishedVersion?->mentionsUser($user))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
