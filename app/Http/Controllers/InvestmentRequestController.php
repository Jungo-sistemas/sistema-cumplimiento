<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\InvestmentRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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
            ->with(['company:id,name', 'requester:id,name'])
            ->where('group_id', $user->group_id);

        if ($selectedCompanyId) {
            $requestsQuery->where('company_id', $selectedCompanyId);
        } elseif ($user->hasMultipleCompanies()) {
            $requestsQuery->whereIn('company_id', $user->accessibleCompanyIds());
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $requestsQuery->where('company_id', $user->company_id);
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
                ->get(['id', 'name']),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createInvestmentRequest', [
            'company_id'   => ['nullable', 'exists:companies,id'],
            'concept'      => ['required', 'string', 'max:500'],
            'amount'       => ['required', 'numeric', 'min:0'],
            'deadline_at'  => ['nullable', 'date'],
            'requested_by' => ['nullable', 'integer', 'exists:users,id'],
            'file'         => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
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

        return redirect()
            ->route('investment-requests.index')
            ->with('success', 'Solicitud de inversión creada correctamente.');
    }

    public function show(InvestmentRequest $investmentRequest)
    {
        $user = auth()->user();
        $this->authorizeAccess($user, $investmentRequest);

        $investmentRequest->load(['company', 'requester', 'uploader']);

        return view('investment-requests.show', [
            'investmentRequest' => $investmentRequest,
        ]);
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
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png'];

        if (! $mime || ! in_array($mime, $allowed, true)) {
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
    private function authorizeAccess($user, InvestmentRequest $investmentRequest): void
    {
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
