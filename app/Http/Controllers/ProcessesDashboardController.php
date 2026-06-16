<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationApproval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ProcessesDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $scopeKey = $user->hasCompanyScope() ? "c{$user->company_id}" : "g{$user->group_id}";

        // Datos estáticos por empresa/grupo — 15 minutos
        [$stats, $recent] = Cache::remember(
            "dashboard:processes:{$scopeKey}",
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

                return [$stats, $recent];
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

        return view('processes.dashboard', compact('stats', 'recent', 'pendingApprovals'));
    }
}
