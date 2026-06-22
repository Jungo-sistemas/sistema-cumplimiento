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

        // v2 — incluye chartData
        [$stats, $recent, $chartData] = Cache::remember(
            "dashboard:processes:v2:{$scopeKey}",
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

                // Gráfica 2: posición actual en el flujo (paso con aprobación pendiente)
                $rawByStep = RegulationApproval::whereIn('regulation_id', $allRegIds)
                    ->where('status', 'pending')
                    ->select('step_number', DB::raw('count(distinct regulation_id) as total'))
                    ->groupBy('step_number')
                    ->orderBy('step_number')
                    ->pluck('total', 'step_number');

                $stepData = [
                    (int) ($rawByStep[1] ?? 0),
                    (int) ($rawByStep[2] ?? 0),
                    (int) ($rawByStep[3] ?? 0),
                    (int) ($rawByStep[4] ?? 0),
                ];

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

                $chartData = [
                    'statusData'     => $statusData,
                    'stepData'       => $stepData,
                    'weeklyLabels'   => $weeklyLabels,
                    'weeklyApproved' => $weeklyApproved,
                    'weeklyRejected' => $weeklyRejected,
                    'impactData'     => $impactData,
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
