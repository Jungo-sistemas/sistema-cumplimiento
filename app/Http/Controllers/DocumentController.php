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

        $companies = $user->hasGroupScope()
            ? Company::query()
                ->where('group_id', $user->group_id)
                ->orderBy('name')
                ->get()
            : collect();

        $foldersQuery = DocumentFolder::query()
            ->withCount([
                'children as categories_count',
                'documents as documents_count',
            ])
            ->whereNull('parent_id')
            ->where('level', 'folder')
            ->where('group_id', $user->group_id)
            ->where('is_active', true);

        if ($selectedCompanyId) {
            $foldersQuery->where('company_id', $selectedCompanyId);
        } elseif (! $user->hasGroupScope()) {
            $foldersQuery->where('company_id', $user->company_id);
        }

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
                $categoriesQuery->where('company_id', $selectedCompanyId);
                $documentsQuery->where('company_id', $selectedCompanyId);
            } elseif (! $user->hasGroupScope()) {
                $categoriesQuery->where('company_id', $user->company_id);
                $documentsQuery->where('company_id', $user->company_id);
            }

            $matchingCategories = $categoriesQuery->orderBy('name')->get();
            $matchingDocuments  = $documentsQuery->orderBy('name')->get();
        }

        $folders = $foldersQuery
            ->with('company')
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

        $folder->load('company');

        abort_unless($user->canAccessCompany($folder->company), 403);

        $categories = DocumentFolder::query()
            ->withCount('documents')
            ->where('parent_id', $folder->id)
            ->where('level', 'category')
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('documents.folder', [
            'folder' => $folder,
            'categories' => $categories,
        ]);
    }

    public function showCategory(Request $request, DocumentFolder $category)
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany($category->company), 403);

        $category->load('parent');

        $documents = Document::query()
            ->with(['currentVersion', 'folder'])
            ->where('document_folder_id', $category->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $users = User::query()
            ->where('group_id', $category->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('documents.category', [
            'category'  => $category,
            'documents' => $documents,
            'users'     => $users,
        ]);
    }

    public function store(Request $request, DocumentFolder $category)
    {
        $user = auth()->user();

        abort_unless($user->canAccessCompany($category->company), 403);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createDocument', [
            'name'                    => ['required', 'string', 'max:255'],
            'reference'               => ['nullable', 'string', 'max:255'],
            'document_type'           => ['nullable', 'string', 'max:255'],
            'responsible_name'        => ['nullable', 'string', 'max:255'],
            'authorized_access_notes' => ['nullable', 'string', 'max:1000'],
            'is_required'             => ['nullable', 'boolean'],
        ]);

        Document::create([
            'group_id'                => $category->group_id,
            'company_id'              => $category->company_id,
            'document_folder_id'      => $category->id,
            'name'                    => strtoupper($data['name']),
            'reference'               => $data['reference'] ?? null,
            'document_type'           => $data['document_type'] ?? null,
            'responsible_name'        => $data['responsible_name'] ?? null,
            'authorized_access_notes' => $data['authorized_access_notes'] ?? null,
            'is_required'             => !empty($data['is_required']),
            'is_active'               => true,
            'uploaded_by'             => $user->id,
        ]);

        return redirect()
            ->route('documents.categories.show', $category)
            ->with('success', 'Documento creado correctamente.');
    }
}