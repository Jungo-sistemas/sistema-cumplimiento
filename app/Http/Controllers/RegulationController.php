<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationShare;
use App\Models\RegulationVersion;
use App\Models\User;
use App\Services\AiProcedureGenerationService;
use App\Services\ApprovalFlowService;
use App\Services\RegulationDocxHeaderBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as WordHtml;

class RegulationController extends Controller
{
    public function __construct(private readonly ApprovalFlowService $flowService) {}
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)->where('show_in_processes', true)->where('otras', false)->orderBy('name')->get()
            : collect();

        // Group user with no company selected AND no search AND not in report mode → company card grid
        if ($user->hasGroupScope() && ! $selectedCompanyId && ! $request->filled('q') && ! $request->boolean('report')) {
            $companiesQuery = Company::where('group_id', $user->group_id)
                ->where('show_in_processes', true)
                ->where('otras', false)
                ->withCount(['regulations' => fn ($q) => $q->where('is_active', true)->where('is_annex', false)]);

            return view('processes.index', [
                'cardView'            => true,
                'showAnnexes'         => false,
                'companiesWithCounts' => $companiesQuery->orderBy('name')->get(),
                'companies'           => $companies,
                'selectedCompanyId'   => null,
                'processTypes'        => collect(),
                'documentTypes'       => Regulation::DOCUMENT_TYPES,
                'regulations'         => collect(),
                'pendingApprovalIds'  => collect(),
                'globalSearch'        => false,
                'usersByPosition'     => collect(),
                'positionLabels'      => collect(),
                'positionSortOrders'  => collect(),
                'flowDefinitions'     => ApprovalFlowService::getAllFlows(),
            ]);
        }

        // Table view
        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        // Por defecto solo se listan procesos normales — los anexos (formatos, tabuladores, etc.
        // que existen sobre todo para ser referenciados por otros documentos, no como procedimientos
        // en sí) se ven aparte con ?anexos=1, para no mezclarlos en el listado principal.
        $showAnnexes = $request->boolean('anexos');

        $query = Regulation::with(['processType', 'company', 'currentVersion', 'annexes'])
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->where('is_annex', $showAnnexes);

        if ($selectedCompanyId) {
            $query->where('company_id', $selectedCompanyId);
        } elseif (! $user->hasGroupScope()) {
            $query->where('company_id', $user->company_id);
        }

        if ($request->filled('process_type_id')) {
            $query->where('process_type_id', $request->process_type_id);
        }

        if ($request->filled('document_type')) {
            $query->where('document_type', $request->document_type);
        }

        if ($request->filled('q')) {
            $search = '%' . Str::upper($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        // Búsqueda global: sin empresa seleccionada + búsqueda, o modo reporte (todas las empresas)
        $globalSearch = $user->hasGroupScope() && ! $selectedCompanyId
            && ($request->filled('q') || $request->boolean('report'));

        $query->orderBy('code')->orderBy('name');

        // El modo reporte/búsqueda global necesita la lista completa (selecciona ids de todas las
        // empresas para exportar a Excel, ver reportTable() en la vista) — el listado normal de una
        // empresa sí se pagina para no volcar cientos de procedimientos en una sola pantalla.
        $regulations = $globalSearch ? $query->get() : $query->paginate(10)->withQueryString();

        // IDs de reglamentos donde el usuario autenticado tiene aprobación pendiente
        $pendingApprovalIds = \App\Models\RegulationApproval::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereIn('regulation_id', $regulations->pluck('id'))
            ->pluck('regulation_id')
            ->flip(); // flip para lookup O(1)

        // Users grouped by position slug for flow assignment modal (admin only)
        $positions = JobPosition::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $positionLabels     = $positions->pluck('name', 'slug');
        $positionSortOrders = $positions->pluck('sort_order', 'slug');

        $usersByPosition = $user->isAdmin()
            ? JobPosition::where('group_id', $user->group_id)
                ->where('is_active', true)
                ->with(['users' => fn ($q) => $q->select('users.id', 'users.name', 'users.email')->orderBy('users.name')])
                ->get()
                ->mapWithKeys(fn ($pos) => [
                    $pos->slug => $pos->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
                ])
            : collect();

        return view('processes.index', [
            'cardView'            => false,
            'showAnnexes'         => $showAnnexes,
            'companiesWithCounts' => collect(),
            'regulations'         => $regulations,
            'processTypes'        => $processTypes,
            'companies'           => $companies,
            'selectedCompanyId'   => $selectedCompanyId,
            'documentTypes'       => Regulation::DOCUMENT_TYPES,
            'pendingApprovalIds'  => $pendingApprovalIds,
            'globalSearch'        => $globalSearch,
            'usersByPosition'     => $usersByPosition,
            'positionLabels'      => $positionLabels,
            'positionSortOrders'  => $positionSortOrders,
            'flowDefinitions'     => ApprovalFlowService::getAllFlows(),
        ]);
    }

    public function create(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        session()->forget(self::AI_DRAFT_SESSION_KEY); // empezar limpio; evita confusión con una vista previa abandonada

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)->where('show_in_processes', true)->where('otras', false)->orderBy('name')->get()
            : collect();

        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('processes.create', [
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'processTypes'      => $processTypes,
            'documentTypes'     => Regulation::DOCUMENT_TYPES,
            'impactLevels'      => Regulation::IMPACT_LEVELS,
        ]);
    }

    public function show(Regulation $regulation)
    {
        $user = auth()->user();

        // Acceso: empresa propia, aprobación pendiente asignada, o documento compartido directamente
        $pendingApprovalForUser = $this->flowService->getPendingApprovalForUser($regulation, $user->id);
        $hasShare = RegulationShare::where('regulation_id', $regulation->id)
            ->where('user_id', $user->id)
            ->exists();
        abort_unless(
            $user->canAccessCompany($regulation->company) || $pendingApprovalForUser !== null || $hasShare,
            403
        );

        $regulation->load(['processType', 'company', 'currentVersion', 'creator']);

        $versionHistory = $regulation->versions()->with('uploader')->get();

        $currentVersion = $versionHistory->firstWhere('is_current', true);

        $users = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $positions = JobPosition::where('group_id', $regulation->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $positionLabels     = $positions->pluck('name', 'slug');
        $positionSortOrders = $positions->pluck('sort_order', 'slug');

        $usersByPosition = $user->isAdmin()
            ? JobPosition::where('group_id', $regulation->group_id)
                ->where('is_active', true)
                ->with(['users' => fn ($q) => $q->select('users.id', 'users.name', 'users.email')->orderBy('users.name')])
                ->get()
                ->mapWithKeys(fn ($pos) => [
                    $pos->slug => $pos->users->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'email' => $u->email])->values(),
                ])
            : collect();

        $shareRecipients = \App\Models\RegulationShare::with(['recipient', 'sender'])
            ->where('regulation_id', $regulation->id)
            ->orderByDesc('sent_at')
            ->get();

        $shareableUsers = $regulation->approval_status === 'approved'
            ? User::where('group_id', $regulation->group_id)
                ->where('id', '!=', $user->id)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
            : collect();

        // Lock info for the edit button
        $editLock = $this->buildEditLock($currentVersion, $user);

        return view('processes.show', [
            'regulation'             => $regulation,
            'versionHistory'         => $versionHistory,
            'currentVersion'         => $currentVersion,
            'users'                  => $users,
            'documentTypes'          => Regulation::DOCUMENT_TYPES,
            'pendingApprovalForUser' => $pendingApprovalForUser,
            'usersByPosition'        => $usersByPosition,
            'positionLabels'         => $positionLabels,
            'positionSortOrders'     => $positionSortOrders,
            'flowDefinitions'        => ApprovalFlowService::getAllFlows(),
            'shareRecipients'        => $shareRecipients,
            'shareableUsers'         => $shareableUsers,
            'editLock'               => $editLock,
        ]);
    }

    public function flowView(Regulation $regulation)
    {
        $user = auth()->user();

        $pendingApprovalForUser = $this->flowService->getPendingApprovalForUser($regulation, $user->id);
        abort_unless(
            $user->canAccessCompany($regulation->company) || $pendingApprovalForUser !== null,
            403
        );
        abort_unless($regulation->impact_level, 404);

        $regulation->load(['processType', 'company']);

        $approvals = $regulation->approvals()
            ->with(['user', 'jobPosition'])
            ->orderBy('step_number')
            ->orderBy('id')
            ->get()
            ->groupBy('step_number');

        $currentVersion = $regulation->versions()->where('is_current', true)->first();
        $editLock       = $this->buildEditLock($currentVersion, $user);

        return view('processes.flow', compact('regulation', 'approvals', 'pendingApprovalForUser', 'currentVersion', 'editLock'));
    }

    /**
     * Mismo criterio usado por show() y flowView() para decidir si el botón "Editar" de la
     * versión actual debe estar habilitado, bloqueado por otro usuario, o retomando un borrador.
     */
    private function buildEditLock(?RegulationVersion $currentVersion, User $user): ?array
    {
        if (! $currentVersion || ! $currentVersion->editing_by || ! $currentVersion->editing_expires_at?->isFuture()) {
            return null;
        }

        $lockUser = User::find($currentVersion->editing_by);

        return [
            'by_me'     => $currentVersion->editing_by === $user->id,
            'user_name' => $lockUser?->name ?? 'otro usuario',
            'expires'   => $currentVersion->editing_expires_at->format('H:i'),
            'has_draft' => $currentVersion->editing_by === $user->id && $currentVersion->draft_html !== null,
            'draft_at'  => $currentVersion->draft_saved_at?->format('H:i'),
        ];
    }

    private const AI_DRAFT_SESSION_KEY = 'ai_procedure_draft';

    private function wizardValidationRules(): array
    {
        return [
            'company_id'                  => ['required', 'exists:companies,id'],
            'process_type_id'             => ['required', 'exists:process_types,id'],
            'document_type'               => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'is_annex'                    => ['sometimes', 'boolean'],
            'impact_level'                => ['nullable', 'string', 'in:' . implode(',', array_keys(Regulation::IMPACT_LEVELS))],
            'nombre'                      => ['required', 'string', 'max:255'],
            'codigo'                      => ['nullable', 'string', 'max:50'],
            'quien_elabora'               => ['required', 'string', 'max:255'],
            'quien_aprueba'               => ['required', 'string', 'max:255'],
            'fecha_vigencia'              => ['required', 'date'],
            'motivo_creacion'             => ['required', 'string'],
            'problema_resuelve'           => ['required', 'string'],
            'resultado_esperado'          => ['required', 'string'],
            'areas_aplica'                => ['required', 'string'],
            'fuera_alcance'               => ['required', 'string'],
            'indicador_proceso'           => ['required', 'string'],
            'indicador_resultado'         => ['required', 'string'],
            'meta_valor'                  => ['required', 'string', 'max:255'],
            'frecuencia_medicion'         => ['required', 'string', 'max:255'],
            'que_detona'                  => ['required', 'string'],
            'lista_actividades'           => ['required', 'string'],
            'areas_ejecutan'              => ['required', 'string'],
            'decisiones_control'          => ['required', 'string'],
            'documentos_usados'           => ['required', 'string'],
            'resultado_entregable'        => ['required', 'string'],
            'areas_roles_mapa'            => ['required', 'string', 'max:255'],
            'procedimientos_relacionados' => ['required', 'string'],
            'proveedores_clientes'        => ['required', 'string'],
            'terminos_abreviaturas'       => ['required', 'string'],
            'riesgos_errores'             => ['required', 'string'],
            'requerimientos_normativos'   => ['required', 'string'],
        ];
    }

    /**
     * Paso 1: valida el wizard, genera el procedimiento con IA y lo deja en sesión
     * (todavía no se crea nada en BD) para mostrarlo en la vista previa.
     */
    public function previewGenerate(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createRegulation', $this->wizardValidationRules());

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        $wizardDetails = collect($data)
            ->except(['company_id', 'process_type_id', 'document_type', 'is_annex', 'nombre', 'codigo', 'impact_level'])
            ->toArray();

        try {
            set_time_limit(240); // la generación con IA puede tardar 1-3 minutos según el modelo
            $ai = app(AiProcedureGenerationService::class)->generate(
                $wizardDetails,
                companyName: $company->name,
                documentName: Str::upper($data['nombre']),
                documentCode: $data['codigo'] ? Str::upper($data['codigo']) : ''
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'ai' => 'No se pudo generar el documento con IA: ' . $e->getMessage(),
            ]);
        }

        session([self::AI_DRAFT_SESSION_KEY => [
            'mode'          => 'create',
            'regulation_id' => null,
            'meta'          => $data,
            'wizard'        => $wizardDetails,
            'ai'            => $ai,
            'revisions'     => 0,
            'company_name'  => $company->name,
        ]]);

        return redirect()->route('processes.preview.show');
    }

    /**
     * Paso 2: muestra el documento generado (o su última revisión) para que el
     * usuario decida si lo acepta o pide cambios, antes de tocar nada en BD.
     */
    public function previewShow()
    {
        $user = auth()->user();
        $draft = session(self::AI_DRAFT_SESSION_KEY);

        if (! $draft) {
            return redirect()->route('processes.create')
                ->with('error', 'No hay una vista previa activa. Genera un procedimiento desde el wizard primero.');
        }

        $isEdit = ($draft['mode'] ?? 'create') === 'edit';
        $regulation = $isEdit ? Regulation::findOrFail($draft['regulation_id']) : null;
        $company = $isEdit ? $regulation->company : Company::findOrFail($draft['meta']['company_id']);

        abort_unless($user->canAccessCompany($company), 403);

        // Mismo número que verá renderHtmlToDocx() al confirmar — así el encabezado de la
        // vista previa (ver abajo) muestra la versión real, no un valor a adivinar.
        $headerVersion = $isEdit
            ? sprintf('%02d', ($regulation->versions()->max('version_number') ?? 0) + 1)
            : '01';

        return view('processes.preview', [
            'draft'         => $draft,
            'company'       => $company,
            'headerVersion' => $headerVersion,
        ]);
    }

    /**
     * Pide a la IA que ajuste el resultado actual según el feedback del usuario,
     * y actualiza la vista previa (sigue sin crear nada en BD).
     */
    public function previewRevise(Request $request)
    {
        $draft = session(self::AI_DRAFT_SESSION_KEY);
        abort_unless($draft, 404);

        $data = $request->validate([
            'feedback' => ['required', 'string', 'max:2000'],
        ]);

        try {
            set_time_limit(240);
            $ai = app(AiProcedureGenerationService::class)->generate(
                $draft['wizard'],
                $draft['ai'],
                $data['feedback'],
                companyName: $draft['company_name'] ?? '',
                documentName: Str::upper($draft['meta']['nombre'] ?? ''),
                documentCode: ($draft['meta']['codigo'] ?? null) ? Str::upper($draft['meta']['codigo']) : ''
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors([
                'ai' => 'No se pudieron aplicar los cambios: ' . $e->getMessage(),
            ]);
        }

        $draft['ai'] = $ai;
        $draft['revisions'] = ($draft['revisions'] ?? 0) + 1;
        session([self::AI_DRAFT_SESSION_KEY => $draft]);

        return redirect()->route('processes.preview.show');
    }

    /**
     * Descarta la vista previa sin crear/modificar nada, y regresa al wizard
     * correspondiente (crear en blanco, o editar con los datos originales).
     */
    public function previewCancel()
    {
        $draft = session(self::AI_DRAFT_SESSION_KEY);
        session()->forget(self::AI_DRAFT_SESSION_KEY);

        if (($draft['mode'] ?? null) === 'edit' && ($draft['regulation_id'] ?? null)) {
            return redirect()->route('processes.edit', $draft['regulation_id']);
        }

        return redirect()->route('processes.create');
    }

    /**
     * Paso 3: el usuario aceptó el documento. Crea el Regulation (modo "create") o
     * actualiza uno existente y le agrega una nueva versión (modo "edit").
     */
    public function previewConfirm()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $draft = session(self::AI_DRAFT_SESSION_KEY);
        abort_unless($draft, 404);

        $response = ($draft['mode'] ?? 'create') === 'edit'
            ? $this->confirmEditDraft($draft, $user)
            : $this->confirmCreateDraft($draft, $user);

        session()->forget(self::AI_DRAFT_SESSION_KEY);

        return $response;
    }

    private function confirmCreateDraft(array $draft, $user)
    {
        $data = $draft['meta'];
        $ai = $draft['ai'];

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        $regulation = DB::transaction(function () use ($data, $company, $user, $ai) {
            $regulation = Regulation::create([
                'group_id'        => $user->group_id,
                'company_id'      => $company->id,
                'process_type_id' => $data['process_type_id'],
                'document_type'   => $data['document_type'],
                'is_annex'        => $data['is_annex'] ?? false,
                'code'            => $data['codigo'] ? Str::upper($data['codigo']) : null,
                'name'            => Str::upper($data['nombre']),
                'details'         => $this->mergeWizardMetaIntoDetails($ai['details'], $data),
                'is_active'       => true,
                'created_by'      => $user->id,
                'impact_level'    => null,
                'approval_status' => null,
            ]);

            // Quien crea el reglamento queda como responsable automáticamente — puede editarlo
            // aunque sea operativo. Un admin puede reasignar responsables desde "Info básica".
            $regulation->responsables()->attach($user->id);

            // Se vuelve a sanear aunque generate() ya lo haga: protege borradores que quedaron
            // en sesión desde antes de un ajuste al saneador (como este documento pendiente de confirmar).
            $sanitizedHtml = app(AiProcedureGenerationService::class)->sanitizeHtmlForWord($ai['documento_html']);
            $tmpDocx = $this->renderHtmlToDocx($sanitizedHtml, [
                'nombre'         => Str::upper($data['nombre']),
                'codigo'         => $data['codigo'] ? Str::upper($data['codigo']) : null,
                'version'        => '01',
                'quien_elabora'  => $data['quien_elabora'],
                'quien_aprueba'  => $data['quien_aprueba'],
                'fecha_vigencia' => $data['fecha_vigencia'],
            ]);
            $storagePath = "regulations/{$company->id}/{$regulation->id}/versions/procedimiento_v1.docx";
            Storage::disk('private')->put($storagePath, file_get_contents($tmpDocx));
            @unlink($tmpDocx);

            RegulationVersion::create([
                'regulation_id'      => $regulation->id,
                'version_number'     => 1,
                'change_description' => 'Versión inicial redactada con IA a partir del wizard',
                'body_html'          => $sanitizedHtml,
                'responsible_name'   => $data['quien_elabora'],
                'file_path'          => $storagePath,
                'original_name'      => 'procedimiento_v1.docx',
                'disk'               => 'private',
                'mime_type'          => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'issued_at'          => now()->toDateString(),
                // La vigencia real (1 año) se asigna sola al aprobarse — ver
                // ApprovalFlowService::processApproval(). fecha_vigencia del wizard es solo la
                // fecha de elaboración, ya no alimenta valid_until.
                'valid_until'        => null,
                'is_current'         => true,
                'uploaded_by'        => $user->id,
            ]);

            return $regulation;
        });

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento creado y redactado con IA. Asigna el flujo de aprobación desde la tabla de documentos.');
    }

    private function confirmEditDraft(array $draft, $user)
    {
        $regulation = Regulation::findOrFail($draft['regulation_id']);
        abort_unless($regulation->isEditableBy($user), 403);
        $wasApproved = $regulation->approval_status === 'approved';

        $data = $draft['meta'];
        $ai = $draft['ai'];
        $oldDetails = $draft['old_details'] ?? [];
        $newDetails = $this->mergeWizardMetaIntoDetails($ai['details'], $data);

        // Detectar cambios en cualquier campo (details O columnas directas) — igual que el update() anterior.
        $sortedOld = $oldDetails;
        ksort($sortedOld);
        $sortedNew = $newDetails;
        ksort($sortedNew);

        $detailsChanged = $sortedOld !== $sortedNew
            || ($draft['old_process_type_id'] ?? null) !== (int) $data['process_type_id']
            || ($draft['old_document_type'] ?? '') !== ($data['document_type'] ?? '')
            || ($draft['old_name'] ?? '') !== Str::upper($data['nombre'])
            || ($draft['old_code'] ?? '') !== ($data['codigo'] ? Str::upper($data['codigo']) : '');

        // Se vuelve a sanear aunque generate() ya lo haga: protege borradores que quedaron en
        // sesión desde antes de un ajuste al saneador (como este documento pendiente de confirmar).
        // El documento se guarda tal cual lo entregó la IA — el "qué cambió" para quien aprueba se
        // calcula aparte, sección por sección, al mostrar el flujo de aprobación (ver
        // RegulationChangeDiffService), comparando esta versión contra la anterior en el momento de
        // mostrarla, sin alterar el contenido guardado.
        $finalHtml = app(AiProcedureGenerationService::class)->sanitizeHtmlForWord($ai['documento_html']);

        DB::transaction(function () use ($regulation, $data, $user, $ai, $oldDetails, $newDetails, $finalHtml) {
            // Se conserva la vigencia de la versión que se reemplaza — si esta edición no dispara
            // un nuevo ciclo de aprobación (ver setFlow()), no tiene caso dejar la nueva versión
            // sin vigencia; y si sí dispara uno nuevo, ApprovalFlowService la vuelve a asignar
            // (1 año) en cuanto se apruebe, sobrescribiendo este valor de todos modos.
            $previousValidUntil = $regulation->currentVersion?->valid_until;

            $regulation->update([
                'process_type_id'  => $data['process_type_id'],
                'document_type'    => $data['document_type'] ?? null,
                'is_annex'         => $data['is_annex'] ?? false,
                'code'             => $data['codigo'] ? Str::upper($data['codigo']) : null,
                'name'             => Str::upper($data['nombre']),
                'previous_details' => $oldDetails ?: null,
                'details'          => $newDetails,
            ]);

            $regulation->versions()->where('is_current', true)->update(['is_current' => false]);
            $next = ($regulation->versions()->max('version_number') ?? 0) + 1;

            $tmpDocx = $this->renderHtmlToDocx($finalHtml, [
                'nombre'         => Str::upper($data['nombre']),
                'codigo'         => $data['codigo'] ? Str::upper($data['codigo']) : null,
                'version'        => sprintf('%02d', $next),
                'quien_elabora'  => $data['quien_elabora'],
                'quien_aprueba'  => $data['quien_aprueba'],
                'fecha_vigencia' => $data['fecha_vigencia'],
            ]);
            $storagePath = "regulations/{$regulation->company_id}/{$regulation->id}/versions/procedimiento_v{$next}.docx";
            Storage::disk('private')->put($storagePath, file_get_contents($tmpDocx));
            @unlink($tmpDocx);

            RegulationVersion::create([
                'regulation_id'      => $regulation->id,
                'version_number'     => $next,
                'change_description' => 'Actualizado con IA a partir del wizard de edición',
                'body_html'          => $finalHtml,
                'responsible_name'   => $data['quien_elabora'],
                'file_path'          => $storagePath,
                'original_name'      => "procedimiento_v{$next}.docx",
                'disk'               => 'private',
                'mime_type'          => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'issued_at'          => now()->toDateString(),
                'valid_until'        => $previousValidUntil,
                'is_current'         => true,
                'uploaded_by'        => $user->id,
            ]);
        });

        // Si el reglamento estaba rechazado, esta edición es (se asume) la corrección — avisar a
        // los admins que ya pueden reiniciar el flujo, en vez de que se enteren solo si entran a
        // revisar el reglamento por su cuenta.
        $this->flowService->notifyIfCorrectedAfterRejection($regulation);

        // Si en cambio ya estaba aprobado, el contenido acaba de cambiar bajo un documento que se
        // consideraba definitivo — no puede seguir "aprobado" sin que alguien lo revise de nuevo.
        if ($wasApproved) {
            $this->flowService->resubmit($regulation);
        }

        if ($detailsChanged && ($draft['old_flow_locked'] ?? false) && $user->isAdmin()) {
            return redirect()
                ->route('processes.show', ['regulation' => $regulation->id, 'review_flow' => 1])
                ->with('success', 'Documento actualizado y redactado con IA. Los cambios están resaltados en la vista de impresión.');
        }

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento actualizado y redactado con IA. Los cambios están resaltados en la vista de impresión.');
    }

    /**
     * El schema de salida de la IA solo cubre los 20 campos de AiProcedureGenerationService::DETAIL_FIELDS.
     * quien_elabora/quien_aprueba/fecha_vigencia/motivo_creacion viven también dentro de "details" (igual
     * que antes de la integración con IA) pero no pasan por la IA — hay que devolverlos al guardar, o se pierden.
     */
    private function mergeWizardMetaIntoDetails(array $aiDetails, array $wizardMeta): array
    {
        return array_merge($aiDetails, [
            'quien_elabora'   => $wizardMeta['quien_elabora'],
            'quien_aprueba'   => $wizardMeta['quien_aprueba'],
            'fecha_vigencia'  => $wizardMeta['fecha_vigencia'],
            'motivo_creacion' => $wizardMeta['motivo_creacion'],
        ]);
    }

    /**
     * @param  array{nombre: string, codigo: ?string, version: int|string, quien_elabora: ?string, quien_aprueba: ?string, fecha_vigencia: ?string}  $headerMeta
     */
    private function renderHtmlToDocx(string $html, array $headerMeta): string
    {
        // Fijo en código, igual que el encabezado: la tipografía base del documento no debe
        // depender de que la IA la respete siempre — el estilo inline que sí ponga la IA
        // (ej. <p style="color:#1A428A;">) sigue aplicando encima de este default.
        \PhpOffice\PhpWord\Settings::setDefaultFontName('Arial');
        \PhpOffice\PhpWord\Settings::setDefaultFontSize(11);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'paperSize'    => 'Letter',
            'marginTop'    => 2000,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
            'headerHeight' => 1300,
        ]);
        app(RegulationDocxHeaderBuilder::class)->apply($section, $headerMeta);
        WordHtml::addHtml($section, $html, false, false);

        $tmp = tempnam(sys_get_temp_dir(), 'ai_procedure_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        return $tmp;
    }

    public function cargar(Request $request)
    {
        $user = auth()->user();
        // Cargar un documento por archivo — solo admins; crear vía wizard de IA sigue abierto a operativos.
        abort_unless($user->isAdmin(), 403);

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)->where('show_in_processes', true)->where('otras', false)->orderBy('name')->get()
            : collect();

        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('processes.cargar', [
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'processTypes'      => $processTypes,
            'documentTypes'     => Regulation::DOCUMENT_TYPES,
        ]);
    }

    public function storeCargar(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $data = $request->validate([
            'company_id'       => ['required', 'exists:companies,id'],
            'process_type_id'  => ['required', 'exists:process_types,id'],
            'document_type'    => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'is_annex'         => ['sometimes', 'boolean'],
            'nombre'           => ['required', 'string', 'max:255'],
            'codigo'           => ['required', 'string', 'max:50'],
            'quien_elabora'    => ['required', 'string', 'max:255'],
            'quien_aprueba'    => ['required', 'string', 'max:255'],
            // "mimes" valida por el tipo de archivo detectado por el servidor (fileinfo/magic), no
            // por la extensión — y en este entorno (Windows/XAMPP) los formatos de Office modernos
            // (.docx/.xlsx/.pptx, que internamente son un ZIP) se detectan genéricamente como
            // "application/zip" en vez de su tipo específico, así que "mimes" los rechazaba aunque
            // el archivo fuera válido (confirmado reproduciendo el mismo rechazo con un .pptx real).
            // "extensions" valida solo la extensión del archivo, que es justo lo que el usuario ve.
            'file'             => ['required', 'file', 'max:10240', 'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx'],
            'issued_at'        => ['nullable', 'date'],
            'valid_until'      => ['required', 'date', 'after_or_equal:issued_at'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        $regulation = \Illuminate\Support\Facades\DB::transaction(function () use ($data, $request, $company, $user) {
            $details = [
                'quien_elabora' => $data['quien_elabora'],
                'quien_aprueba' => $data['quien_aprueba'],
                'fecha_vigencia' => $data['valid_until'] ?? null,
            ];

            $regulation = Regulation::create([
                'group_id'        => $user->group_id,
                'company_id'      => $company->id,
                'process_type_id' => $data['process_type_id'],
                'document_type'   => $data['document_type'],
                'is_annex'        => $data['is_annex'] ?? false,
                'code'            => $data['codigo'] ? Str::upper($data['codigo']) : null,
                'name'            => Str::upper($data['nombre']),
                'details'         => $details,
                'is_active'       => true,
                'created_by'      => $user->id,
                'impact_level'    => null,
                'approval_status' => 'approved',
                'flow_locked'     => false,
            ]);

            $regulation->responsables()->attach($user->id);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $path = $file->store(
                    "regulations/{$regulation->company_id}/{$regulation->id}/versions",
                    'private'
                );

                \App\Models\RegulationVersion::create([
                    'regulation_id'      => $regulation->id,
                    'version_number'     => 1,
                    'change_description' => 'Versión inicial cargada',
                    'responsible_name'   => $data['quien_aprueba'],
                    'file_path'          => $path,
                    'original_name'      => $file->getClientOriginalName(),
                    'disk'               => 'private',
                    'mime_type'          => $file->getMimeType(),
                    'issued_at'          => $data['issued_at'] ?? now()->toDateString(),
                    'valid_until'        => $data['valid_until'] ?? null,
                    'is_current'         => true,
                    'uploaded_by'        => $user->id,
                ]);
            }

            return $regulation;
        });

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Reglamento cargado correctamente y marcado como aprobado.');
    }

    public function edit(Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($regulation->isEditableBy($user), 403);

        $regulation->load(['processType', 'company']);

        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('processes.edit', [
            'regulation'    => $regulation,
            'processTypes'  => $processTypes,
            'documentTypes' => Regulation::DOCUMENT_TYPES,
        ]);
    }

    private function editWizardValidationRules(): array
    {
        return [
            'process_type_id'             => ['required', 'exists:process_types,id'],
            'document_type'               => ['nullable', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'is_annex'                    => ['sometimes', 'boolean'],
            'codigo'                      => ['nullable', 'string', 'max:50'],
            'nombre'                      => ['required', 'string', 'max:255'],
            'quien_elabora'               => ['required', 'string', 'max:255'],
            'quien_aprueba'               => ['required', 'string', 'max:255'],
            'fecha_vigencia'              => ['required', 'date'],
            'motivo_creacion'             => ['required', 'string'],
            'problema_resuelve'           => ['required', 'string'],
            'resultado_esperado'          => ['required', 'string'],
            'areas_aplica'                => ['required', 'string'],
            'fuera_alcance'               => ['required', 'string'],
            'indicador_proceso'           => ['required', 'string'],
            'indicador_resultado'         => ['required', 'string'],
            'meta_valor'                  => ['required', 'string', 'max:255'],
            'frecuencia_medicion'         => ['required', 'string', 'max:255'],
            'que_detona'                  => ['required', 'string'],
            'lista_actividades'           => ['required', 'string'],
            'areas_ejecutan'              => ['required', 'string'],
            'decisiones_control'          => ['required', 'string'],
            'documentos_usados'           => ['required', 'string'],
            'resultado_entregable'        => ['required', 'string'],
            'areas_roles_mapa'            => ['required', 'string', 'max:255'],
            'procedimientos_relacionados' => ['required', 'string'],
            'proveedores_clientes'        => ['required', 'string'],
            'terminos_abreviaturas'       => ['required', 'string'],
            'riesgos_errores'             => ['required', 'string'],
            'requerimientos_normativos'   => ['required', 'string'],
        ];
    }

    /**
     * Paso 1 (edición): valida el wizard de edición, genera el procedimiento con IA a partir
     * de los campos editados y lo deja en sesión (el Regulation existente no se toca todavía)
     * para mostrarlo en la vista previa.
     */
    public function previewGenerateEdit(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($regulation->isEditableBy($user), 403);

        $data = $request->validate($this->editWizardValidationRules());

        $wizardDetails = collect($data)
            ->except(['process_type_id', 'document_type', 'is_annex', 'codigo', 'nombre'])
            ->toArray();

        try {
            set_time_limit(240); // la generación con IA puede tardar 1-3 minutos según el modelo
            $ai = app(AiProcedureGenerationService::class)->generate(
                $wizardDetails,
                companyName: $regulation->company->name,
                documentName: Str::upper($data['nombre']),
                documentCode: $data['codigo'] ? Str::upper($data['codigo']) : ''
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'ai' => 'No se pudo generar el documento con IA: ' . $e->getMessage(),
            ]);
        }

        session([self::AI_DRAFT_SESSION_KEY => [
            'mode'                => 'edit',
            'regulation_id'       => $regulation->id,
            'meta'                => $data,
            'wizard'              => $wizardDetails,
            'ai'                  => $ai,
            'revisions'           => 0,
            'company_name'        => $regulation->company->name,
            'old_details'         => $regulation->details ?? [],
            'old_flow_locked'     => $regulation->flow_locked,
            'old_process_type_id' => (int) $regulation->process_type_id,
            'old_document_type'   => $regulation->document_type ?? '',
            'old_name'            => $regulation->name ?? '',
            'old_code'            => $regulation->code ?? '',
        ]]);

        return redirect()->route('processes.preview.show');
    }

    public function setFlow(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'impact_level'    => ['nullable', 'string', 'in:' . implode(',', array_keys(Regulation::IMPACT_LEVELS))],
            'users'           => ['nullable', 'array'],
            'users.*'         => ['nullable', 'array'],
            'users.*.*'       => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $newLevel = $data['impact_level'] ?: null;

        // Build userMap: slug → [id, id, ...], skip empty slots
        $userMap = [];
        foreach ($data['users'] ?? [] as $slug => $ids) {
            $clean = array_values(array_filter((array) $ids, fn ($id) => $id !== null && $id !== ''));
            if (count($clean)) {
                $userMap[$slug] = $clean;
            }
        }

        $regulation->approvals()->delete();
        $regulation->update([
            'impact_level'    => $newLevel,
            'approval_status' => $newLevel ? 'pending_review' : null,
            'flow_locked'     => (bool) $newLevel,
            'flow_user_map'   => $newLevel && count($userMap) ? $userMap : null,
        ]);

        if ($newLevel) {
            $this->flowService->initFlow($regulation, $userMap);
        }

        $message = $newLevel ? 'Flujo de aprobación confirmado e iniciado.' : 'Flujo eliminado.';

        // Si viene de la pantalla de revisión (show?review_flow=1), redirigir al show limpio
        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, 'review_flow')) {
            return redirect()->route('processes.show', $regulation)->with('success', $message);
        }

        return back()->with('success', $message);
    }

    public function obsoleto(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)
                ->where('show_in_processes', true)
                ->where('otras', false)
                ->orderBy('name')
                ->get()
            : collect();

        // Regulaciones vencidas: versión actual con valid_until en el pasado
        $expiredQuery = Regulation::with(['currentVersion', 'company:id,name', 'processType:id,name'])
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->whereHas('currentVersion', fn ($q) => $q->where('valid_until', '<', now()));

        if ($selectedCompanyId) {
            $expiredQuery->where('company_id', $selectedCompanyId);
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $expiredQuery->where('company_id', $user->company_id);
        }

        $expiredRegulations = $expiredQuery->orderBy('name')->get();

        // Versiones anteriores reemplazadas (is_current = false)
        $oldVersionsQuery = \App\Models\RegulationVersion::with([
                'regulation.company:id,name',
                'regulation.processType:id,name',
                'uploader:id,name',
            ])
            ->where('is_current', false)
            ->whereHas('regulation', fn ($q) => $q->where('group_id', $user->group_id)->where('is_active', true));

        if ($selectedCompanyId) {
            $oldVersionsQuery->whereHas('regulation', fn ($q) => $q->where('company_id', $selectedCompanyId));
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $oldVersionsQuery->whereHas('regulation', fn ($q) => $q->where('company_id', $user->company_id));
        }

        $oldVersions = $oldVersionsQuery->orderBy('created_at', 'desc')->get();

        return view('processes.obsoleto', [
            'companies'          => $companies,
            'selectedCompanyId'  => $selectedCompanyId,
            'expiredRegulations' => $expiredRegulations,
            'oldVersions'        => $oldVersions,
        ]);
    }

    public function requestAccess(Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($regulation->canRequestAccessFrom($user), 403);

        $notified = $this->flowService->notifyAdminsOfAccessRequest($regulation, $user);

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', $notified > 0
                ? 'Se envió tu solicitud de acceso a los administradores de Procesos.'
                : 'No se encontró un administrador de Procesos a quien avisar — contacta directamente a tu administrador.');
    }

    public function editBasic(Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($regulation->isEditableBy($user), 403);

        $regulation->load(['processType', 'company', 'currentVersion', 'responsables']);

        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        // Solo un admin puede reasignar responsables — se le ofrece el universo de usuarios del
        // grupo que tengan acceso a la empresa del reglamento (mismo candado que canAccessCompany).
        $candidateResponsables = $user->isAdmin()
            ? User::where('group_id', $regulation->group_id)
                ->get()
                ->filter(fn (User $candidate) => $candidate->canAccessCompany($regulation->company))
                ->sortBy('name')
                ->values()
            : collect();

        return view('processes.edit-basic', [
            'regulation'            => $regulation,
            'processTypes'          => $processTypes,
            'documentTypes'         => Regulation::DOCUMENT_TYPES,
            'candidateResponsables' => $candidateResponsables,
        ]);
    }

    public function updateBasic(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($regulation->isEditableBy($user), 403);

        $data = $request->validate([
            'process_type_id' => ['required', 'exists:process_types,id'],
            'document_type'   => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'is_annex'        => ['sometimes', 'boolean'],
            'nombre'          => ['required', 'string', 'max:255'],
            'codigo'          => ['nullable', 'string', 'max:50'],
            'quien_elabora'   => ['required', 'string', 'max:255'],
            'quien_aprueba'   => ['required', 'string', 'max:255'],
            'fecha_vigencia'  => ['required', 'date'],
            'motivo_creacion' => ['required', 'string'],
        ]);

        $newDetails = array_merge($regulation->details ?? [], [
            'quien_elabora'   => $data['quien_elabora'],
            'quien_aprueba'   => $data['quien_aprueba'],
            'fecha_vigencia'  => $data['fecha_vigencia'],
            'motivo_creacion' => $data['motivo_creacion'],
        ]);

        $regulation->update([
            'process_type_id' => $data['process_type_id'],
            'document_type'   => $data['document_type'],
            'is_annex'        => $data['is_annex'] ?? false,
            'code'            => $data['codigo'] ? Str::upper($data['codigo']) : null,
            'name'            => Str::upper($data['nombre']),
            'details'         => $newDetails,
        ]);

        // Reasignar responsables es exclusivo de admins — un operativo (aunque sea responsable y
        // pueda entrar aquí) no puede tocar esta lista, así que ni se lee el input si no es admin.
        if ($user->isAdmin()) {
            $responsableIds = $request->validate([
                'responsables'   => ['sometimes', 'array'],
                'responsables.*' => ['integer', 'exists:users,id'],
            ])['responsables'] ?? [];

            $sync = $regulation->responsables()->sync($responsableIds);

            if (! empty($sync['attached'])) {
                $newResponsables = User::whereIn('id', $sync['attached'])->get();
                $this->flowService->notifyAccessGranted($regulation, $newResponsables);
            }
        }

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Información básica actualizada.');
    }

    public function searchAnnexes(Request $request): JsonResponse
    {
        $user      = auth()->user();
        $companyId = (int) $request->company_id;
        $excludeId = (int) $request->exclude;

        abort_if(! $companyId, 400);

        $company = Company::findOrFail($companyId);
        abort_unless($user->canAccessCompany($company), 403);

        $search = '%' . Str::upper(trim($request->q ?? '')) . '%';

        $results = Regulation::where('company_id', $companyId)
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($search) {
                $q->where('code', 'like', $search)
                  ->orWhere('name', 'like', $search);
            })
            ->orderBy('code')
            ->limit(12)
            ->get(['id', 'code', 'name']);

        return response()->json($results);
    }

    public function setAnnexes(Request $request, Regulation $regulation): JsonResponse
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'annex_ids'   => ['nullable', 'array'],
            'annex_ids.*' => ['integer', 'exists:regulations,id'],
        ]);

        $annexIds = collect($data['annex_ids'] ?? [])
            ->filter(fn ($id) => (int) $id !== $regulation->id);

        if ($annexIds->isNotEmpty()) {
            $valid = Regulation::whereIn('id', $annexIds)
                ->where('company_id', $regulation->company_id)
                ->where('is_active', true)
                ->pluck('id');

            $regulation->annexes()->sync($valid);
        } else {
            $regulation->annexes()->detach();
        }

        return response()->json([
            'annexes' => $regulation->annexes()->get(['regulations.id', 'regulations.code', 'regulations.name']),
        ]);
    }

    public function printView(Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany($regulation->company), 403);

        $regulation->load(['processType', 'company', 'currentVersion']);

        $versionHistory = $regulation->versions()->with('uploader')->get();
        $currentVersion = $versionHistory->firstWhere('is_current', true);

        return view('processes.print', compact('regulation', 'versionHistory', 'currentVersion'));
    }
}
