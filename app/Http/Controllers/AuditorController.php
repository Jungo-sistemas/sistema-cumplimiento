<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\AuditChecklistFile;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\Regulation;
use App\Models\User;
use App\Services\AuditChecklistService;
use App\Services\AuditFindingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Lado del auditor (menú Auditorías): programar auditorías y su fecha, capturar el checklist
 * durante la llamada de Teams, finalizar (calificación + hallazgos) y revisar las causas raíz y
 * evidencias de los auditados. El auditor ve y opera las suyas; un admin consulta las de su grupo.
 */
class AuditorController extends Controller
{
    public function __construct(
        private AuditChecklistService $checklist,
        private AuditFindingService $findings,
    ) {}

    // ------------------------------------------------------------------ Inicio

    public function home(Request $request)
    {
        $user = $request->user();
        $audits = Audit::visibleToAuditor($user)->with('regulation')->where('status', '!=', Audit::STATUS_CANCELLED)->get();
        $open = $audits->where('status', Audit::STATUS_PLANNED);

        return view('auditorias.home', [
            'inProgress'   => $open->count(),
            'completed'    => $audits->where('status', Audit::STATUS_COMPLETED)->count(),
            'openFindings' => $this->findingsQuery($user)->where('status', '!=', AuditFinding::STATUS_CLOSED)->count(),
            'toReview'     => $this->findingsQuery($user)->whereIn('status', AuditFinding::NEEDS_AUDITOR)->count(),
            'pending'      => $open->sortBy(fn ($a) => $a->scheduled_at ?? Carbon::create(9999))->take(8),
        ]);
    }

    // ------------------------------------------------------------------ Lista de auditorías

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = $request->query('tab') === 'finalizadas' ? 'finalizadas' : 'ejecucion';
        $filters = array_filter($request->only(['empresa', 'estado', 'q']));

        $audits = Audit::visibleToAuditor($user)
            ->with(['regulation', 'company', 'auditees', 'auditor'])
            ->where('status', $tab === 'finalizadas' ? Audit::STATUS_COMPLETED : Audit::STATUS_PLANNED)
            ->get();

        $companies = $audits->map(fn ($a) => $this->companyName($a))->filter()->unique()->sort()->values();

        $rows = $audits
            ->when($filters['empresa'] ?? null, fn ($c, $company) => $c->filter(fn ($a) => $this->companyName($a) === $company))
            ->when($tab === 'ejecucion' ? ($filters['estado'] ?? null) : null, fn ($c, $stage) => $c->filter(fn ($a) => $a->auditorStage() === $stage))
            ->when($filters['q'] ?? null, fn ($c, $q) => $c->filter(fn ($a) => Str::contains(
                Str::lower(($a->regulation?->code ?? '') . ' ' . ($a->regulation?->name ?? '') . ' ' . $a->auditees->pluck('name')->join(' ')),
                Str::lower($q)
            )))
            ->sortBy(fn ($a) => $tab === 'finalizadas'
                ? -($a->completed_at?->timestamp ?? 0)
                : ($a->scheduled_at?->timestamp ?? PHP_INT_MAX))
            ->values();

        return view('auditorias.audits', [
            'tab'         => $tab,
            'filters'     => $filters,
            'companies'   => $companies,
            'rows'        => $rows,
            'canCreate'   => $user->isAuditor() || $user->isAdmin(),
            'regulations' => $this->regulationsFor($user),
            'people'      => $this->peopleFor($user),
            'auditors'    => $user->isAdmin() ? $this->auditorsFor($user) : collect(),
        ]);
    }

    /** Programa una auditoría: proceso, auditados y (opcional) fecha y hora. */
    public function store(Request $request)
    {
        $user = $request->user();
        $data = $request->validate([
            'regulation_id' => ['required', 'integer'],
            'auditees'      => ['required', 'array', 'min:1'],
            'auditees.*'    => ['integer'],
            'fecha'         => ['nullable', 'date_format:Y-m-d'],
            'hora'          => ['nullable', 'required_with:fecha', 'date_format:H:i'],
            'auditor_id'    => ['nullable', 'integer'],
        ], [
            'auditees.required' => 'Elige al menos a un auditado.',
            'hora.required_with' => 'Elige la hora.',
        ]);

        $regulation = $this->regulationsFor($user)->firstWhere('id', (int) $data['regulation_id']);
        abort_unless($regulation, 422, 'Proceso no válido.');

        $auditorId = $user->isAuditor() ? $user->id : (int) ($data['auditor_id'] ?? $user->id);
        if (! $user->isAuditor() && ! $this->auditorsFor($user)->contains('id', $auditorId)) {
            return back()->withErrors(['auditor_id' => 'Elige al auditor.'])->withInput();
        }

        // Nadie se audita a sí mismo.
        $auditeeIds = $this->peopleFor($user)->pluck('id')
            ->intersect(array_map('intval', $data['auditees']))
            ->reject(fn ($id) => $id === $auditorId)
            ->values();
        if ($auditeeIds->isEmpty()) {
            return back()->withErrors(['auditees' => 'Elige al menos a un auditado (que no sea el auditor).'])->withInput();
        }

        $at = ! empty($data['fecha']) ? Carbon::parse("{$data['fecha']} {$data['hora']}") : null;
        if ($at && $at->isPast()) {
            return back()->withErrors(['fecha' => 'La fecha tiene que ser futura.'])->withInput();
        }

        $audit = Audit::create([
            'group_id'      => $user->group_id,
            'company_id'    => $regulation->company_id,
            'regulation_id' => $regulation->id,
            'auditor_id'    => $auditorId,
            'planned_for'   => ($at ?? now())->copy()->startOfMonth()->toDateString(),
        ]);
        $audit->auditees()->attach($auditeeIds->all());

        if ($at) {
            $audit->schedule($at);
        }

        return redirect()->route('auditorias.audits.index')
            ->with('success', $at
                ? "Auditoría programada para el {$audit->scheduledLabel()}. " . ($audit->notice_sent_at ? 'Ya se avisó a los auditados.' : 'Los auditados recibirán el aviso ' . Audit::noticeDays() . ' días antes.')
                : 'Auditoría creada sin fecha. Ponle fecha cuando la acuerdes con el auditado.');
    }

    /** Fija o cambia el día y la hora (el aviso al auditado lo maneja Audit::schedule). */
    public function schedule(Request $request, Audit $audit)
    {
        $this->authorizeRun($request->user(), $audit);
        abort_unless($audit->status === Audit::STATUS_PLANNED, 422, 'La auditoría ya está finalizada.');

        $data = $request->validate([
            'fecha' => ['required', 'date_format:Y-m-d'],
            'hora'  => ['required', 'date_format:H:i'],
        ], ['fecha.required' => 'Elige la fecha.', 'hora.required' => 'Elige la hora.']);

        $at = Carbon::parse("{$data['fecha']} {$data['hora']}");
        if ($at->isPast()) {
            return back()->withErrors(['fecha' => 'La fecha tiene que ser futura.']);
        }

        $hadNotice = $audit->notice_sent_at !== null;
        $audit->schedule($at);

        return back()->with('success', "Fecha registrada: {$audit->scheduledLabel()}. " . match (true) {
            $hadNotice              => 'Se avisó a los auditados del cambio.',
            $audit->notice_sent_at !== null => 'Ya se avisó a los auditados.',
            default                 => 'Los auditados recibirán el aviso ' . Audit::noticeDays() . ' días antes.',
        });
    }

    // ------------------------------------------------------------------ Checklist

    public function checklist(Request $request, Audit $audit)
    {
        $user = $request->user();
        abort_unless($audit->isVisibleToAuditor($user), 403);
        if ($audit->status === Audit::STATUS_COMPLETED) {
            return redirect()->route('auditorias.audits.result', $audit);
        }

        $editable = $audit->isCapturable() && $audit->isRunBy($user);
        if ($editable) {
            $this->checklist->ensureItems($audit);
        }

        $audit->load(['regulation.currentVersion', 'company', 'auditees', 'auditor', 'checklistItems.files']);

        // Antes del día todavía no se copian: se muestra cómo quedarán, sin guardarlos.
        $items = $audit->checklistItems->isNotEmpty() ? $audit->checklistItems : $this->checklist->previewItems($audit);

        return view('auditorias.checklist', [
            'audit'    => $audit,
            'editable' => $editable,
            'canRun'   => $audit->isRunBy($user),
            'items'    => $items,
            'people'   => $this->participants($audit),
        ]);
    }

    public function saveChecklist(Request $request, Audit $audit): JsonResponse
    {
        $this->authorizeCapture($request->user(), $audit);
        $this->checklist->save($audit, $this->checklistPayload($request));

        return response()->json(['ok' => true, 'saved_at' => now()->format('H:i')]);
    }

    public function finalize(Request $request, Audit $audit): JsonResponse
    {
        $this->authorizeCapture($request->user(), $audit);
        $this->checklist->save($audit, $this->checklistPayload($request));
        $this->checklist->finalize($audit); // ValidationException → 422 con los pendientes

        return response()->json(['ok' => true, 'redirect' => route('auditorias.audits.result', $audit)]);
    }

    public function addItem(Request $request, Audit $audit): JsonResponse
    {
        $this->authorizeCapture($request->user(), $audit);
        $data = $request->validate([
            'text'     => ['required', 'string', 'max:1000'],
            'evidence' => ['nullable', 'string', 'max:1000'],
        ], ['text.required' => 'Escribe el criterio.']);

        $this->checklist->addItem($audit, $data['text'], $data['evidence'] ?? null);

        return response()->json(['ok' => true]);
    }

    public function deleteItem(Request $request, Audit $audit, AuditChecklistItem $item): JsonResponse
    {
        $this->authorizeCapture($request->user(), $audit);
        abort_unless($item->audit_id === $audit->id && $item->source === AuditChecklistItem::SOURCE_AUDITOR, 403);

        foreach ($item->files as $file) {
            Storage::disk('private')->delete($file->path);
        }
        $item->delete();

        return response()->json(['ok' => true]);
    }

    public function uploadSupport(Request $request, Audit $audit, AuditChecklistItem $item): JsonResponse
    {
        $this->authorizeCapture($request->user(), $audit);
        abort_unless($item->audit_id === $audit->id, 404);
        $request->validate(['file' => ['required', 'file', 'max:20480']]);

        $upload = $request->file('file');
        $file = $item->files()->create([
            'path'          => $upload->store("audits/{$audit->id}/checklist/{$item->id}", 'private'),
            'original_name' => $request->input('name') ?: $upload->getClientOriginalName(),
            'mime_type'     => $upload->getClientMimeType(),
            'size'          => $upload->getSize() ?: 0,
        ]);

        return response()->json([
            'id'   => $file->id,
            'name' => $file->original_name,
            'url'  => route('auditorias.support.download', $file),
        ]);
    }

    public function deleteSupport(Request $request, AuditChecklistFile $file): JsonResponse
    {
        $this->authorizeCapture($request->user(), $file->item->audit);
        Storage::disk('private')->delete($file->path);
        $file->delete();

        return response()->json(['ok' => true]);
    }

    public function downloadSupport(Request $request, AuditChecklistFile $file)
    {
        abort_unless($file->item->audit->isVisibleToAuditor($request->user()), 403);
        abort_unless(Storage::disk('private')->exists($file->path), 404);

        return Storage::disk('private')->download($file->path, $file->original_name);
    }

    // ------------------------------------------------------------------ Resultado

    public function result(Request $request, Audit $audit)
    {
        abort_unless($audit->isVisibleToAuditor($request->user()), 403);
        if ($audit->status !== Audit::STATUS_COMPLETED) {
            return redirect()->route('auditorias.audits.checklist', $audit);
        }

        $audit->load(['regulation', 'company', 'auditor', 'auditees', 'checklistItems.files', 'findings.responsible']);

        return view('auditorias.result', [
            'audit'    => $audit,
            'items'    => $audit->checklistItems,
            'people'   => $this->participants($audit),
            'findings' => $audit->findings->map(fn ($f) => $this->findingPayload($f))->values(),
        ]);
    }

    // ------------------------------------------------------------------ Hallazgos

    public const FINDING_TABS = [
        'todos'      => 'Todos',
        'causas'     => 'Causas raíz por revisar',
        'evidencias' => 'Evidencias por revisar',
    ];

    public function findings(Request $request)
    {
        $user = $request->user();
        $tab = array_key_exists($request->query('tab'), self::FINDING_TABS) ? $request->query('tab') : 'todos';
        $filters = array_filter($request->only(['empresa', 'seguimiento', 'q']));

        $findings = $this->findingsQuery($user)
            ->with(['audit.regulation', 'audit.company', 'audit.auditor', 'responsible'])
            ->when($tab === 'causas', fn ($q) => $q->where('status', AuditFinding::STATUS_ROOT_CAUSE_REVIEW))
            ->when($tab === 'evidencias', fn ($q) => $q->where('status', AuditFinding::STATUS_EVIDENCE_REVIEW))
            ->when($tab === 'todos' ? ($filters['seguimiento'] ?? null) : null, fn ($q, $status) => $q->where('status', $status))
            ->latest('id')
            ->get();

        $companies = $findings->map(fn ($f) => $this->companyName($f->audit))->filter()->unique()->sort()->values();

        $rows = $findings
            ->when($filters['empresa'] ?? null, fn ($c, $company) => $c->filter(fn ($f) => $this->companyName($f->audit) === $company))
            ->when($filters['q'] ?? null, fn ($c, $q) => $c->filter(fn ($f) => Str::contains(
                Str::lower($this->findingCode($f) . ' ' . $f->point . ' ' . ($f->responsible?->name ?? '')), Str::lower($q)
            )))
            ->values();

        return view('auditorias.findings', [
            'tab'       => $tab,
            'filters'   => $filters,
            'companies' => $companies,
            'rows'      => $rows,
            'payloads'  => $rows->mapWithKeys(fn ($f) => [$f->id => $this->findingPayload($f)]),
        ]);
    }

    public function reviewFinding(Request $request, AuditFinding $finding)
    {
        $user = $request->user();
        abort_unless($finding->audit->isRunBy($user), 403, 'Solo el auditor asignado revisa el hallazgo.');

        $data = $request->validate([
            'decision' => ['required', 'in:aprobar,rechazar'],
            'comment'  => ['nullable', 'string', 'max:2000', 'required_if:decision,rechazar'],
        ], ['comment.required_if' => 'Escribe el motivo del rechazo.']);

        $wasRootCause = $finding->status === AuditFinding::STATUS_ROOT_CAUSE_REVIEW;

        try {
            $this->findings->review($finding, $data['decision'] === 'aprobar', $data['comment'] ?? null, $user);
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(['comment' => $e->getMessage()]);
        }

        $message = match (true) {
            $wasRootCause && $data['decision'] === 'aprobar' => 'Aprobaste la causa raíz y las acciones: el auditado ya puede subir la evidencia del seguimiento.',
            $wasRootCause                                    => 'Devolviste la causa raíz: el auditado debe corregirla.',
            $data['decision'] === 'aprobar'                  => 'Aprobaste la evidencia: el hallazgo quedó cerrado.',
            default                                          => 'Rechazaste la evidencia: el auditado debe subir otra.',
        };

        return back()->with('success', "{$this->findingCode($finding)}: {$message}");
    }

    // ------------------------------------------------------------------ Apoyo

    /** Hallazgos de las auditorías que ve el usuario. */
    private function findingsQuery(User $user): Builder
    {
        return AuditFinding::query()->whereHas('audit', fn ($q) => $q->visibleToAuditor($user));
    }

    public function findingCode(AuditFinding $finding): string
    {
        return 'HAL-' . str_pad((string) $finding->id, 3, '0', STR_PAD_LEFT);
    }

    /** Lo que la ventana de detalle necesita de un hallazgo. */
    private function findingPayload(AuditFinding $finding): array
    {
        $finding->loadMissing(['submissions.files', 'submissions.user', 'events.user', 'audit.regulation', 'responsible', 'checklistItem']);
        $rootCause = $finding->submissions->where('kind', 'causa')->sortByDesc('id')->first();
        $evidence = $finding->submissions->where('kind', 'evidencia')->sortByDesc('id')->first();

        return [
            'id'          => $finding->id,
            'codigo'      => $this->findingCode($finding),
            'titulo'      => $finding->point,
            'descripcion' => $finding->description,
            'criterio'    => $finding->checklistItem?->text,
            'proceso'     => trim(($finding->audit->regulation?->code ? $finding->audit->regulation->code . ' — ' : '') . ($finding->audit->regulation?->name ?? '')),
            'empresa'     => $this->companyName($finding->audit),
            'responsable' => $finding->responsible?->name ?? '—',
            'severidad'   => $finding->severityLabel(),
            'critico'     => $finding->isCritical(),
            'status'      => $finding->status,
            'seguimiento' => self::trackingLabel($finding),
            'plazo'       => $finding->due_at?->locale('es')->isoFormat('DD MMM YYYY'),
            'vencido'     => $finding->isOverdue(),
            'revision'    => match ($finding->status) {
                AuditFinding::STATUS_ROOT_CAUSE_REVIEW => 'causa',
                AuditFinding::STATUS_EVIDENCE_REVIEW   => 'evidencia',
                default                                => null,
            },
            'causa'     => $rootCause?->root_cause,
            'acciones'  => $rootCause?->actions,
            'causaArchivos' => $rootCause ? $this->filesPayload($rootCause) : [],
            'evidencias'    => $evidence ? $this->filesPayload($evidence) : [],
            'evidenciaComentario' => $evidence?->comment,
            'reviewUrl' => route('auditorias.findings.review', $finding),
            'puedeRevisar' => request()->user() && $finding->audit->isRunBy(request()->user()),
            'auditor'   => $finding->audit->auditor?->name ?? 'sin asignar',
            'historial' => $finding->events->sortBy('id')->map(fn ($e) => $e->created_at->locale('es')->isoFormat('DD MMM YYYY HH:mm')
                . ' · ' . ($e->user?->name ?? 'Sistema') . ': ' . $e->label() . ($e->comment ? ' — ' . $e->comment : ''))->values(),
        ];
    }

    private function filesPayload($submission): array
    {
        return $submission->files->map(fn ($file) => [
            'name' => $file->original_name,
            'url'  => route('mi-espacio.findings.file', $file),
        ])->values()->all();
    }

    /** Seguimiento como lo ve el auditor. */
    public static function trackingLabel(AuditFinding $finding): string
    {
        return match ($finding->status) {
            AuditFinding::STATUS_ROOT_CAUSE_PENDING  => 'Con el auditado',
            AuditFinding::STATUS_ROOT_CAUSE_REVIEW   => 'Causa raíz por revisar',
            AuditFinding::STATUS_ROOT_CAUSE_REJECTED => 'Causa raíz devuelta',
            AuditFinding::STATUS_EVIDENCE_PENDING    => 'Sin evidencia',
            AuditFinding::STATUS_EVIDENCE_REVIEW     => 'Evidencia por revisar',
            AuditFinding::STATUS_EVIDENCE_REJECTED   => 'Evidencia rechazada',
            AuditFinding::STATUS_CLOSED              => 'Cerrado',
            default                                  => $finding->status,
        };
    }

    public const SEVERITY_COLORS = [
        'Crítico'    => 'bg-red-50 text-red-700',
        'No crítico' => 'bg-gray-100 text-gray-700',
    ];

    public const TRACKING_COLORS = [
        'Con el auditado'        => 'bg-amber-50 text-amber-700',
        'Causa raíz por revisar' => 'bg-brand-50 text-brand',
        'Causa raíz devuelta'    => 'bg-red-50 text-red-700',
        'Sin evidencia'          => 'bg-amber-50 text-amber-700',
        'Evidencia por revisar'  => 'bg-brand-50 text-brand',
        'Evidencia rechazada'    => 'bg-red-50 text-red-700',
        'Cerrado'                => 'bg-green-50 text-green-700',
    ];

    public const TRACKING_FILTERS = [
        AuditFinding::STATUS_ROOT_CAUSE_PENDING  => 'Con el auditado',
        AuditFinding::STATUS_ROOT_CAUSE_REVIEW   => 'Causa raíz por revisar',
        AuditFinding::STATUS_ROOT_CAUSE_REJECTED => 'Causa raíz devuelta',
        AuditFinding::STATUS_EVIDENCE_PENDING    => 'Sin evidencia',
        AuditFinding::STATUS_EVIDENCE_REVIEW     => 'Evidencia por revisar',
        AuditFinding::STATUS_EVIDENCE_REJECTED   => 'Evidencia rechazada',
        AuditFinding::STATUS_CLOSED              => 'Cerrado',
    ];

    /** Auditor y auditados, para la lista de asistencia y el responsable de cada hallazgo. */
    private function participants(Audit $audit): array
    {
        $people = [];
        if ($audit->auditor) {
            $people[] = ['id' => $audit->auditor->id, 'nombre' => $audit->auditor->name, 'equipo' => 'auditor', 'puesto' => 'Auditor'];
        }
        foreach ($audit->auditees as $auditee) {
            $people[] = ['id' => $auditee->id, 'nombre' => $auditee->name, 'equipo' => 'auditado', 'puesto' => $auditee->position ?? 'Auditado'];
        }

        return $people;
    }

    private function companyName(?Audit $audit): ?string
    {
        return $audit?->company?->procesos_name ?? $audit?->company?->name;
    }

    private function regulationsFor(User $user)
    {
        return Regulation::query()
            ->with('company')
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->where('is_annex', false)
            ->whereNull('obsoleted_at')
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'company_id', 'group_id']);
    }

    private function peopleFor(User $user)
    {
        return User::where('group_id', $user->group_id)->where('status', 'active')->orderBy('name')->get(['id', 'name', 'email']);
    }

    /** A quién puede asignar un admin: los auditores del grupo o él mismo. */
    private function auditorsFor(User $user)
    {
        return User::where('group_id', $user->group_id)->where('status', 'active')
            ->where(fn ($q) => $q->whereHas('role', fn ($q) => $q->where('slug', 'auditor'))->orWhere('id', $user->id))
            ->orderBy('name')->get(['id', 'name']);
    }

    private function authorizeRun(User $user, Audit $audit): void
    {
        abort_unless($audit->isRunBy($user), 403, 'Solo el auditor asignado puede hacerlo.');
    }

    private function authorizeCapture(User $user, Audit $audit): void
    {
        $this->authorizeRun($user, $audit);
        abort_unless($audit->isCapturable(), 422, 'El checklist solo se captura desde el día de la auditoría y antes de finalizarla.');
    }

    private function checklistPayload(Request $request): array
    {
        return $request->validate([
            'items'                         => ['array'],
            'items.*.id'                    => ['required', 'integer'],
            'items.*.result'                => ['nullable', 'string'],
            'items.*.observations'          => ['nullable', 'string', 'max:5000'],
            'items.*.findings'              => ['nullable', 'array'],
            'attendance'                    => ['nullable', 'array'],
            'attendance.*'                  => ['integer'],
            'conclusions'                   => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
