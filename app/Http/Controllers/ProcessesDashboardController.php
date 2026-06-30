<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationApproval;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProcessesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $scopeKey = $user->hasCompanyScope() ? "c{$user->company_id}" : "g{$user->group_id}";

        // v3 — incluye daysData por paso
        [$stats, $recent, $chartData] = Cache::remember(
            "dashboard:processes:v4:{$scopeKey}",
            now()->addMinutes(15),
            function () use ($user) {
                $query = Regulation::query()
                    ->where('group_id', $user->group_id)
                    ->where('is_active', true);

                if ($user->hasCompanyScope()) {
                    $query->where('company_id', $user->company_id);
                }

                $stats = [
                    'total'     => (clone $query)->count(),
                    'approved'  => (clone $query)->where('approval_status', 'approved')->count(),
                    'in_review' => (clone $query)->whereIn('approval_status', ['pending_review', 'pending_authorization'])->count(),
                ];

                $recent = (clone $query)
                    ->with(['processType', 'company', 'currentVersion'])
                    ->latest()
                    ->limit(10)
                    ->get();

                // ── Datos para gráficas ──────────────────────────────────────────
                $allRegIds = (clone $query)->pluck('id');

                // Gráfica 1: distribución por estado del flujo
                $rawByStatus = (clone $query)
                    ->select('approval_status', DB::raw('count(*) as total'))
                    ->groupBy('approval_status')
                    ->pluck('total', 'approval_status');

                $statusData = [
                    (int) ($rawByStatus['pending_review']        ?? 0),
                    (int) ($rawByStatus['pending_authorization'] ?? 0),
                    (int) ($rawByStatus['approved']              ?? 0),
                    (int) ($rawByStatus['rejected']              ?? 0),
                    (int) ((clone $query)->whereNull('approval_status')->count()),
                ];

                // Gráfica 2: días promedio por paso del flujo, agrupado por estado
                // Etiquetas dinámicas: nombres de puestos que aparecen en cada paso
                $stepPositionNames = RegulationApproval::whereIn('regulation_id', $allRegIds)
                    ->join('job_positions', 'regulation_approvals.job_position_id', '=', 'job_positions.id')
                    ->select('regulation_approvals.step_number', 'job_positions.name', 'job_positions.sort_order')
                    ->distinct()
                    ->orderBy('regulation_approvals.step_number')
                    ->orderBy('job_positions.sort_order')
                    ->get()
                    ->groupBy('step_number')
                    ->map(fn ($rows) => $rows->pluck('name')->implode('/'));

                $stepLabels = [];
                for ($s = 1; $s <= 4; $s++) {
                    $pos = $stepPositionNames[$s] ?? null;
                    $stepLabels[] = $pos ? "Paso {$s} · {$pos}" : "Paso {$s}";
                }

                $rawApprovals = RegulationApproval::whereIn('regulation_id', $allRegIds)
                    ->whereIn('status', ['approved', 'rejected', 'pending'])
                    ->select(['step_number', 'status', 'created_at', 'decided_at', 'regulation_id'])
                    ->get();

                $groups = ['approved' => [], 'rejected' => [], 'pending' => []];
                foreach ($rawApprovals as $row) {
                    $idx = $row->step_number - 1;
                    if ($idx < 0 || $idx > 3) continue;
                    $end  = $row->decided_at ? Carbon::parse($row->decided_at) : now();
                    $days = (int) Carbon::parse($row->created_at)->diffInDays($end);
                    $groups[$row->status][$idx][] = ['days' => $days, 'reg_id' => $row->regulation_id];
                }

                $daysData = ['stepLabels' => $stepLabels];
                foreach (['approved', 'rejected', 'pending'] as $status) {
                    $countKey            = 'count' . ucfirst($status);
                    $daysData[$status]   = [];
                    $daysData[$countKey] = [];
                    for ($idx = 0; $idx < 4; $idx++) {
                        $entries = $groups[$status][$idx] ?? [];
                        if (empty($entries)) {
                            $daysData[$status][]   = null;
                            $daysData[$countKey][] = 0;
                        } else {
                            $totalDays             = array_sum(array_column($entries, 'days'));
                            $daysData[$status][]   = round($totalDays / count($entries), 1);
                            $daysData[$countKey][] = count(array_unique(array_column($entries, 'reg_id')));
                        }
                    }
                }

                // Gráfica 3: actividad semanal — últimas 8 semanas
                $since = now()->startOfWeek()->subWeeks(7);

                $decisions = RegulationApproval::whereIn('regulation_id', $allRegIds)
                    ->whereNotNull('decided_at')
                    ->whereIn('status', ['approved', 'rejected'])
                    ->where('decided_at', '>=', $since)
                    ->get(['decided_at', 'status']);

                $weeklyLabels   = [];
                $weeklyApproved = array_fill(0, 8, 0);
                $weeklyRejected = array_fill(0, 8, 0);

                for ($i = 0; $i < 8; $i++) {
                    $weeklyLabels[] = $since->copy()->addWeeks($i)->format('d/m');
                }

                foreach ($decisions as $d) {
                    $diff = (int) $since->diffInWeeks(
                        Carbon::parse($d->decided_at)->startOfWeek(),
                        false
                    );
                    if ($diff >= 0 && $diff < 8) {
                        if ($d->status === 'approved') {
                            $weeklyApproved[$diff]++;
                        } else {
                            $weeklyRejected[$diff]++;
                        }
                    }
                }

                // Gráfica 4: distribución por nivel de impacto
                $rawByImpact = (clone $query)
                    ->whereNotNull('impact_level')
                    ->select('impact_level', DB::raw('count(*) as total'))
                    ->groupBy('impact_level')
                    ->pluck('total', 'impact_level');

                $impactData = [
                    (int) ($rawByImpact['alto']       ?? 0),
                    (int) ($rawByImpact['medio_alto']  ?? 0),
                    (int) ($rawByImpact['medio']       ?? 0),
                    (int) ($rawByImpact['bajo']        ?? 0),
                ];

                // Ranking de personas con más aprobaciones pendientes
                $pendingByUser = RegulationApproval::whereIn('regulation_id', $allRegIds)
                    ->where('status', 'pending')
                    ->join('users', 'regulation_approvals.user_id', '=', 'users.id')
                    ->select(
                        'users.name',
                        DB::raw('count(*) as pending_count'),
                        DB::raw('min(regulation_approvals.created_at) as oldest_at')
                    )
                    ->groupBy('users.name')
                    ->orderByDesc('pending_count')
                    ->get()
                    ->map(fn ($row) => [
                        'name'        => $row->name,
                        'count'       => (int) $row->pending_count,
                        'oldest_days' => (int) Carbon::parse($row->oldest_at)->diffInDays(now()),
                    ])
                    ->all();

                $chartData = [
                    'statusData'     => $statusData,
                    'daysData'       => $daysData,
                    'weeklyLabels'   => $weeklyLabels,
                    'weeklyApproved' => $weeklyApproved,
                    'weeklyRejected' => $weeklyRejected,
                    'impactData'     => $impactData,
                    'pendingByUser'  => $pendingByUser,
                ];

                return [$stats, $recent, $chartData];
            }
        );

        // Aprobaciones pendientes del usuario — caché corta (2 min) porque cambia frecuente
        $pendingApprovals = Cache::remember(
            "dashboard:processes:approvals:u{$user->id}",
            now()->addMinutes(2),
            fn () => RegulationApproval::where('user_id', $user->id)
                ->where('status', 'pending')
                ->with(['regulation.company', 'regulation.processType'])
                ->get()
        );

        $stats['pending_me'] = $pendingApprovals->count();

        return view('processes.dashboard', compact('stats', 'recent', 'pendingApprovals', 'chartData'));
    }
}
