<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\User;
use App\Services\ApprovalFlowService;
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

        // Group user with no company selected AND no search → company card grid
        if ($user->hasGroupScope() && ! $selectedCompanyId && ! $request->filled('q')) {
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
            ]);
        }

        // Table view
        $processTypes = ProcessType::where('group_id', $user->group_id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $query = Regulation::with(['processType', 'company', 'currentVersion'])
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

        // Búsqueda global: sin empresa seleccionada, con texto, usuario de grupo
        $globalSearch = $user->hasGroupScope() && ! $selectedCompanyId && $request->filled('q');

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

        $approvals = $regulation->approvals()
            ->with(['user', 'jobPosition'])
            ->get()
            ->groupBy('step_number');

        $authUser = auth()->user();
        $pendingApprovalForUser = $this->flowService->getPendingApprovalForUser($regulation, $authUser->id);

        return view('processes.show', [
            'regulation'             => $regulation,
            'versionHistory'         => $versionHistory,
            'currentVersion'         => $currentVersion,
            'users'                  => $users,
            'documentTypes'          => Regulation::DOCUMENT_TYPES,
            'approvals'              => $approvals,
            'pendingApprovalForUser' => $pendingApprovalForUser,
        ]);
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

    public function update(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'process_type_id' => ['required', 'exists:process_types,id'],
            'document_type'   => ['nullable', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'code'            => ['nullable', 'string', 'max:50'],
            'name'            => ['required', 'string', 'max:255'],
        ]);

        $regulation->update([
            'process_type_id' => $data['process_type_id'],
            'document_type'   => $data['document_type'] ?? null,
            'code'            => $data['code'] ? strtoupper($data['code']) : null,
            'name'            => strtoupper($data['name']),
        ]);

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Reglamento actualizado correctamente.');
    }

    public function setFlow(Request $request, Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_if($regulation->flow_locked, 403, 'El flujo ya está confirmado y no puede modificarse.');

        $data = $request->validate([
            'impact_level' => ['nullable', 'string', 'in:' . implode(',', array_keys(Regulation::IMPACT_LEVELS))],
        ]);

        $newLevel = $data['impact_level'] ?: null;

        $regulation->approvals()->delete();
        $regulation->update([
            'impact_level'    => $newLevel,
            'approval_status' => $newLevel ? 'pending_review' : null,
            'flow_locked'     => (bool) $newLevel,
        ]);

        if ($newLevel) {
            $this->flowService->initFlow($regulation);
        }

        return back()->with('success', $newLevel
            ? 'Flujo de aprobación confirmado e iniciado.'
            : 'Flujo eliminado.');
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
