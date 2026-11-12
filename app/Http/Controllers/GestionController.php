<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Inicio de Gestión del módulo "Procesos y Auditorías": un resumen de los dos lados y una sola
 * lista de lo que requiere atención. El detalle está en Indicadores de procesos (solo admins) y,
 * cuando existan las tablas de auditorías, en Indicadores de auditorías.
 */
class GestionController extends Controller
{
    // Días antes del vencimiento en que un proceso se considera "por vencer".
    private const DUE_SOON_DAYS = 30;

    public function home(Request $request)
    {
        $user = $request->user();

        if ($user->isAuditeeOnly()) {
            return redirect()->route('mi-espacio.home');
        }

        // El auditor tiene su propio Inicio en Auditorías; los procesos solo los consulta desde el menú.
        if ($user->isAuditor()) {
            return redirect()->route('auditorias.home');
        }

        $base = $this->processesFor($user);

        $released = (clone $base)->where('approval_status', 'approved')
            ->whereHas('currentVersion', fn ($v) => $v->where(fn ($q) => $q->whereNull('valid_until')->orWhereDate('valid_until', '>=', today())))
            ->count();

        $dueSoon = (clone $base)->where('approval_status', 'approved')
            ->whereHas('currentVersion', fn ($v) => $v->whereDate('valid_until', '>=', today())->whereDate('valid_until', '<=', today()->addDays(self::DUE_SOON_DAYS)))
            ->with(['company:id,name,process_name', 'currentVersion'])
            ->get();

        $myApprovals = RegulationApproval::where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereHas('regulation')
            ->with('regulation.company:id,name,process_name')
            ->orderBy('created_at')
            ->get();

        $rejected = (clone $base)->where('approval_status', 'rejected')
            ->with('company:id,name,process_name')
            ->latest('updated_at')
            ->limit(5)
            ->get();

        // Una sola lista, lo más urgente primero: lo que te toca aprobar, lo rechazado por corregir
        // y lo que está por vencer.
        $attention = collect()
            ->merge($myApprovals->map(fn ($a) => [
                'area' => 'Procesos', 'name' => $a->regulation->name, 'company' => $a->regulation->company?->procesos_name,
                'state' => 'Te toca aprobar', 'tone' => 'warn', 'when' => $a->created_at, 'url' => route('my-approvals.index'),
            ]))
            ->merge($rejected->map(fn ($r) => [
                'area' => 'Procesos', 'name' => $r->name, 'company' => $r->company?->procesos_name,
                'state' => 'Rechazado, por corregir', 'tone' => 'bad', 'when' => $r->updated_at, 'url' => route('processes.show', $r),
            ]))
            ->merge($dueSoon->sortBy('currentVersion.valid_until')->map(fn ($r) => [
                'area' => 'Procesos', 'name' => $r->name, 'company' => $r->company?->procesos_name,
                'state' => 'Vence el ' . $r->currentVersion->valid_until->format('d/m/Y'), 'tone' => 'warn',
                'when' => $r->currentVersion->valid_until, 'url' => route('processes.show', $r),
            ]))
            ->take(10);

        return view('gestion.home', [
            'total'         => (clone $base)->count(),
            'released'      => $released,
            'inReview'      => (clone $base)->whereIn('approval_status', ['pending_review', 'pending_authorization'])->count(),
            'myApprovals'   => $myApprovals->count(),
            'dueSoon'       => $dueSoon->count(),
            'attention'     => $attention,
            'showAudits'    => $user->isAdmin() || $user->isAuditor(),
        ]);
    }

    /** Procesos vigentes (no anexos ni obsoletos) de las empresas a las que el usuario tiene acceso. */
    private function processesFor(User $user): Builder
    {
        return Regulation::query()
            ->where('group_id', $user->group_id)
            ->where('is_active', true)
            ->where('is_annex', false)
            ->whereNull('obsoleted_at')
            ->whereHas('company', fn ($q) => $q->where('show_in_processes', true)->where('otras', false))
            ->when(! $user->hasGroupScope() && ! $user->isGlobalScope(), fn ($q) => $q->whereIn('company_id', $user->accessibleCompanyIds()));
    }
}
