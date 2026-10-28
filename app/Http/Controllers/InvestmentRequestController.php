<?php

namespace App\Http\Controllers;

use App\Enums\InvestmentRequestStatus;
use App\Models\Company;
use App\Models\InvestmentRequest;
use App\Models\User;
use App\Services\InvestmentApprovalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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

        $selectedCompanyId = ($user->hasGroupScope() || $user->hasMultipleCompanies())
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::query()
                ->where('group_id', $user->group_id)
                ->where('otras', false)
                ->orderBy('name')
                ->get()
            : ($user->hasMultipleCompanies()
                ? Company::query()
                    ->whereIn('id', $user->accessibleCompanyIds())
                    ->where('otras', false)
                    ->orderBy('name')
                    ->get()
                : collect());

        $requestsQuery = InvestmentRequest::query()
            ->with(['company:id,name', 'requester:id,name', 'approvals'])
            ->where('group_id', $user->group_id);

        if ($selectedCompanyId) {
            $requestsQuery->where('company_id', $selectedCompanyId);
        } elseif ($user->hasMultipleCompanies()) {
            $requestsQuery->whereIn('company_id', $user->accessibleCompanyIds());
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $requestsQuery->where('company_id', $user->company_id);
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
            'companies'          => $companies,
            'selectedCompanyId'  => $selectedCompanyId,
            'groupUsers'         => User::query()
                ->where('group_id', $user->group_id)
                ->orderBy('name')
                ->get(['id', 'name', 'role_id']),
            'approverCandidates' => $this->approverCandidates($user->group_id)->get(['id', 'name', 'role_id']),
            'maxApprovers'       => InvestmentApprovalService::MAX_APPROVERS,
        ]);
    }

    // Personas del grupo que pueden aprobar: deben poder entrar al módulo de Cumplimiento, donde
    // vive Solicitud de Inversiones (un usuario solo de Procesos no podría abrir la solicitud).
    private function approverCandidates(int $groupId)
    {
        return User::query()
            ->where('group_id', $groupId)
            ->where(fn ($q) => $q->whereNull('module_access')->orWhereIn('module_access', ['all', 'cumplimiento']))
            ->orderBy('name');
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
            'company_id'   => ['nullable', 'exists:companies,id'],
            'concept'      => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'integer', 'min:0'],
            'deadline_at'  => ['nullable', 'date'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            // Aprobadores en el orden en que deben aprobar; nadie puede repetirse.
            'approver_ids'   => ['required', 'array', 'min:1', 'max:' . InvestmentApprovalService::MAX_APPROVERS],
            'approver_ids.*' => [
                'required', 'integer', 'distinct',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('group_id', $user->group_id)
                    ->where(fn ($q) => $q->whereNull('module_access')->orWhereIn('module_access', ['all', 'cumplimiento']))),
            ],
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
            'approver_ids.required'   => 'Elige al menos una persona para el flujo de aprobación.',
            'approver_ids.*.required' => 'Elige a la persona de cada lugar del flujo de aprobación.',
            'approver_ids.*.distinct' => 'Una misma persona no puede estar dos veces en el flujo de aprobación.',
        ]);

        $companyId = $data['company_id']
            ?? ((! $user->hasGroupScope() && ! $user->hasMultipleCompanies()) ? $user->company_id : null);

        abort_if(($user->hasGroupScope() || $user->hasMultipleCompanies()) && ! $companyId, 422, 'Debes seleccionar una empresa.');
        abort_unless($user->canAccessCompany(Company::find($companyId)), 403);

        $investmentRequest = InvestmentRequest::create([
            'group_id'     => $user->group_id,
            'company_id'   => $companyId,
            'concept'      => $data['concept'],
            'amount'       => $data['amount'],
            'deadline_at'  => $data['deadline_at'] ?? null,
            'requested_by' => $data['requested_by'] ?? null,
            'uploaded_by'  => $user->id,
        ]);

        $file      = $request->file('file');
        $directory = "investment-requests/{$companyId}/{$investmentRequest->id}";
        $filename  = now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName();
        $path      = $file->storeAs($directory, $filename, 'private');

        $investmentRequest->update([
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type'     => $file->getClientMimeType(),
            'file_size'     => $file->getSize(),
        ]);

        app(InvestmentApprovalService::class)->start($investmentRequest, $data['approver_ids']);

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
