<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Application\Compliance\ComplianceDashboardService;
use App\Models\Asset;
use App\Models\Task;
use App\Models\AssetRequirement;
use App\Models\Company;
use App\Enums\RequirementStatus;
use Carbon\Carbon;

class ComplianceDashboardController extends Controller
{
    public function index(Request $request, ComplianceDashboardService $service)
    {
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return redirect()->route('superadmin.dashboard');
        }

        $companies = Company::query()
            ->when($user->isGlobalScope(), function ($query) {
                // global-scope non-superadmin: see all companies
            }, function ($query) use ($user) {
                if ($user->hasGroupScope()) {
                    $query->where('group_id', $user->group_id);
                } else {
                    $query->where('id', $user->company_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $companyId = $request->filled('company_id')
            ? (int) $request->company_id
            : ($user->company_id ?? $companies->first()?->id);

        $company = Company::findOrFail($companyId);

        abort_unless($user->canAccessCompany($company), 403);

        $today        = Carbon::today();
        $soonLimit    = Carbon::today()->addDays(30);
        $showAllTasks = $request->boolean('all_tasks', false);

        // ── Caché 1: datos de empresa (iguales para todos los usuarios) ──────
        [$metrics, $upcoming, $critical] = Cache::remember(
            "dash:c:{$companyId}",
            now()->addMinutes(15),
            function () use ($service, $companyId, $today) {
                $metrics = $service->metricsForCompany($companyId);

                $upcoming = AssetRequirement::query()
                    ->whereHas('asset', fn ($q) => $q->where('company_id', $companyId))
                    ->whereNotNull('due_date')
                    ->whereNotIn('status', [RequirementStatus::COMPLETED, RequirementStatus::CANCELLED, RequirementStatus::IN_TRANSIT])
                    ->whereDate('due_date', '>=', $today->toDateString())
                    ->whereDate('due_date', '<=', $today->copy()->addDays(30)->toDateString())
                    ->with(['asset.assetType', 'template'])
                    ->orderBy('due_date')
                    ->limit(10)
                    ->get();

                $critical = AssetRequirement::query()
                    ->whereHas('asset', fn ($q) => $q->where('company_id', $companyId))
                    ->whereNotNull('due_date')
                    ->whereNotIn('status', [RequirementStatus::COMPLETED, RequirementStatus::CANCELLED, RequirementStatus::IN_TRANSIT])
                    ->whereDate('due_date', '<=', $today->copy()->addDays(7)->toDateString())
                    ->with(['asset.assetType', 'template'])
                    ->orderBy('due_date')
                    ->limit(10)
                    ->get()
                    ->map(fn ($r) => tap($r, fn ($r) =>
                        $r->risk_level = $r->due_date->lt($today) ? 'expired' : 'warning'
                    ));

                return [$metrics, $upcoming, $critical];
            }
        );

        // ── Caché 2: tareas (dependen del usuario y del filtro all_tasks) ────
        $taskCacheKey = "dash:c:{$companyId}:u:{$user->id}:t:" . ($showAllTasks ? '1' : '0');

        [$stats, $tasksPending, $tasksDueSoon, $tasksOverdue] = Cache::remember(
            $taskCacheKey,
            now()->addMinutes(15),
            function () use ($companyId, $user, $today, $showAllTasks, $metrics) {
                $tasksQuery = Task::query()
                    ->with([
                        'requirement:id,asset_id,requirement_template_id,type',
                        'requirement.asset:id,name,code',
                        'requirement.template:id,name',
                    ])
                    ->whereHas('requirement', fn ($q) => $q->where('company_id', $companyId));

                if (! $showAllTasks) {
                    $tasksQuery->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
                }

                $soonLimit = $today->copy()->addDays(30);

                $stats = [
                    'assets'   => Asset::where('company_id', $companyId)->count(),
                    'tasks'    => (clone $tasksQuery)->whereNull('completed_at')->count(),
                    'due_soon' => $metrics['kpis']['warning'] + $metrics['kpis']['danger'],
                    'overdue'  => $metrics['kpis']['expired'],
                ];

                $tasksPending = (clone $tasksQuery)
                    ->whereNull('completed_at')->orderBy('due_date')->limit(10)->get();

                $tasksDueSoon = (clone $tasksQuery)
                    ->whereNull('completed_at')->whereNotNull('due_date')
                    ->whereDate('due_date', '>=', $today->toDateString())
                    ->whereDate('due_date', '<=', $soonLimit->toDateString())
                    ->orderBy('due_date')->limit(10)->get();

                $tasksOverdue = (clone $tasksQuery)
                    ->whereNull('completed_at')->whereNotNull('due_date')
                    ->whereDate('due_date', '<', $today->toDateString())
                    ->orderBy('due_date')->limit(10)->get();

                return [$stats, $tasksPending, $tasksDueSoon, $tasksOverdue];
            }
        );

        return view('dashboard', compact(
            'metrics',
            'stats',
            'upcoming',
            'critical',
            'tasksPending',
            'tasksDueSoon',
            'tasksOverdue',
            'showAllTasks',
            'companies',
            'companyId'
        ));
    }
}