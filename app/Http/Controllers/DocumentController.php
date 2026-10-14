<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedCompanyId = ($user->hasGroupScope() || $user->hasMultipleCompanies())
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $allCompanies = $user->hasGroupScope()
            ? Company::query()
                ->where('group_id', $user->group_id)
                ->orderBy('name')
                ->get()
            : ($user->hasMultipleCompanies()
                ? Company::query()
                    ->whereIn('id', $user->accessibleCompanyIds())
                    ->orderBy('name')
                    ->get()
                : collect());

        // Las empresas "otras" se muestran aparte (bajo "Otras empresas") en vez de excluirse del
        // todo — existen y se les puede asignar un documento, solo no se mezclan con la lista
        // principal para no saturarla.
        $companies      = $allCompanies->where('otras', false)->values();
        $otrasCompanies = $allCompanies->where('otras', true)->values();

        $documentsQuery = Document::query()
            ->with(['currentVersions', 'company:id,name'])
            ->where('group_id', $user->group_id)
            ->where('is_active', true);

        if ($selectedCompanyId) {
            $documentsQuery->where('company_id', $selectedCompanyId);
        } elseif ($user->hasMultipleCompanies()) {
            $documentsQuery->whereIn('company_id', $user->accessibleCompanyIds());
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $documentsQuery->where('company_id', $user->company_id);
        }

        if ($request->filled('q')) {
            $q = Str::upper($request->q);
            $documentsQuery->where(function ($query) use ($q) {
                $query->where('name', 'like', '%' . $q . '%')
                    ->orWhere('reference', 'like', '%' . $q . '%');
            });
        }

        $documentType = $request->filled('document_type') && in_array($request->document_type, Document::DOCUMENT_TYPES, true)
            ? $request->document_type
            : null;

        if ($documentType) {
            $documentsQuery->where('document_type', $documentType);
        }

        $vigencia = in_array($request->vigencia, ['vigente', 'por_vencer', 'vencido', 'sin_vencimiento'], true)
            ? $request->vigencia
            : null;

        $today        = now()->toDateString();
        $nearHorizon  = now()->addDays(60)->toDateString();

        // Un documento puede tener varios archivos vigentes a la vez, así que su estado de
        // vigencia es el peor caso entre todos ellos (si cualquiera está vencido, el documento
        // cuenta como vencido) — el mismo criterio que Document::vigenciaStatus().
        match ($vigencia) {
            'vencido' => $documentsQuery->whereHas(
                'currentVersions',
                fn ($q) => $q->whereNotNull('valid_until')->where('valid_until', '<', $today)
            ),
            'por_vencer' => $documentsQuery
                ->whereDoesntHave('currentVersions', fn ($q) => $q->whereNotNull('valid_until')->where('valid_until', '<', $today))
                ->whereHas('currentVersions', fn ($q) => $q->whereNotNull('valid_until')->whereBetween('valid_until', [$today, $nearHorizon])),
            'vigente' => $documentsQuery
                ->whereDoesntHave('currentVersions', fn ($q) => $q->whereNotNull('valid_until')->where('valid_until', '<', $today))
                ->whereDoesntHave('currentVersions', fn ($q) => $q->whereNotNull('valid_until')->whereBetween('valid_until', [$today, $nearHorizon]))
                ->whereHas('currentVersions', fn ($q) => $q->where('valid_until', '>', $nearHorizon)),
            'sin_vencimiento' => $documentsQuery->whereDoesntHave(
                'currentVersions',
                fn ($q) => $q->whereNotNull('valid_until')
            ),
            default => null,
        };

        $documents = $documentsQuery
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('documents.index', [
            'documents'          => $documents,
            'companies'          => $companies,
            'otrasCompanies'     => $otrasCompanies,
            'selectedCompanyId'  => $selectedCompanyId,
            'documentTypes'      => Document::DOCUMENT_TYPES,
            'selectedType'       => $documentType,
            'selectedVigencia'   => $vigencia,
            'users'              => User::query()
                ->where('group_id', $user->group_id)
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
                ->orderBy('name')
                ->get(['id', 'name', 'role_id']),
            'groupUsers'         => $this->groupUsersForAccessPicker($user),
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createDocument', $this->documentValidationRules());

        $companyId = $data['company_id']
            ?? ((! $user->hasGroupScope() && ! $user->hasMultipleCompanies()) ? $user->company_id : null);

        abort_if(($user->hasGroupScope() || $user->hasMultipleCompanies()) && ! $companyId, 422, 'Debes seleccionar una empresa.');
        abort_unless($user->canAccessCompany(Company::find($companyId)), 403);

        $document = Document::create([
            'group_id'                => $user->group_id,
            'company_id'              => $companyId,
            'document_folder_id'      => null,
            'name'                    => Str::upper($data['name']),
            'description'             => $data['description'] ?? null,
            'reference'               => $data['reference'] ?? null,
            'bodega'                  => $data['bodega'] ?? null,
            'document_type'           => $data['document_type'],
            'responsible_name'        => $data['responsible_name'] ?? null,
            'is_required'             => true,
            'is_active'               => true,
            'uploaded_by'             => $user->id,
        ]);

        $document->authorizedUsers()->sync($data['authorized_user_ids'] ?? []);

        return redirect()
            ->route('documents.index')
            ->with('success', 'Documento creado correctamente.');
    }

    public function update(Request $request, Document $document)
    {
        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('editDocument', $this->documentValidationRules());

        $companyId = $data['company_id']
            ?? ((! $user->hasGroupScope() && ! $user->hasMultipleCompanies()) ? $user->company_id : null);

        abort_if(($user->hasGroupScope() || $user->hasMultipleCompanies()) && ! $companyId, 422, 'Debes seleccionar una empresa.');
        abort_unless($user->canAccessCompany(Company::find($companyId)), 403);

        $document->update([
            'company_id'       => $companyId,
            'name'             => Str::upper($data['name']),
            'description'      => $data['description'] ?? null,
            'reference'        => $data['reference'] ?? null,
            'bodega'           => $data['bodega'] ?? null,
            'document_type'    => $data['document_type'],
            'responsible_name' => $data['responsible_name'] ?? null,
            'is_required'      => true,
        ]);

        $document->authorizedUsers()->sync($data['authorized_user_ids'] ?? []);

        return redirect()
            ->route('documents.show', $document)
            ->with('success', 'Documento actualizado correctamente.');
    }

    private function documentValidationRules(): array
    {
        return [
            'name'                    => ['required', 'string', 'max:255'],
            'description'             => ['nullable', 'string'],
            'company_id'              => ['nullable', 'exists:companies,id'],
            'reference'               => ['nullable', 'string', 'max:255'],
            'bodega'                  => ['nullable', 'string', 'max:255'],
            'document_type'           => ['required', 'string', Rule::in(Document::DOCUMENT_TYPES)],
            'responsible_name'        => ['nullable', 'string', 'max:255'],
            'authorized_user_ids'     => ['nullable', 'array'],
            'authorized_user_ids.*'   => ['integer', 'exists:users,id'],
        ];
    }

    private function groupUsersForAccessPicker($user)
    {
        return User::query()
            ->where('group_id', $user->group_id)
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'role_id']);
    }

    public function destroy(Document $document)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $this->authorizeDocumentAccess($user, $document);

        $document->update([
            'deleted_by'             => $user->id,
            'permanently_delete_at'  => now()->addMonths(2),
        ]);

        $document->delete();

        return back()->with('success', 'Documento movido a la papelera. Se eliminará permanentemente en 2 meses.');
    }

    // Documentos con empresa asignada requieren acceso a esa empresa;
    // documentos generales (company_id=null) son accesibles a cualquier usuario del mismo grupo.
    private function authorizeDocumentAccess($user, Document $document): void
    {
        if ($document->company_id !== null) {
            abort_unless($user->canAccessCompany($document->company), 403);
        } else {
            abort_unless(
                $user->isGlobalScope() || $user->group_id === $document->group_id,
                403
            );
        }
    }
}
