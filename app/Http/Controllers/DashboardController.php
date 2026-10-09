<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class DashboardController extends Controller
{
    public function __invoke()
    {
        $user = auth()->user();

        $cacheKey = match (true) {
            $user->hasGroupScope() => "dashboard:group:{$user->group_id}",
            $user->hasMultipleCompanies() => 'dashboard:companies:' . implode(',', $user->accessibleCompanyIds()),
            default => "dashboard:company:{$user->company_id}",
        };

        [$stats, $dueSoon] = Cache::remember($cacheKey, now()->addSeconds(60), function () use ($user) {
            $today = Carbon::today();
            $soonLimit = $today->copy()->addDays(30);

            $scopeCompanyQuery = function ($query) use ($user) {
                if ($user->hasGroupScope()) {
                    $query->whereHas('company', function ($subQuery) use ($user) {
                        $subQuery->where('group_id', $user->group_id);
                    });
                } elseif ($user->hasMultipleCompanies()) {
                    $query->whereIn('company_id', $user->accessibleCompanyIds());
                } else {
                    $query->where('company_id', $user->company_id);
                }
            };

            $assetsCount = Asset::query()
                ->tap($scopeCompanyQuery)
                ->count();

            $pendingTasksCount = Task::query()
                ->where('status', 'pending')
                ->whereHas('requirement', $scopeCompanyQuery)
                ->count();

            $dueSoonCount = AssetRequirement::query()
                ->tap($scopeCompanyQuery)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $soonLimit])
                ->count();

            $overdueCount = AssetRequirement::query()
                ->tap($scopeCompanyQuery)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->where('due_date', '<', $today)
                ->count();

            $dueSoon = AssetRequirement::query()
                ->with([
                    'asset:id,name,code',
                    'template:id,name',
                    'company:id,name,group_id',
                ])
                ->tap($scopeCompanyQuery)
                ->where('status', '!=', 'completed')
                ->whereNotNull('due_date')
                ->whereBetween('due_date', [$today, $soonLimit])
                ->orderBy('due_date')
                ->limit(10)
                ->get()
                ->map(function ($requirement) use ($user) {
                    return [
                        'title' => $requirement->template?->name ?? $requirement->type,
                        'asset_name' => $requirement->asset?->name,
                        'asset_code' => $requirement->asset?->code,
                        'company_name' => ($user->hasGroupScope() || $user->hasMultipleCompanies()) ? $requirement->company?->name : null,
                        'due_date' => optional($requirement->due_date)->format('Y-m-d'),
                        'risk' => $requirement->risk_level ?? null,
                    ];
                });

            $stats = [
                'assets' => $assetsCount,
                'tasks' => $pendingTasksCount,
                'due_soon' => $dueSoonCount,
                'overdue' => $overdueCount,
            ];

            return [$stats, $dueSoon];
        });

        return view('dashboard', compact('stats', 'dueSoon'));
    }
}
