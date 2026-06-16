<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\User;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        // Excluir empresas "otras" del filtro de documentos
        $companies = $user->hasGroupScope()
            ? Company::query()
                ->where('group_id', $user->group_id)
                ->where('otras', false)
                ->orderBy('name')
                ->get()
            : collect();

        // Las carpetas son generales (company_id = null), a nivel de grupo
        $foldersQuery = DocumentFolder::query()
            ->withCount('children as categories_count')
            ->whereNull('parent_id')
            ->where('level', 'folder')
            ->where('group_id', $user->group_id)
            ->whereNull('company_id')
            ->where('is_active', true);

        $matchingCategories = collect();
        $matchingDocuments  = collect();

        if ($request->filled('q')) {
            $q = strtoupper($request->q);

            $foldersQuery->where('name', 'like', '%' . $q . '%');

            $categoriesQuery = DocumentFolder::query()
                ->where('level', 'category')
                ->where('group_id', $user->group_id)
                ->where('is_active', true)
                ->where('name', 'like', '%' . $q . '%')
                ->with('parent');

            $documentsQuery = Document::query()
                ->where('group_id', $user->group_id)
                ->where('is_active', true)
                ->where('name', 'like', '%' . $q . '%')
                ->with(['folder.parent', 'currentVersion']);

            if ($selectedCompanyId) {
                $documentsQuery->where('company_id', $selectedCompanyId);
            } elseif (! $user->hasGroupScope() && $user->company_id) {
                $documentsQuery->where('company_id', $user->company_id);
            }

            $matchingCategories = $categoriesQuery->orderBy('name')->get();
            $matchingDocuments  = $documentsQuery->orderBy('name')->get();
        }

        $folders = $foldersQuery
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('documents.index', [
            'folders'            => $folders,
            'companies'          => $companies,
            'selectedCompanyId'  => $selectedCompanyId,
            'matchingCategories' => $matchingCategories,
            'matchingDocuments'  => $matchingDocuments,
        ]);
    }

    public function showFolder(Request $request, DocumentFolder $folder)
    {
        $user = auth()->user();

        $this->authorizeFolder($user, $folder);

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)
                ->where('otras', false)
                ->orderBy('name')
                ->get()
            : collect();

        $documentsQuery = Document::query()
            ->with(['currentVersion', 'company:id,name'])
            ->where('document_folder_id', $folder->id)
            ->where('is_active', true);

        if ($selectedCompanyId) {
            $documentsQuery->where('company_id', $selectedCompanyId);
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $documentsQuery->where('company_id', $user->company_id);
        }

        $documents = $documentsQuery->orderBy('name')->get();

        $users = User::query()
            ->where('group_id', $folder->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('documents.folder', [
            'folder'            => $folder,
            'documents'         => $documents,
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'users'             => $users,
        ]);
    }

    public function showCategory(Request $request, DocumentFolder $category)
    {
        $user = auth()->user();

        $this->authorizeFolder($user, $category);

        $category->load('parent');

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)
                ->where('otras', false)
                ->orderBy('name')
                ->get()
            : collect();

        $documentsQuery = Document::query()
            ->with(['currentVersion', 'folder', 'company:id,name'])
            ->where('document_folder_id', $category->id)
            ->where('is_active', true);

        if ($selectedCompanyId) {
            $documentsQuery->where('company_id', $selectedCompanyId);
        } elseif (! $user->hasGroupScope() && $user->company_id) {
            $documentsQuery->where('company_id', $user->company_id);
        }

        $documents = $documentsQuery->orderBy('name')->get();

        $users = User::query()
            ->where('group_id', $category->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('documents.category', [
            'category'          => $category,
            'documents'         => $documents,
            'users'             => $users,
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
        ]);
    }

    public function store(Request $request, DocumentFolder $category)
    {
        $user = auth()->user();

        $this->authorizeFolder($user, $category);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createDocument', [
            'name'                    => ['required', 'string', 'max:255'],
            'company_id'              => ['nullable', 'exists:companies,id'],
            'reference'               => ['nullable', 'string', 'max:255'],
            'document_type'           => ['nullable', 'string', 'max:255'],
            'responsible_name'        => ['nullable', 'string', 'max:255'],
            'authorized_access_notes' => ['nullable', 'string', 'max:1000'],
            'is_required'             => ['nullable', 'boolean'],
        ]);

        // For general categories (company_id=null), use request company_id or user's company
        $companyId = $category->company_id
            ?? ($data['company_id'] ?? null)
            ?? ($user->hasGroupScope() ? null : $user->company_id);

        Document::create([
            'group_id'                => $category->group_id,
            'company_id'              => $companyId,
            'document_folder_id'      => $category->id,
            'name'                    => strtoupper($data['name']),
            'reference'               => $data['reference'] ?? null,
            'document_type'           => $data['document_type'] ?? null,
            'responsible_name'        => $data['responsible_name'] ?? null,
            'authorized_access_notes' => $data['authorized_access_notes'] ?? null,
            'is_required'             => ! empty($data['is_required']),
            'is_active'               => true,
            'uploaded_by'             => $user->id,
        ]);

        return redirect()
            ->route('documents.categories.show', $category)
            ->with('success', 'Documento creado correctamente.');
    }

    public function destroy(DocumentFolder $folder, Document $document)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        if ((int) $document->document_folder_id !== (int) $folder->id) {
            abort(404);
        }

        $this->authorizeFolder($user, $folder);

        $document->update([
            'deleted_by'             => $user->id,
            'permanently_delete_at'  => now()->addMonths(2),
        ]);

        $document->delete();

        return back()->with('success', 'Documento movido a la papelera. Se eliminará permanentemente en 2 meses.');
    }

    // General folders (company_id=null) are accessible to any user in the same group.
    // Company-specific folders (legacy) require canAccessCompany.
    private function authorizeFolder($user, DocumentFolder $folder): void
    {
        if ($folder->company_id !== null) {
            $folder->loadMissing('company');
            abort_unless($user->canAccessCompany($folder->company), 403);
        } else {
            abort_unless(
                $user->isGlobalScope() || $user->group_id === $folder->group_id,
                403
            );
        }
    }
}
