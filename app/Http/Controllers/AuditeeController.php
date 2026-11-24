<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditFindingFile;
use App\Models\AuditFindingSubmission;
use App\Models\Company;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use App\Services\AuditFindingService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * "Vista de auditado" (Mi espacio): lo que ve cualquier persona del grupo sobre su propio trabajo —
 * sus documentos, auditorías y actividades. Los admins llegan aquí con el cambio
 * "Gestión / Vista de auditado" del encabezado; los demás roles solo tienen esta vista.
 *
 * Mis auditorías es solo informativa (mes, fecha, calificación). Mis hallazgos son tareas: el
 * responsable sube evidencia y el auditor la acepta o la rechaza con motivo.
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

        $findingsToAttend = AuditFinding::forResponsible($user)
            ->whereIn('status', AuditFinding::NEEDS_AUDITEE)
            ->with('audit.regulation')
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderBy('id')
            ->get();
        $activeAudits = Audit::activeForAuditee($user)->with('regulation')
            ->orderBy('scheduled_at')->get();

        // Siguiente por atender: primero los hallazgos (son tareas), luego las próximas auditorías.
        $nextItems = $findingsToAttend->map(fn (AuditFinding $f) => [
            'tag'   => match ($f->status) {
                AuditFinding::STATUS_ROOT_CAUSE_REJECTED => 'Causa raíz devuelta',
                AuditFinding::STATUS_EVIDENCE_REJECTED   => 'Evidencia rechazada',
                AuditFinding::STATUS_EVIDENCE_PENDING    => 'Seguimiento',
                default                                  => $f->isCritical() ? 'Hallazgo crítico' : 'Hallazgo',
            },
            'tone'  => in_array($f->status, [AuditFinding::STATUS_ROOT_CAUSE_REJECTED, AuditFinding::STATUS_EVIDENCE_REJECTED], true) || $f->isOverdue() || $f->isCritical() ? 'red' : 'amber',
            'title' => $f->point,
            'meta'  => trim(($f->audit->regulation?->code ? $f->audit->regulation->code . ' · ' : '') . ($f->due_at ? 'Fecha límite ' . $f->due_at->format('d/m/Y') : 'Fecha límite por definir')),
            'url'   => route('mi-espacio.findings.show', $f),
            'cta'   => $f->needsRootCause() ? 'Escribir causa raíz' : 'Subir evidencia',
        ])->concat($activeAudits->map(fn (Audit $a) => [
            'tag'   => 'Auditoría',
            'tone'  => 'brand',
            'title' => $a->regulation?->name ?? 'Auditoría',
            'meta'  => trim(($a->regulation?->code ? $a->regulation->code . ' · ' : '') . ($a->scheduledLabel() ?? 'Programada para ' . $a->plannedMonthLabel())),
            'url'   => route('mi-espacio.audits'),
            'cta'   => 'Ver auditoría',
        ]))->take(5);

        return view('mi-espacio.home', [
            'activeAuditsCount'  => $activeAudits->count(),
            'findingsCount'      => $findingsToAttend->count(),
            'releasedDocsCount'  => $this->decorate($documents, $mentionedIds)->where('auditee_status', 'Liberado')->count(),
            'nextItems'          => $nextItems,
        ]);
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

    /**
     * Mis auditorías: solo informativa. Activas = programadas que ya se le avisaron (el auditor fija
     * la fecha y el aviso llega unos días antes; antes no se ven), con su fecha y hora.
     * Historial = realizadas o canceladas. El checklist se consulta en el proceso mismo.
     */
    public function audits(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'historial' ? 'historial' : 'activas';
        $companies = $this->companiesFor($user);

        $audits = Audit::query()
            ->forAuditee($user)
            ->noticeStarted()
            ->when($tab === 'activas',
                fn ($q) => $q->where('status', Audit::STATUS_PLANNED),
                fn ($q) => $q->whereIn('status', [Audit::STATUS_COMPLETED, Audit::STATUS_CANCELLED]))
            ->when($request->integer('company_id'), fn ($q, $id) => $q->where('company_id', $id))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->whereHas('regulation', fn ($r) => $r->withoutGlobalScopes()
                    ->where(fn ($w) => $w->whereRaw('LOWER(code) LIKE ?', [$like])->orWhereRaw('LOWER(name) LIKE ?', [$like])));
            })
            ->with(['regulation.publishedVersion', 'company', 'auditor:id,name,email'])
            ->withCount([
                'findings',
                'findings as open_findings_count' => fn ($q) => $q->where('status', '!=', AuditFinding::STATUS_CLOSED),
                'findings as my_open_findings_count' => fn ($q) => $q->where('status', '!=', AuditFinding::STATUS_CLOSED)->where('responsible_id', $user->id),
            ])
            ->when($tab === 'activas',
                fn ($q) => $q->orderBy('scheduled_at'),
                fn ($q) => $q->orderByDesc('completed_at')->orderByDesc('scheduled_at'))
            ->get();

        return view('mi-espacio.audits', [
            'tab'       => $tab,
            'companies' => $companies,
            'audits'    => $audits,
        ]);
    }

    /**
     * Mis hallazgos: las tareas que el auditor le dejó como responsable. Por atender = todo lo no
     * cerrado (incluye "en revisión", que espera al auditor); Concluidas = cerrados.
     */
    public function findings(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'concluidas' ? 'concluidas' : 'por-atender';

        $findings = AuditFinding::forResponsible($user)
            ->when($tab === 'por-atender',
                fn ($q) => $q->open(),
                fn ($q) => $q->where('status', AuditFinding::STATUS_CLOSED))
            ->when(trim((string) $request->query('q')), function ($q, $term) {
                $like = '%' . mb_strtolower($term) . '%';
                $q->where(fn ($w) => $w->whereRaw('LOWER(point) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(description) LIKE ?', [$like])
                    ->orWhereHas('audit.regulation', fn ($r) => $r->withoutGlobalScopes()
                        ->where(fn ($x) => $x->whereRaw('LOWER(code) LIKE ?', [$like])->orWhereRaw('LOWER(name) LIKE ?', [$like]))));
            })
            ->with(['audit.regulation', 'audit.auditor:id,name', 'latestSubmission'])
            ->when($tab === 'por-atender',
                // Primero lo que le toca a él, luego lo que espera al auditor.
                fn ($q) => $q->orderByRaw("CASE WHEN status IN ('causa_en_revision', 'evidencia_en_revision') THEN 1 ELSE 0 END")
                    ->orderByRaw('due_at IS NULL')->orderBy('due_at')->orderBy('id'),
                fn ($q) => $q->orderByDesc('closed_at'))
            ->get();

        return view('mi-espacio.findings', [
            'tab'      => $tab,
            'findings' => $findings,
        ]);
    }

    public function showFinding(Request $request, AuditFinding $finding)
    {
        $this->authorizeFinding($request->user(), $finding);

        $finding->load(['audit.regulation.publishedVersion', 'audit.company', 'audit.auditor:id,name,email', 'responsible:id,name', 'submissions.files', 'submissions.user:id,name', 'events.user:id,name']);
        $isResponsible = $finding->responsible_id === $request->user()->id;

        return view('mi-espacio.finding', [
            'finding'           => $finding,
            'isResponsible'     => $isResponsible,
            'canSendRootCause'  => $isResponsible && $finding->needsRootCause(),
            'canSubmit'         => $isResponsible && $finding->needsEvidence(),
            // Lo último que devolvió el auditor, si el hallazgo está esperando la corrección.
            'lastReject'        => in_array($finding->status, [AuditFinding::STATUS_ROOT_CAUSE_REJECTED, AuditFinding::STATUS_EVIDENCE_REJECTED], true)
                ? $finding->submissions->first() : null,
            'approvedRootCause' => $finding->submissions->first(fn ($s) => $s->isRootCause() && $s->status === AuditFindingSubmission::STATUS_ACCEPTED),
        ]);
    }

    public function submitRootCause(Request $request, AuditFinding $finding, AuditFindingService $service)
    {
        $user = $request->user();
        abort_unless($finding->responsible_id === $user->id, 403);

        if (! $finding->needsRootCause()) {
            return redirect()->route('mi-espacio.findings.show', $finding)
                ->with('warning', 'Este hallazgo ya no está esperando la causa raíz.');
        }

        $data = $request->validate([
            'root_cause' => ['required', 'string', 'max:5000'],
            'actions'    => ['required', 'string', 'max:5000'],
            'files'      => ['nullable', 'array', 'max:' . AuditFinding::MAX_FILES],
            'files.*'    => ['file', 'max:20480', 'mimes:' . self::EVIDENCE_MIMES],
        ], [
            'root_cause.required' => 'Escribe la causa raíz: por qué pasó, no solo qué pasó.',
            'actions.required'    => 'Escribe las acciones para corregirlo.',
            'files.max'           => 'Puedes adjuntar como máximo ' . AuditFinding::MAX_FILES . ' archivos.',
            'files.*.max'         => 'Cada archivo puede pesar como máximo 20 MB.',
            'files.*.mimes'       => 'Formato no permitido. Usa PDF, Word, Excel, PowerPoint o imagen (JPG, PNG, HEIC).',
        ]);

        $service->submitRootCause($finding, $user, $data['root_cause'], $data['actions'], $data['files'] ?? []);

        return redirect()->route('mi-espacio.findings.show', $finding)
            ->with('success', 'Causa raíz enviada. El auditor la revisará y te avisaremos si la aprueba o tiene observaciones.');
    }

    public function submitEvidence(Request $request, AuditFinding $finding, AuditFindingService $service)
    {
        $user = $request->user();
        abort_unless($finding->responsible_id === $user->id, 403);

        if (! $finding->needsEvidence()) {
            return redirect()->route('mi-espacio.findings.show', $finding)
                ->with('warning', 'Este hallazgo ya no está esperando evidencia.');
        }

        $data = $request->validate([
            'files'   => ['required', 'array', 'min:1', 'max:' . AuditFinding::MAX_FILES],
            'files.*' => ['file', 'max:20480', 'mimes:' . self::EVIDENCE_MIMES],
            'comment' => ['nullable', 'string', 'max:2000'],
        ], [
            'files.required' => 'Agrega al menos un archivo de evidencia.',
            'files.max'      => 'Puedes subir como máximo ' . AuditFinding::MAX_FILES . ' archivos.',
            'files.*.max'    => 'Cada archivo puede pesar como máximo 20 MB.',
            'files.*.mimes'  => 'Formato no permitido. Usa PDF, Word, Excel, PowerPoint o imagen (JPG, PNG, HEIC).',
        ]);

        $service->submitEvidence($finding, $user, $data['files'], $data['comment'] ?? null);

        return redirect()->route('mi-espacio.findings.show', $finding)
            ->with('success', 'Evidencia enviada. El auditor la revisará y te avisaremos el resultado.');
    }

    public function downloadFindingFile(Request $request, AuditFindingFile $file)
    {
        $this->authorizeFinding($request->user(), $file->submission->finding);
        abort_unless(Storage::disk('private')->exists($file->path), 404);

        return Storage::disk('private')->download($file->path, $file->original_name);
    }

    private const EVIDENCE_MIMES = 'pdf,doc,docx,xls,xlsx,ppt,pptx,jpg,jpeg,png,webp,heic,heif';

    /**
     * Ve el hallazgo su responsable, el auditor de la auditoría, cualquiera de sus auditados, los
     * responsables del procedimiento (lo acompañan en la causa raíz) y quien recibe los avisos de
     * auditorías del grupo (le llegan los críticos y los vencidos).
     */
    private function authorizeFinding(User $user, AuditFinding $finding): void
    {
        $audit = $finding->audit;

        abort_unless(
            $finding->responsible_id === $user->id
            || $audit->auditor_id === $user->id
            || $audit->auditees()->where('users.id', $user->id)->exists()
            || ($audit->regulation && $audit->regulation->responsables()->where('users.id', $user->id)->exists())
            || ($user->receives_audit_alerts && $user->group_id === $audit->group_id)
            || ($user->isAdmin() && $user->group_id === $audit->group_id),
            403
        );
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
