<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\User;
use App\Services\ApprovalFlowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
                ->withCount(['regulations' => fn ($q) => $q->where('is_active', true)]);

            return view('processes.index', [
                'cardView'            => true,
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

        $query = Regulation::with(['processType', 'company', 'currentVersion', 'annexes'])
            ->where('group_id', $user->group_id)
            ->where('is_active', true);

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
            $search = '%' . strtoupper($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        $regulations = $query->orderBy('code')->orderBy('name')->get();

        // IDs de reglamentos donde el usuario autenticado tiene aprobación pendiente
        $pendingApprovalIds = \App\Models\RegulationApproval::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereIn('regulation_id', $regulations->pluck('id'))
            ->pluck('regulation_id')
            ->flip(); // flip para lookup O(1)

        // Búsqueda global: sin empresa seleccionada + búsqueda, o modo reporte (todas las empresas)
        $globalSearch = $user->hasGroupScope() && ! $selectedCompanyId
            && ($request->filled('q') || $request->boolean('report'));

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

        abort_unless($user->canAccessCompany($regulation->company), 403);

        $regulation->load(['processType', 'company', 'currentVersion', 'creator']);

        $versionHistory = $regulation->versions()->with('uploader')->get();

        $currentVersion = $versionHistory->firstWhere('is_current', true);

        $users = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name']);

        $pendingApprovalForUser = $this->flowService->getPendingApprovalForUser($regulation, $user->id);

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
        ]);
    }

    public function flowView(Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_unless($regulation->impact_level, 404);

        $regulation->load(['processType', 'company']);

        $approvals = $regulation->approvals()
            ->with(['user', 'jobPosition'])
            ->orderBy('step_number')
            ->orderBy('id')
            ->get()
            ->groupBy('step_number');

        $pendingApprovalForUser = $this->flowService->getPendingApprovalForUser($regulation, $user->id);

        return view('processes.flow', compact('regulation', 'approvals', 'pendingApprovalForUser'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createRegulation', [
            'company_id'                  => ['required', 'exists:companies,id'],
            'process_type_id'             => ['required', 'exists:process_types,id'],
            'document_type'               => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'impact_level'                => ['nullable', 'string', 'in:' . implode(',', array_keys(Regulation::IMPACT_LEVELS))],
            'nombre'                      => ['required', 'string', 'max:255'],
            'codigo'                      => ['nullable', 'string', 'max:50'],
            'quien_elabora'               => ['required', 'string', 'max:255'],
            'quien_aprueba'               => ['required', 'string', 'max:255'],
            'fecha_vigencia'              => ['required', 'date'],
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
        ]);

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        $details = collect($data)
            ->except(['company_id', 'process_type_id', 'document_type', 'nombre', 'codigo', 'impact_level'])
            ->toArray();

        $regulation = Regulation::create([
            'group_id'        => $user->group_id,
            'company_id'      => $company->id,
            'process_type_id' => $data['process_type_id'],
            'document_type'   => $data['document_type'],
            'code'            => $data['codigo'] ? strtoupper($data['codigo']) : null,
            'name'            => strtoupper($data['nombre']),
            'details'         => $details,
            'is_active'       => true,
            'created_by'      => $user->id,
            'impact_level'    => null,
            'approval_status' => null,
        ]);

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento creado. Asigna el flujo de aprobación desde la tabla de documentos.');
    }

    public function cargar(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

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
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validate([
            'company_id'       => ['required', 'exists:companies,id'],
            'process_type_id'  => ['required', 'exists:process_types,id'],
            'document_type'    => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'nombre'           => ['required', 'string', 'max:255'],
            'codigo'           => ['required', 'string', 'max:50'],
            'quien_elabora'    => ['required', 'string', 'max:255'],
            'quien_aprueba'    => ['required', 'string', 'max:255'],
            'file'             => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx'],
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
                'code'            => $data['codigo'] ? strtoupper($data['codigo']) : null,
                'name'            => strtoupper($data['nombre']),
                'details'         => $details,
                'is_active'       => true,
                'created_by'      => $user->id,
                'impact_level'    => null,
                'approval_status' => 'approved',
                'flow_locked'     => false,
            ]);

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
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

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

    public function update(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'process_type_id'             => ['required', 'exists:process_types,id'],
            'document_type'               => ['nullable', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'codigo'                      => ['nullable', 'string', 'max:50'],
            'nombre'                      => ['required', 'string', 'max:255'],
            'quien_elabora'               => ['required', 'string', 'max:255'],
            'quien_aprueba'               => ['required', 'string', 'max:255'],
            'fecha_vigencia'              => ['required', 'date'],
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
        ]);

        $oldDetails       = $regulation->details ?? [];
        $flowLocked       = $regulation->flow_locked;
        $oldProcessTypeId = (int) $regulation->process_type_id;
        $oldDocumentType  = $regulation->document_type ?? '';
        $oldName          = $regulation->name ?? '';
        $oldCode          = $regulation->code ?? '';

        $newDetails = collect($data)
            ->except(['process_type_id', 'document_type', 'codigo', 'nombre'])
            ->toArray();

        $regulation->update([
            'process_type_id'  => $data['process_type_id'],
            'document_type'    => $data['document_type'] ?? null,
            'code'             => $data['codigo'] ? strtoupper($data['codigo']) : null,
            'name'             => strtoupper($data['nombre']),
            'previous_details' => $oldDetails ?: null,
            'details'          => $newDetails,
        ]);

        // Detectar cambios en cualquier campo (details O columnas directas)
        ksort($oldDetails);
        $sortedNew = $newDetails;
        ksort($sortedNew);

        $detailsChanged = $oldDetails !== $sortedNew
            || $oldProcessTypeId !== (int) $data['process_type_id']
            || $oldDocumentType  !== ($data['document_type'] ?? '')
            || $oldName          !== strtoupper($data['nombre'])
            || $oldCode          !== ($data['codigo'] ? strtoupper($data['codigo']) : '');

        if ($detailsChanged && $flowLocked && $user->isAdmin()) {
            return redirect()
                ->route('processes.show', ['regulation' => $regulation->id, 'review_flow' => 1])
                ->with('success', 'Documento actualizado. Los cambios están resaltados en la vista de impresión.');
        }

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento actualizado. Los cambios están resaltados en la vista de impresión.');
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

    public function editBasic(Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $regulation->load(['processType', 'company', 'currentVersion']);

        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')->orderBy('name')->get();

        return view('processes.edit-basic', [
            'regulation'    => $regulation,
            'processTypes'  => $processTypes,
            'documentTypes' => Regulation::DOCUMENT_TYPES,
        ]);
    }

    public function updateBasic(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'process_type_id' => ['required', 'exists:process_types,id'],
            'document_type'   => ['required', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'nombre'          => ['required', 'string', 'max:255'],
            'codigo'          => ['nullable', 'string', 'max:50'],
            'quien_elabora'   => ['required', 'string', 'max:255'],
            'quien_aprueba'   => ['required', 'string', 'max:255'],
            'fecha_vigencia'  => ['required', 'date'],
        ]);

        $newDetails = array_merge($regulation->details ?? [], [
            'quien_elabora'  => $data['quien_elabora'],
            'quien_aprueba'  => $data['quien_aprueba'],
            'fecha_vigencia' => $data['fecha_vigencia'],
        ]);

        $regulation->update([
            'process_type_id' => $data['process_type_id'],
            'document_type'   => $data['document_type'],
            'code'            => $data['codigo'] ? strtoupper($data['codigo']) : null,
            'name'            => strtoupper($data['nombre']),
            'details'         => $newDetails,
        ]);

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

        $search = '%' . strtoupper(trim($request->q ?? '')) . '%';

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
