<?php

namespace App\Http\Controllers;

use App\Enums\TaskStatus;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\RequirementTemplate;
use App\Models\User;
use App\Services\LicenseService;
use App\Services\SyncAssetRequirementsService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AssetController extends Controller
{
    public function __construct(
        private SyncAssetRequirementsService $syncAssetRequirementsService,
        private LicenseService $licenseService,
    ) {
        $this->authorizeResource(Asset::class, 'asset');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $assetTypes = AssetType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $allCompanies = Company::query()
            ->when($user->hasGroupScope(), function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            }, function ($query) use ($user) {
                $query->where('id', $user->company_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'otras']);

        $companies      = $allCompanies->where('otras', false)->values();
        $otrasCompanies = $allCompanies->where('otras', true)->values();

        // Determine filter state
        $filterOtras       = $request->boolean('otras');
        $selectedCompanyId = $request->filled('company_id') && is_numeric($request->company_id)
            ? (int) $request->company_id
            : null;

        // State to restore Alpine.js selects on page load
        $filterGrupo  = $filterOtras ? 'otras' : ($selectedCompanyId ? (string) $selectedCompanyId : '');
        $filterOtraId = ($filterOtras && $selectedCompanyId) ? (string) $selectedCompanyId : '';

        if ($selectedCompanyId) {
            $selectedCompany = Company::findOrFail($selectedCompanyId);
            abort_unless($user->canAccessCompany($selectedCompany), 403);
        }

        $query = Asset::query()
            ->with([
                'type:id,name',
                'company:id,name,group_id',
                'responsibleUser:id,name',
                'creator:id,name',
            ])
            ->when($user->hasGroupScope(), function ($query) use ($user) {
                $query->whereHas('company', function ($subQuery) use ($user) {
                    $subQuery->where('group_id', $user->group_id);
                });
            }, function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            });

        if ($filterOtras) {
            if ($selectedCompanyId) {
                $query->where('company_id', $selectedCompanyId);
            } else {
                $query->whereHas('company', fn ($q) => $q->where('otras', true));
            }
        } elseif ($selectedCompanyId) {
            $query->where('company_id', $selectedCompanyId);
        }

        if ($request->filled('status') && in_array($request->status, ['active', 'inactive'], true)) {
            $query->where('status', $request->status);
        }

        if ($request->filled('asset_type_id')) {
            $query->where('asset_type_id', (int) $request->asset_type_id);
        }

        if ($request->filled('q')) {
            $q = trim($request->q);
            $query->where('name', 'like', "%{$q}%");
        }

        if ($request->filled('location')) {
            $query->whereRaw('UPPER(TRIM(location)) = ?', [strtoupper(trim($request->location))]);
        }

        $assets = $query
            ->latest('id')
            ->paginate(15)
            ->withQueryString();

        $locationsQuery = Asset::query()
            ->when($user->hasGroupScope(), function ($query) use ($user) {
                $query->whereHas('company', function ($subQuery) use ($user) {
                    $subQuery->where('group_id', $user->group_id);
                });
            }, function ($query) use ($user) {
                $query->where('company_id', $user->company_id);
            });

        if ($filterOtras) {
            if ($selectedCompanyId) {
                $locationsQuery->where('company_id', $selectedCompanyId);
            } else {
                $locationsQuery->whereHas('company', fn ($q) => $q->where('otras', true));
            }
        } elseif ($selectedCompanyId) {
            $locationsQuery->where('company_id', $selectedCompanyId);
        }

        $locations = $locationsQuery
            ->whereNotNull('location')
            ->where('location', '!=', '')
            ->distinct()
            ->orderBy('location')
            ->pluck('location');

        // License info for the current context
        $licenseCompany = $selectedCompanyId
            ? Company::find($selectedCompanyId)
            : ($user->hasGroupScope() ? null : Company::find($user->company_id));

        $licenseInfo = $licenseCompany ? $this->licenseService->info($licenseCompany) : null;

        return view('assets.index', compact(
            'assets', 'assetTypes', 'locations', 'companies', 'otrasCompanies',
            'selectedCompanyId', 'filterGrupo', 'filterOtraId', 'licenseInfo'
        ));
    }

    public function create(Request $request)
    {
        $user = $request->user();

        $assetTypes = AssetType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $companies = Company::query()
            ->when($user->hasGroupScope(), function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            }, function ($query) use ($user) {
                $query->where('id', $user->company_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $selectedCompanyId = $request->filled('company_id')
            ? (int) $request->company_id
            : (int) $user->company_id;

        $selectedCompany = Company::findOrFail($selectedCompanyId);

        abort_unless($user->canAccessCompany($selectedCompany), 403);

        $licenseInfo = $this->licenseService->info($selectedCompany);

        if ($licenseInfo['at_limit']) {
            return redirect()
                ->route('assets.index')
                ->with('license_limit', true);
        }

        $parentAssets = Asset::query()
            ->where('company_id', $selectedCompany->id)
            ->whereHas('type', function ($query) {
                $query->whereIn('name', ['Plantas', 'Transporte']);
            })
            ->with('type:id,name')
            ->orderBy('name')
            ->get();

        $responsibles = User::query()
            ->where('company_id', $selectedCompany->id)
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $mexicoStates = [
            'Aguascalientes',
            'Baja California',
            'Baja California Sur',
            'Campeche',
            'Coahuila',
            'Colima',
            'Chiapas',
            'Chihuahua',
            'Ciudad de México',
            'Durango',
            'Guanajuato',
            'Guerrero',
            'Hidalgo',
            'Jalisco',
            'México',
            'Michoacán',
            'Morelos',
            'Nayarit',
            'Nuevo León',
            'Oaxaca',
            'Puebla',
            'Querétaro',
            'Quintana Roo',
            'San Luis Potosí',
            'Sinaloa',
            'Sonora',
            'Tabasco',
            'Tamaulipas',
            'Tlaxcala',
            'Veracruz',
            'Yucatán',
            'Zacatecas',
        ];

        $vehicleTypeIds = AssetType::whereIn('name', \App\Models\Asset::VEHICLE_TYPES)
            ->pluck('id')
            ->toArray();

        return view('assets.create', compact(
            'assetTypes',
            'responsibles',
            'mexicoStates',
            'parentAssets',
            'companies',
            'selectedCompanyId',
            'licenseInfo',
            'vehicleTypeIds'
        ));
    }

    public function store(StoreAssetRequest $request)
    {
        $data = $request->validated();
        $user = $request->user();

        $company = Company::findOrFail((int) $data['company_id']);

        abort_unless($user->canAccessCompany($company), 403);

        if (! $this->licenseService->hasCapacity($company)) {
            return redirect()
                ->route('assets.index')
                ->with('license_limit', true);
        }

        if (! empty($data['code'])) {
            $data['code'] = Str::upper(trim($data['code']));
        }

        if (empty($data['code'])) {
            $namePart = Str::upper(Str::substr(Str::slug($data['name'], ''), 0, 10));

            $type = AssetType::find($data['asset_type_id']);
            $typePart = $type?->code
                ? Str::upper(Str::slug($type->code, ''))
                : Str::upper(Str::substr(Str::slug($type?->name ?? 'TIPO', ''), 0, 6));

            $prefix = "{$namePart}-{$typePart}-";

            $last = Asset::query()
                ->where('company_id', $company->id)
                ->where('code', 'like', $prefix . '%')
                ->orderBy('code', 'desc')
                ->value('code');

            $nextNumber = 1;

            if ($last) {
                $lastNumber = (int) Str::afterLast($last, '-');
                $nextNumber = $lastNumber + 1;
            }

            $data['code'] = $prefix . str_pad((string) $nextNumber, 3, '0', STR_PAD_LEFT);
        }

        $asset = DB::transaction(function () use ($company, $data) {
            $asset = Asset::create([
                'company_id' => $company->id,
                ...$data,
            ]);

            $this->syncAssetRequirementsService->handle($asset);

            return $asset;
        });

        return redirect()
            ->route('assets.show', $asset)
            ->with('status', 'Activo creado.');
    }

    private function generateAssetCode(int $companyId): string
    {
        $lastNumeric = Asset::query()
            ->where('company_id', $companyId)
            ->whereNotNull('code')
            ->orderByDesc('id')
            ->value('code');

        $n = is_numeric($lastNumeric) ? ((int) $lastNumeric + 1) : 1;

        return str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    }

    public function show(Asset $asset)
    {
        $this->authorize('view', $asset);

        $asset->load([
            'parent.assetType',
            'children.assetType',
        ]);

        $search = trim((string) request('search'));
        $authority = trim((string) request('authority'));
        $risk = trim((string) request('risk'));
        $status = trim((string) request('status'));
        $showFilters = request()->boolean('show_filters')
            || $authority !== ''
            || $risk !== ''
            || $status !== '';

        $assetInactive = ($asset->status ?? null) === \App\Models\Asset::STATUS_INACTIVE
            || (method_exists($asset, 'isInactive') && $asset->isInactive());

        // Detectar si este tipo de activo usa categorías (Alta/Baja/Expediente)
        $usesCategoryView = RequirementTemplate::where('asset_type_id', $asset->asset_type_id)
            ->whereNotNull('category')
            ->where('category', '!=', '')
            ->exists();

        $categoryTabs = RequirementTemplate::CATEGORIES; // ['expediente'=>'Expediente','alta'=>'Alta / Modificación','baja'=>'Baja']

        $scope = request()->get('scope', $usesCategoryView ? 'expediente' : 'project');

        if ($usesCategoryView) {
            $scopeTitle       = $categoryTabs[$scope] ?? ucfirst($scope);
            $scopeDescription = 'Visualiza el avance y estado de los requerimientos de ' . strtolower($scopeTitle) . '.';
        } else {
            $scopeTitle = $scope === 'operation'
                ? 'Normativa de operación'
                : 'Normativa de proyecto';
            $scopeDescription = $scope === 'operation'
                ? 'Visualiza el avance, riesgo y estado de cada carpeta de cumplimiento en operación.'
                : 'Visualiza el avance, riesgo y estado de cada carpeta de cumplimiento del proyecto.';
        }

        $requirementsQuery = AssetRequirement::query()
            ->with(['template'])
            ->where('asset_id', $asset->id)
            ->when($usesCategoryView,
                fn ($q) => $q->whereHas('template', fn ($tq) => $tq->where('category', $scope)),
                fn ($q) => $q->where('compliance_scope', $scope)
            )
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($subQuery) use ($search) {
                    $subQuery->whereHas('template', function ($templateQuery) use ($search) {
                        $templateQuery->where('name', 'ilike', "%{$search}%");
                    })->orWhere('type', 'ilike', "%{$search}%");
                });
            })
            ->when($authority !== '', function ($query) use ($authority) {
                $query->whereHas('template', function ($templateQuery) use ($authority) {
                    $templateQuery->where('authority', $authority);
                });
            })
            ->withCount([
                'tasks as tasks_total'     => fn ($q) => $q->where('status', '!=', 'cancelled'),
                'tasks as tasks_done'      => fn ($q) => $q->where('status', TaskStatus::COMPLETED),
                'tasks as renewal_pending' => fn ($q) => $q->where('type', 'renewal')->whereNotIn('status', ['completed', 'cancelled']),
                'tasks as checkin_pending' => fn ($q) => $q->where('type', 'checkin')->whereNotIn('status', ['completed', 'cancelled']),
            ])
            ->orderBy('id');

        $requirementsCollection = $requirementsQuery->get()->map(function ($requirement) {
            $expiresAt = $requirement->expires_at ?? $requirement->due_date;

            $riskLevel = 'normal';

            if ($expiresAt) {
                $daysToExpire = now()->startOfDay()->diffInDays($expiresAt->startOfDay(), false);

                if ($daysToExpire < 0) {
                    $riskLevel = 'danger';
                } elseif ($daysToExpire <= 30) {
                    $riskLevel = 'warning';
                }
            }

            $tasksTotal = (int) ($requirement->tasks_total ?? 0);
            $tasksDone = (int) ($requirement->tasks_done ?? 0);

            $hasOfficialDocument = ! is_null($requirement->current_document_id);

            $computedStatus = $requirement->status?->value ?? $requirement->status ?? 'pending';

            if (! $hasOfficialDocument) {
                $computedStatus = 'missing_document';
            } elseif ($riskLevel === 'danger') {
                $computedStatus = 'expired';
            } elseif ($tasksTotal > 0 && $tasksDone === $tasksTotal) {
                $computedStatus = 'completed';
            } elseif ($tasksDone > 0 && $tasksDone < $tasksTotal) {
                $computedStatus = 'in_progress';
            } else {
                $computedStatus = $computedStatus ?: 'pending';
            }

            $progress = 0;

            if ($hasOfficialDocument) {
                if ($tasksTotal > 0) {
                    $progress = (int) round(($tasksDone / max($tasksTotal, 1)) * 100);
                } else {
                    $progress = 100;
                }
            } else {
                if ($tasksTotal > 0 && $tasksDone > 0) {
                    $progress = min((int) round(($tasksDone / max($tasksTotal, 1)) * 100), 80);
                } else {
                    $progress = 0;
                }
            }

            $requirement->risk_level = $riskLevel;
            $requirement->computed_status = $computedStatus;
            $requirement->computed_progress = $progress;
            $requirement->has_official_document = $hasOfficialDocument;
            $requirement->renewal_pending  = (int) ($requirement->renewal_pending ?? 0);
            $requirement->checkin_pending  = (int) ($requirement->checkin_pending ?? 0);

            return $requirement;
        });

        $requirementsCollection = $requirementsCollection
            ->when($risk !== '', function (Collection $collection) use ($risk) {
                return $collection->filter(fn ($item) => ($item->risk_level ?? '') === $risk);
            })
            ->when($status !== '', function (Collection $collection) use ($status) {
                return $collection->filter(fn ($item) => ($item->computed_status ?? '') === $status);
            })
            ->values();

        $perPage = 10;
        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $currentItems = $requirementsCollection->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $requirements = new LengthAwarePaginator(
            $currentItems,
            $requirementsCollection->count(),
            $perPage,
            $currentPage,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );

        $authorities = RequirementTemplate::query()
            ->where('asset_type_id', $asset->asset_type_id)
            ->whereNotNull('authority')
            ->where('authority', '!=', '')
            ->distinct()
            ->orderBy('authority')
            ->pluck('authority');

        $navContext = [
            'asset' => $asset,
            'requirement' => null,
            'task' => null,
            'documentSection' => false,
        ];

        return view('assets.show', compact(
            'asset',
            'requirements',
            'scope',
            'scopeTitle',
            'scopeDescription',
            'assetInactive',
            'navContext',
            'search',
            'authority',
            'risk',
            'status',
            'authorities',
            'showFilters',
            'usesCategoryView',
            'categoryTabs'
        ));
    }

    public function edit(Request $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $asset->load(['assetType', 'responsible', 'company']);

        $assetTypes = AssetType::query()
            ->orderBy('name')
            ->get(['id', 'name']);

        $parentAssets = Asset::query()
            ->where('company_id', $asset->company_id)
            ->where('id', '!=', $asset->id)
            ->whereHas('assetType', function ($query) {
                $query->whereIn('name', ['Plantas', 'Transporte']);
            })
            ->with('assetType')
            ->orderBy('name')
            ->get();

        $groupId = $asset->company?->group_id;
        $responsibles = User::query()
            ->when($user->isGlobalScope(), fn ($q) => $q, function ($q) use ($user, $asset, $groupId) {
                if ($user->hasGroupScope()) {
                    $q->where('group_id', $groupId ?? $user->group_id);
                } else {
                    $q->where('company_id', $user->company_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $user = $request->user();
        $companies = Company::query()
            ->when($user->hasGroupScope(), fn ($q) => $q->where('group_id', $user->group_id))
            ->when(! $user->hasGroupScope() && ! $user->isGlobalScope(), fn ($q) => $q->where('id', $user->company_id))
            ->orderBy('name')
            ->get(['id', 'name']);

        $mexicoStates = [
            'Aguascalientes',
            'Baja California',
            'Baja California Sur',
            'Campeche',
            'Chiapas',
            'Chihuahua',
            'Ciudad de México',
            'Coahuila',
            'Colima',
            'Durango',
            'Estado de México',
            'Guanajuato',
            'Guerrero',
            'Hidalgo',
            'Jalisco',
            'Michoacán',
            'Morelos',
            'Nayarit',
            'Nuevo León',
            'Oaxaca',
            'Puebla',
            'Querétaro',
            'Quintana Roo',
            'San Luis Potosí',
            'Sinaloa',
            'Sonora',
            'Tabasco',
            'Tamaulipas',
            'Tlaxcala',
            'Veracruz',
            'Yucatán',
            'Zacatecas',
        ];

        $vehicleTypeIds = AssetType::whereIn('name', \App\Models\Asset::VEHICLE_TYPES)
            ->pluck('id')
            ->toArray();

        return view('assets.edit', compact('asset', 'responsibles', 'assetTypes', 'mexicoStates', 'parentAssets', 'vehicleTypeIds', 'companies'));
    }

    public function update(UpdateAssetRequest $request, Asset $asset)
    {
        $this->authorize('update', $asset);

        $oldAssetTypeId = (int) $asset->asset_type_id;

        DB::transaction(function () use ($request, $asset, $oldAssetTypeId) {
            $data = $request->validated();

            // Verify access to the new company if it changed
            if (! empty($data['company_id']) && (int) $data['company_id'] !== (int) $asset->company_id) {
                $newCompany = \App\Models\Company::findOrFail($data['company_id']);
                abort_unless($request->user()->canAccessCompany($newCompany), 403);
            }

            $asset->update($data);

            if ($oldAssetTypeId !== (int) $asset->asset_type_id) {
                $this->syncAssetRequirementsService->handle($asset, removeObsolete: true);
            }
        });

        return redirect()
            ->route('assets.show', $asset)
            ->with('status', 'Activo actualizado.');
    }

    public function destroy(Asset $asset)
    {
        $this->authorize('delete', $asset);

        if ($asset->requirements()->exists() || $asset->obligations()->exists()) {
            return back()->with('status', 'No se puede eliminar: el activo ya tiene obligaciones/requerimientos.');
        }

        $asset->delete();

        return redirect()
            ->route('assets.index')
            ->with('status', 'Activo eliminado.');
    }

    public function deactivate(Asset $asset)
    {
        $this->authorize('deactivate', $asset);

        $asset->update(['status' => Asset::STATUS_INACTIVE]);

        return redirect()
            ->route('assets.index')
            ->with('success', 'Activo desactivado.');
    }

    public function activate(Asset $asset)
    {
        $this->authorize('activate', $asset);

        $asset->update(['status' => Asset::STATUS_ACTIVE]);

        return back()->with('success', 'Activo reactivado.');
    }
}