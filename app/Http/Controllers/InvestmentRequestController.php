<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentRequestStatus;
use App\Models\InvestmentEntity;
use App\Models\InvestmentRequest;
use App\Models\InvestmentRequestApproval;
use App\Models\User;
use App\Services\InvestmentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class InvestmentRequestController extends Controller
{
    private function disk()
    {
        return Storage::disk('private');
    }

    public function index(Request $request)
    {
        $user = auth()->user();

        // La lista "Empresa" es exactamente la de razones sociales de la tabla del cliente, cada
        // una con su flujo fijo. Quien no es de grupo solo ve las de sus empresas.
        $entities = $this->entitiesFor($user);
        $selectedEntityId = $request->filled('entity_id') ? (int) $request->entity_id : null;

        $requestsQuery = InvestmentRequest::query()
            ->with(['company:id,name', 'entity:id,name', 'requester:id,name', 'approvals'])
            ->where('group_id', $user->group_id);

        if ($selectedEntityId) {
            $requestsQuery->where('investment_entity_id', $selectedEntityId);
        }

        // Fuera del alcance de grupo, solo las solicitudes de sus empresas.
        if (! $user->hasGroupScope()) {
            $requestsQuery->whereIn('company_id', $user->accessibleCompanyIds() ?: [0]);
        }

        // Busca en el nombre del archivo del Anexo 8 y en el solicitante.
        if ($request->filled('q')) {
            $q = '%' . trim($request->q) . '%';
            $requestsQuery->where(function ($query) use ($q) {
                $query->where('original_name', 'ilike', $q)
                    ->orWhereHas('requester', fn ($r) => $r->where('name', 'ilike', $q));
            });
        }

        $investmentRequests = $requestsQuery
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('investment-requests.index', [
            'investmentRequests' => $investmentRequests,
            'entities'           => $entities,
            'selectedEntityId'   => $selectedEntityId,
            'groupUsers'         => User::query()
                ->where('group_id', $user->group_id)
                ->orderBy('name')
                ->get(['id', 'name', 'role_id']),
            // El modal muestra el flujo fijo de la razón social elegida antes de guardar.
            'approvalFlows'      => $this->approvalFlowsFor($entities->pluck('id')->all()),
        ]);
    }

    /** Razones sociales activas que el usuario puede elegir, en el orden de la tabla del cliente. */
    private function entitiesFor(User $user)
    {
        return InvestmentEntity::query()
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->when(! $user->hasGroupScope(), fn ($q) => $q->whereIn('company_id', $user->accessibleCompanyIds() ?: [0]))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /** @return array<int, array{steps: array, problem: ?string}> por investment_entity_id */
    private function approvalFlowsFor(array $entityIds): array
    {
        $service = app(InvestmentApprovalService::class);
        $steps   = $service->stepsForEntities($entityIds);

        return collect($entityIds)->mapWithKeys(function ($id) use ($steps, $service) {
            $entitySteps = $steps->get($id, collect());

            return [$id => [
                'steps'   => $entitySteps->map(fn ($s) => [
                    'position' => $s->position,
                    'name'     => $s->user?->name ?? $s->approver_name,
                    'missing'  => $s->user_id === null,
                ])->values()->all(),
                'problem' => $service->flowProblem($entitySteps),
            ]];
        })->all();
    }

    // Solicitudes donde me toca aprobar y las que ya decidí.
    public function myApprovals()
    {
        $user = auth()->user();

        $base = fn () => InvestmentRequestApproval::query()
            ->where('user_id', $user->id)
            ->with(['investmentRequest.company:id,name', 'investmentRequest.entity:id,name', 'investmentRequest.requester:id,name', 'investmentRequest.approvals']);

        return view('investment-requests.my-approvals', [
            'pending' => $base()->where('status', 'pending')->oldest('updated_at')->get(),
            'decided' => $base()->whereIn('status', ['approved', 'rejected'])->latest('decided_at')->paginate(20),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        // El monto se captura con formato de dinero ("$1,250,000"); se quitan "$", comas y espacios
        // antes de validar. El punto no se quita: "1000.50" debe fallar, no convertirse en 100050.
        $request->merge([
            'amount' => preg_replace('/[\s$,]/', '', (string) $request->input('amount')),
        ]);

        $data = $request->validateWithBag('createInvestmentRequest', [
            'entity_id'    => ['nullable', 'integer'],
            'concept'      => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'integer', 'min:0'],
            'deadline_at'  => ['nullable', 'date'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'file'         => [
                'required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png,xls,xlsx',
                // Evita subir otro documento por error: el nombre debe decir que es el Anexo 8
                // ("Anexo 8", "ANEXO_8", "anexo-08"…).
                function ($attribute, $file, $fail) {
                    $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);

                    if (! preg_match('/anexo[\s_\-.]*0*8(?!\d)/i', $name)) {
                        $fail('El nombre del archivo debe incluir "Anexo 8" (ej. "Anexo 8 - Compra de equipo.xlsx").');
                    }
                },
            ],
        ], [
            'amount.integer'          => 'El monto debe ser una cantidad entera, sin centavos.',
            'file.mimes'              => 'El archivo debe ser PDF, JPG, PNG o Excel (XLS, XLSX).',
        ]);

        // Solo se puede elegir una razón social de la lista que el usuario ve.
        $entity = $this->entitiesFor($user)->firstWhere('id', (int) ($data['entity_id'] ?? 0));

        if (! $entity) {
            throw ValidationException::withMessages(['entity_id' => 'Debes seleccionar una empresa de la lista.'])
                ->errorBag('createInvestmentRequest');
        }

        // El flujo de aprobación es fijo por razón social: sin flujo completo no se puede capturar.
        $service = app(InvestmentApprovalService::class);
        $problem = $service->flowProblem($service->stepsForEntities([$entity->id])->get($entity->id, collect()));

        if ($problem) {
            throw ValidationException::withMessages(['entity_id' => $problem])
                ->errorBag('createInvestmentRequest');
        }

        $companyId = $entity->company_id;

        $investmentRequest = InvestmentRequest::create([
            'group_id'     => $user->group_id,
            'company_id'   => $companyId,
            'investment_entity_id' => $entity->id,
            'concept'      => $data['concept'],
            'amount'       => $data['amount'],
            'deadline_at'  => $data['deadline_at'] ?? null,
            'requested_by' => $data['requested_by'] ?? null,
            'uploaded_by'  => $user->id,
        ]);

        $file      = $request->file('file');
        $directory = 'investment-requests/' . ($companyId ?? 'grupo') . "/{$investmentRequest->id}";
        $filename  = now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName();
        $path      = $file->storeAs($directory, $filename, 'private');

        $investmentRequest->update([
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType(),
            'file_size'     => $file->getSize(),
        ]);

        $service->start($investmentRequest);

        return redirect()
            ->route('investment-requests.index')
            ->with('success', 'Solicitud de inversión creada. Se avisó a la primera persona del flujo de aprobación.');
    }

    public function show(InvestmentRequest $investmentRequest)
    {
        $user = auth()->user();
        $this->authorizeAccess($user, $investmentRequest);

        $investmentRequest->load(['company', 'requester', 'uploader', 'approvals.user']);

        return view('investment-requests.show', [
            'investmentRequest' => $investmentRequest,
            'myTurn'            => $investmentRequest->approvals->first(
                fn ($a) => $a->isPending() && $a->user_id === $user->id
            ),
        ]);
    }

    public function approve(Request $request, InvestmentRequest $investmentRequest)
    {
        $approval = $this->myPendingApproval($investmentRequest);

        $data = $request->validate(['comments' => ['nullable', 'string', 'max:2000']]);

        app(InvestmentApprovalService::class)->approve($approval, $data['comments'] ?? null);

        $message = $investmentRequest->fresh()->status === InvestmentRequestStatus::APPROVED
            ? 'Aprobaste la solicitud. Era el último paso: la solicitud quedó aprobada.'
            : 'Aprobaste la solicitud. Se avisó a la siguiente persona del flujo.';

        return redirect()->route('investment-requests.show', $investmentRequest)->with('success', $message);
    }

    public function reject(Request $request, InvestmentRequest $investmentRequest)
    {
        $approval = $this->myPendingApproval($investmentRequest);

        $data = $request->validate(
            ['comments' => ['required', 'string', 'max:2000']],
            ['comments.required' => 'Escribe el motivo del rechazo.']
        );

        app(InvestmentApprovalService::class)->reject($approval, $data['comments']);

        return redirect()
            ->route('investment-requests.show', $investmentRequest)
            ->with('success', 'Rechazaste la solicitud. Se avisó a quien la capturó y al solicitante.');
    }

    // Solo quien tiene el turno puede aprobar o rechazar.
    private function myPendingApproval(InvestmentRequest $investmentRequest)
    {
        $approval = $investmentRequest->currentApproval()->first();

        abort_unless($approval && $approval->user_id === auth()->id(), 403, 'No es tu turno de aprobar esta solicitud.');

        return $approval;
    }

    public function preview(InvestmentRequest $investmentRequest)
    {
        $user = auth()->user();
        $this->authorizeAccess($user, $investmentRequest);

        if (! $investmentRequest->file_path || ! $this->disk()->exists($investmentRequest->file_path)) {
            abort(404);
        }

        $fullPath = $this->disk()->path($investmentRequest->file_path);
        $mime     = $investmentRequest->mime_type ?: (file_exists($fullPath) ? mime_content_type($fullPath) : null);

        if (! $mime || ! in_array($mime, InvestmentRequest::PREVIEWABLE_MIME_TYPES, true)) {
            abort(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Vista previa no disponible para este tipo de archivo.');
        }

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $this->safeFilename($investmentRequest->original_name ?? 'anexo8') . '"',
        ]);
    }

    public function download(InvestmentRequest $investmentRequest)
    {
        $user = auth()->user();
        $this->authorizeAccess($user, $investmentRequest);

        if (! $investmentRequest->file_path || ! $this->disk()->exists($investmentRequest->file_path)) {
            abort(404);
        }

        return $this->disk()->download(
            $investmentRequest->file_path,
            $investmentRequest->original_name ?? basename($investmentRequest->file_path)
        );
    }

    // Solicitudes con empresa asignada requieren acceso a esa empresa;
    // solicitudes generales (company_id=null) son accesibles a cualquier usuario del mismo grupo.
    // Quien está en el flujo de aprobación siempre puede verla, aunque no tenga esa empresa.
    private function authorizeAccess($user, InvestmentRequest $investmentRequest): void
    {
        if ($investmentRequest->approvals()->where('user_id', $user->id)->exists()) {
            return;
        }

        // Sin Cumplimiento solo entra como aprobador (ver EnsureModuleAccess).
        abort_unless($user->isSuperAdmin() || $user->canAccessModule('cumplimiento'), 403);

        if ($investmentRequest->company_id !== null) {
            abort_unless($user->canAccessCompany($investmentRequest->company), 403);
        } else {
            abort_unless(
                $user->isGlobalScope() || $user->group_id === $investmentRequest->group_id,
                403
            );
        }
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^\w.\-]/', '_', $name);
    }
}
