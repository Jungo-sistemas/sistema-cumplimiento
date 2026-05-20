<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\User;
use Illuminate\Http\Request;

class RegulationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $selectedCompanyId = $user->hasGroupScope()
            ? ($request->filled('company_id') ? (int) $request->company_id : null)
            : (int) $user->company_id;

        $companies = $user->hasGroupScope()
            ? Company::where('group_id', $user->group_id)->orderBy('name')->get()
            : collect();

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

        if ($request->filled('status')) {
            // We filter in PHP after loading since status is computed
        }

        if ($request->filled('q')) {
            $search = '%' . strtoupper($request->q) . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', $search)
                  ->orWhere('code', 'like', $search);
            });
        }

        $regulations = $query->orderBy('code')->orderBy('name')->get();

        // Filter by status (computed) if requested
        if ($request->filled('status')) {
            $regulations = $regulations->filter(
                fn ($r) => $r->statusColor() === $request->status
            )->values();
        }

        return view('processes.index', [
            'regulations'       => $regulations,
            'processTypes'      => $processTypes,
            'companies'         => $companies,
            'selectedCompanyId' => $selectedCompanyId,
            'documentTypes'     => Regulation::DOCUMENT_TYPES,
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

        return view('processes.show', [
            'regulation'     => $regulation,
            'versionHistory' => $versionHistory,
            'currentVersion' => $currentVersion,
            'users'          => $users,
            'documentTypes'  => Regulation::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validateWithBag('createRegulation', [
            'company_id'      => ['required', 'exists:companies,id'],
            'process_type_id' => ['required', 'exists:process_types,id'],
            'document_type'   => ['nullable', 'string', 'in:' . implode(',', Regulation::DOCUMENT_TYPES)],
            'code'            => ['nullable', 'string', 'max:50'],
            'name'            => ['required', 'string', 'max:255'],
        ]);

        $company = Company::findOrFail($data['company_id']);
        abort_unless($user->canAccessCompany($company), 403);

        Regulation::create([
            'group_id'        => $user->group_id,
            'company_id'      => $company->id,
            'process_type_id' => $data['process_type_id'],
            'document_type'   => $data['document_type'] ?? null,
            'code'            => $data['code'] ? strtoupper($data['code']) : null,
            'name'            => strtoupper($data['name']),
            'is_active'       => true,
            'created_by'      => $user->id,
        ]);

        return redirect()
            ->route('processes.index')
            ->with('success', 'Reglamento creado correctamente.');
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
}
