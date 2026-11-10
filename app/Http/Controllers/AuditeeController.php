<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Regulation;
use App\Models\User;
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
        $documents = $this->documentsFor($request->user(), $this->companiesFor($request->user()));

        return view('mi-espacio.home', [
            'activeAuditsCount'  => 0,
            'activitiesCount'    => 0,
            'releasedDocsCount'  => $documents->where('auditee_status', 'Liberado')->count(),
            'nextItems'          => collect(),
        ]);
    }

    public function requests()
    {
        return view('mi-espacio.requests', ['requests' => collect()]);
    }

    public function documents(Request $request)
    {
        $user = $request->user();
        $companies = $this->companiesFor($user);

        // Solo se acepta una empresa a la que el usuario tenga acceso; cualquier otro valor = todas.
        $selectedCompanyId = $companies->firstWhere('id', $request->integer('company_id'))?->id;
        $search = trim((string) $request->q);

        $documents = $this->documentsFor($user, $selectedCompanyId ? $companies->where('id', $selectedCompanyId) : $companies, $search);

        return view('mi-espacio.documents', [
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'search'            => $search,
            'documents'         => $documents,
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
        'procesos'     => 'processes.dashboard',
        'auditorias'   => 'auditorias.home',
    ];

    /**
     * Cambio "Gestión / Vista de auditado" del encabezado de Auditorías: Gestión es el lado del
     * auditor (/auditorias) y Vista de auditado es Mi espacio. Desde el selector de módulos de la
     * vista de auditado llega con ?modulo= para pasar a Gestión directo en ese módulo.
     */
    public function switchView(Request $request, string $mode)
    {
        $user = $request->user();

        // Solo admins y auditores tienen Gestión; los demás roles siempre caen en su vista de auditado.
        if ($mode === 'auditado' || $user->isAuditeeOnly()) {
            abort_unless($user->canUseAuditeeView(), 403);
            $request->session()->put('view_mode', 'auditado');

            return redirect()->route('mi-espacio.home');
        }

        $request->session()->put('view_mode', 'gestion');

        return redirect()->route(self::MODULE_HOMES[$request->query('modulo')] ?? 'auditorias.home');
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
     * Procesos que le aplican al usuario: por ahora, todos los de sus empresas que ya tienen una
     * versión publicada (el control fino de "a quién aplica" se hará del lado de Procesos). Los
     * que nunca se han aprobado no se listan porque todavía no hay nada que consultar.
     */
    private function documentsFor(User $user, Collection $companies, string $search = ''): Collection
    {
        return Regulation::with(['currentVersion', 'processType:id,name', 'company:id,name,process_name'])
            ->whereIn('company_id', $companies->pluck('id'))
            ->where('is_active', true)
            ->where('is_annex', false)
            ->whereNull('obsoleted_at')
            ->where(fn ($q) => $q->whereIn('approval_status', ['approved', 'outdated'])
                ->orWhereHas('currentVersion', fn ($v) => $v->whereNotNull('valid_until')))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $q->where(fn ($q) => $q->whereRaw('LOWER(name) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(code) LIKE ?', [$like]));
            })
            ->orderBy('name')
            ->get()
            ->each(function (Regulation $regulation) {
                // "Obsoleto" es lo mismo que la vista Obsoleto de Procesos: la versión vigente ya venció.
                $regulation->auditee_status = match (true) {
                    $regulation->approval_status === 'outdated'                  => 'Desactualizado',
                    (bool) $regulation->currentVersion?->valid_until?->isPast() => 'Obsoleto',
                    default                                                      => 'Liberado',
                };
            });
    }
}
