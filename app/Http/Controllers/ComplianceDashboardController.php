<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
            ->when($user->hasGroupScope(), function ($query) use ($user) {
                $query->where('group_id', $user->group_id);
            }, function ($query) use ($user) {
                $query->where('id', $user->company_id);
            })
            ->orderBy('name')
            ->get(['id', 'name']);

        $companyId = $request->filled('company_id')
            ? (int) $request->company_id
            : (int) $user->company_id;

        $company = Company::findOrFail($companyId);

        abort_unless($user->canAccessCompany($company), 403);

        $today = Carbon::today();
        $soonLimit = Carbon::today()->addDays(30);

        $showAllTasks = $request->boolean('all_tasks', false);

        $metrics = $service->metricsForCompany($companyId);

        $tasksBaseQuery = Task::query()
            ->whereHas('requirement', fn ($q) => $q->where('company_id', $companyId));

        if (! $showAllTasks) {
            $tasksBaseQuery->whereHas('users', fn ($q) => $q->where('users.id', $user->id));
        }

        $stats = [
            'assets' => Asset::query()
                ->where('company_id', $companyId)
                ->count(),

            'tasks' => (clone $tasksBaseQuery)
                ->whereNull('completed_at')
                ->count(),

            'due_soon' => $metrics['kpis']['warning'] + $metrics['kpis']['danger'],
            'overdue' => $metrics['kpis']['expired'],
        ];

        $upcoming = AssetRequirement::query()
            ->whereHas('asset', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('due_date')
            ->whereNotIn('status', [
                RequirementStatus::COMPLETED,
                RequirementStatus::CANCELLED,
            ])
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $soonLimit->toDateString())
            ->with(['asset.assetType', 'template'])
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $critical = AssetRequirement::query()
            ->whereHas('asset', fn ($q) => $q->where('company_id', $companyId))
            ->whereNotNull('due_date')
            ->whereNotIn('status', [
                RequirementStatus::COMPLETED,
                RequirementStatus::CANCELLED,
            ])
            ->whereDate('due_date', '<=', now()->addDays(7)->toDateString())
            ->with(['asset.assetType', 'template'])
            ->orderBy('due_date')
            ->limit(10)
            ->get()
            ->map(function ($r) use ($today) {
                $r->risk_level = $r->due_date && $r->due_date->lt($today) ? 'expired' : 'warning';
                return $r;
            });

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

        $tasksPending = (clone $tasksQuery)
            ->whereNull('completed_at')
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $tasksDueSoon = (clone $tasksQuery)
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today->toDateString())
            ->whereDate('due_date', '<=', $soonLimit->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

        $tasksOverdue = (clone $tasksQuery)
            ->whereNull('completed_at')
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', $today->toDateString())
            ->orderBy('due_date')
            ->limit(10)
            ->get();

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