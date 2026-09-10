<?php

namespace App\Services;

use App\Models\JobPosition;
use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\User;
use App\Notifications\ApprovalFlowMemberNotification;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\ApprovalStepUnassignedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationAccessRequestedNotification;
use App\Notifications\RegulationReadyToResubmitNotification;
use App\Notifications\RegulationRejectedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalFlowService
{
    /**
     * Flujos definidos por nivel de impacto.
     * Cada paso es un array de posición-slug => lógica.
     * 'requires_all' => true  = AND: todos los usuarios de TODOS los puestos deben aprobar.
     * 'requires_all' => false = OR:  cualquier usuario de cualquier puesto en el paso basta.
     */
    /**
     * Flujos bottom-up: cada paso espera a que el anterior se complete.
     * Jerarquía: lider (1) → jefe (2) → gerente (3) → direccion (4)
     */
    private const FLOWS = [
        'bajo' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
        ],
        'medio' => [
            1 => ['requires_all' => true,  'positions' => ['lider']],
            2 => ['requires_all' => false, 'positions' => ['jefe', 'gerente']],
        ],
        'medio_alto' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
            2 => ['requires_all' => true, 'positions' => ['gerente']],
            3 => ['requires_all' => true, 'positions' => ['direccion']],
        ],
        'alto' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
            2 => ['requires_all' => true, 'positions' => ['jefe']],
            3 => ['requires_all' => true, 'positions' => ['gerente']],
            4 => ['requires_all' => true, 'positions' => ['direccion']],
        ],
    ];

    public static function getFlowSteps(string $level): array
    {
        return self::FLOWS[$level] ?? [];
    }

    public static function getAllFlows(): array
    {
        return self::FLOWS;
    }

    /**
     * Inicializa el flujo de aprobación al crear un reglamento.
     */
    public function initFlow(Regulation $regulation, array $userMap = []): void
    {
        DB::transaction(function () use ($regulation, $userMap) {
            $regulation->approvals()->delete();
            $this->createStepRecords($regulation, 1, $userMap);
        });

        $this->notifyPendingApprovers($regulation, 1);
        $this->notifyFutureFlowMembers($regulation, $userMap);
    }

    /**
     * Procesa la decisión de un aprobador (approve / reject).
     */
    public function processApproval(RegulationApproval $approval, string $status, ?string $comments = null): void
    {
        DB::transaction(function () use ($approval, $status, $comments) {
            $approval->update([
                'status'     => $status,
                'comments'   => $comments,
                'decided_at' => now(),
            ]);

            $regulation = $approval->regulation;
            $userMap = $regulation->flow_user_map ?? [];

            if ($status === 'rejected') {
                // "waiting" incluido: si alguien a media fila de un paso secuencial rechaza, no
                // debe quedar el resto de la fila colgado en waiting para siempre.
                $regulation->approvals()
                    ->whereIn('status', ['pending', 'waiting'])
                    ->update(['status' => 'cancelled']);
                $regulation->update(['approval_status' => 'rejected']);
                $this->notifyCreator($regulation, 'rejected', $comments, $approval->user);
                return;
            }

            if (! $approval->requires_all) {
                $regulation->approvalStep($approval->step_number)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }

            // Paso secuencial ("requires_all" con varios aprobadores): si queda alguien esperando
            // su turno en este mismo paso, le toca ahora — el paso no avanza hasta que también
            // decida (por eso se corta aquí y no se evalúa isStepComplete todavía).
            if ($this->promoteNextWaitingApprover($regulation, $approval->step_number)) {
                return;
            }

            if ($this->isStepComplete($regulation, $approval->step_number)) {
                $flow = self::FLOWS[$regulation->impact_level] ?? [];
                $nextStep = $approval->step_number + 1;

                if (isset($flow[$nextStep])) {
                    $this->createStepRecords($regulation, $nextStep, $userMap);
                    $regulation->update(['approval_status' => 'pending_authorization']);
                    $this->notifyPendingApprovers($regulation, $nextStep);
                } else {
                    $regulation->update(['approval_status' => 'approved']);

                    // La vigencia real (1 año) se asigna hasta este momento — la "fecha de
                    // elaboración" capturada en el wizard ya no determina el vencimiento.
                    $regulation->currentVersion?->update([
                        'valid_until' => now()->addYear(),
                    ]);

                    $this->notifyCreator($regulation, 'approved');
                    $this->notifyApprovers($regulation);
                }
            }
        });
    }

    /**
     * Reinicia el flujo desde el paso 1. Se usa en dos casos: (1) un admin re-envía manualmente
     * un reglamento rechazado tras corregirlo (RegulationApprovalController::resubmit()), y (2)
     * automáticamente cuando se guarda una edición sobre un reglamento que ya estaba 'approved'
     * (saveEdit()/confirmEditDraft()/store() en los controladores de Regulation) — el contenido
     * cambió, así que ya no se puede considerar aprobado sin que alguien lo revise de nuevo.
     */
    public function resubmit(Regulation $regulation): void
    {
        $userMap = $regulation->flow_user_map ?? [];

        DB::transaction(function () use ($regulation, $userMap) {
            $regulation->approvals()->delete();
            $regulation->update(['approval_status' => 'pending_review']);
            $this->createStepRecords($regulation, 1, $userMap);
        });

        $this->notifyPendingApprovers($regulation, 1);
        $this->notifyFutureFlowMembers($regulation, $userMap);
    }

    /**
     * Devuelve true si el usuario tiene algún registro pending en el reglamento.
     */
    public function userHasPendingApproval(Regulation $regulation, int $userId): bool
    {
        return $regulation->pendingApprovals()->where('user_id', $userId)->exists();
    }

    /**
     * Devuelve el registro pending del usuario en el reglamento, o null.
     */
    public function getPendingApprovalForUser(Regulation $regulation, int $userId): ?RegulationApproval
    {
        return $regulation->pendingApprovals()->where('user_id', $userId)->first();
    }

    /**
     * Si el reglamento estaba rechazado y se acaba de guardar una corrección (desde el wizard de
     * IA o desde el editor libre), avisa a los admins con acceso a la empresa que ya pueden
     * reiniciar el flujo — solo un admin puede hacerlo (RegulationApprovalController::resubmit()),
     * y sin este aviso nadie se entera de que ya se corrigió hasta que alguien entra a revisar el
     * reglamento por su cuenta. No hace nada si el reglamento no estaba rechazado.
     *
     * Solo se avisa a admins con acceso al módulo de Procesos (module_access 'all' o 'procesos')
     * — los admins de otros módulos (ej. solo Cumplimiento) no deben recibir avisos de un flujo
     * que no les corresponde.
     */
    public function notifyIfCorrectedAfterRejection(Regulation $regulation): void
    {
        if ($regulation->approval_status !== 'rejected') {
            return;
        }

        $admins = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->get()
            ->filter(fn (User $u) => $u->canAccessCompany($regulation->company))
            ->filter(fn (User $u) => $u->canAccessModule('procesos'));

        foreach ($admins as $admin) {
            $admin->notify(new RegulationReadyToResubmitNotification($regulation));
        }
    }

    /**
     * Un operativo sin acceso de edición (no es responsable) solicitó que lo agreguen como
     * responsable de un reglamento — se avisa a los admins con acceso al módulo de Procesos
     * (mismo filtro que notifyIfCorrectedAfterRejection) para que le den acceso si corresponde.
     * Devuelve cuántos admins fueron notificados, para que el controlador pueda avisarle al
     * operativo si no había ninguno a quien avisar.
     */
    public function notifyAdminsOfAccessRequest(Regulation $regulation, User $requester): int
    {
        $admins = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->get()
            ->filter(fn (User $u) => $u->canAccessCompany($regulation->company))
            ->filter(fn (User $u) => $u->canAccessModule('procesos'));

        foreach ($admins as $admin) {
            $admin->notify(new RegulationAccessRequestedNotification($regulation, $requester));
        }

        return $admins->count();
    }

    /**
     * Mismo filtro de admins que notifyIfCorrectedAfterRejection()/notifyAdminsOfAccessRequest():
     * solo quienes de verdad pueden corregir la asignación de puestos de este reglamento.
     *
     * @param  array<int, string>  $positionSlugs
     */
    private function notifyStepUnassigned(Regulation $regulation, int $step, array $positionSlugs): void
    {
        $names = JobPosition::where('group_id', $regulation->group_id)
            ->whereIn('slug', $positionSlugs)
            ->pluck('name')
            ->all();

        $admins = User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->get()
            ->filter(fn (User $u) => $u->canAccessCompany($regulation->company))
            ->filter(fn (User $u) => $u->canAccessModule('procesos'));

        foreach ($admins as $admin) {
            $admin->notify(new ApprovalStepUnassignedNotification($regulation, $step, $names ?: $positionSlugs));
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createStepRecords(Regulation $regulation, int $step, array $userMap = []): void
    {
        $flow = self::FLOWS[$regulation->impact_level] ?? [];
        $stepDef = $flow[$step] ?? null;

        if (! $stepDef) {
            return;
        }

        $users = $this->resolveStepUsers($regulation, $stepDef, $userMap)->values();

        // Ningún puesto del paso tiene usuarios reales (o flow_user_map apunta a usuarios que ya
        // no existen/no están activos) — sin este aviso el reglamento queda en
        // "pending_authorization" para siempre, sin ninguna fila "pending" sobre la cual nadie
        // (ni siquiera el recordatorio automático) pueda actuar. Ver ApprovalStepUnassignedNotification.
        if ($users->isEmpty()) {
            $this->notifyStepUnassigned($regulation, $step, $stepDef['positions']);
        }

        // Cuando el paso exige que TODOS aprueben y hay más de un aprobador, van uno a la vez en
        // el orden en que se agregaron (flow_user_map conserva ese orden) — no en paralelo: solo
        // el primero arranca "pending", el resto arranca "waiting" hasta que le toque su turno
        // (ver promoteNextWaitingApprover()). Si basta con uno (OR) o solo hay un aprobador, se
        // mantiene el comportamiento de siempre: todos "pending" desde el inicio.
        $sequential = $stepDef['requires_all'] && $users->count() > 1;

        foreach ($users as $index => $user) {
            RegulationApproval::create([
                'regulation_id'   => $regulation->id,
                'step_number'     => $step,
                'job_position_id' => $user->pivot_job_position_id,
                'user_id'         => $user->id,
                'requires_all'    => $stepDef['requires_all'],
                'sequence_order'  => $index,
                'status'          => (! $sequential || $index === 0) ? 'pending' : 'waiting',
            ]);
        }
    }

    /**
     * Resuelve qué usuarios corresponden a un paso del flujo: si el admin asignó usuarios
     * específicos por puesto (flow_user_map) se usan solo esos, si no, todos los usuarios
     * activos de cada puesto involucrado. Compartido entre createStepRecords() (que sí crea el
     * registro de aprobación) y notifyFutureFlowMembers() (que solo necesita saber a quién avisar
     * de un paso que todavía no existe como registro en BD).
     *
     * @return Collection<int, User>  cada User trae "pivot_job_position_id" con el puesto resuelto.
     */
    private function resolveStepUsers(Regulation $regulation, array $stepDef, array $userMap): Collection
    {
        $users = collect();

        foreach ($stepDef['positions'] as $slug) {
            $position = JobPosition::where('group_id', $regulation->group_id)
                ->where('slug', $slug)
                ->first();

            if (! $position) {
                continue;
            }

            // Si se asignaron usuarios específicos para este puesto, usar solo esos.
            if (isset($userMap[$slug])) {
                $userIds = is_array($userMap[$slug])
                    ? array_map('intval', $userMap[$slug])
                    : [(int) $userMap[$slug]];

                foreach (User::whereIn('id', array_filter($userIds))->get() as $user) {
                    $user->pivot_job_position_id = $position->id;
                    $users->push($user);
                }
                continue;
            }

            // Si no, todos los usuarios asignados al puesto.
            foreach ($position->users as $user) {
                $user->pivot_job_position_id = $position->id;
                $users->push($user);
            }
        }

        return $users->unique('id');
    }

    private function isStepComplete(Regulation $regulation, int $step): bool
    {
        return ! $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * En un paso secuencial (ver createStepRecords()), le pasa el turno al siguiente aprobador
     * en la fila (el "waiting" con menor sequence_order) y lo notifica. Devuelve false si no hay
     * nadie esperando turno en este paso — o porque el paso nunca fue secuencial, o porque ya
     * decidieron todos.
     */
    private function promoteNextWaitingApprover(Regulation $regulation, int $step): bool
    {
        $next = $regulation->approvalStep($step)
            ->where('status', 'waiting')
            ->orderBy('sequence_order')
            ->first();

        if (! $next) {
            return false;
        }

        $next->update(['status' => 'pending']);
        $next->user?->notify(new ApprovalRequestedNotification($regulation));

        return true;
    }

    private function notifyPendingApprovers(Regulation $regulation, int $step): void
    {
        $users = $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($users as $user) {
            $user->notify(new ApprovalRequestedNotification($regulation));
        }
    }

    /**
     * Avisa, de forma solo informativa, a quienes participan en pasos POSTERIORES al primero —
     * el primer paso ya recibe el correo accionable de notifyPendingApprovers(). Se manda una
     * sola vez, al iniciar o reiniciar el flujo completo (no en cada avance de paso: ahí el paso
     * que se desbloquea ya recibe el correo accionable, no el informativo).
     */
    private function notifyFutureFlowMembers(Regulation $regulation, array $userMap): void
    {
        $flow = self::FLOWS[$regulation->impact_level] ?? [];

        // Quien ya participa en el paso 1 recibió el correo accionable — si esa misma persona
        // también está asignada a un puesto de un paso posterior (ej. es líder Y gerente), no
        // tiene caso mandarle además el informativo de "tu voto se pedirá más adelante".
        $notifiedIds = isset($flow[1])
            ? $this->resolveStepUsers($regulation, $flow[1], $userMap)->pluck('id')->all()
            : [];

        foreach ($flow as $step => $stepDef) {
            if ($step === 1) {
                continue;
            }

            foreach ($this->resolveStepUsers($regulation, $stepDef, $userMap) as $user) {
                if (in_array($user->id, $notifiedIds, true)) {
                    continue;
                }

                $notifiedIds[] = $user->id;
                $user->notify(new ApprovalFlowMemberNotification($regulation, $step));
            }
        }
    }

    private function notifyCreator(Regulation $regulation, string $outcome, ?string $comments = null, $rejectedBy = null): void
    {
        $creator = $regulation->creator;

        if (! $creator) {
            return;
        }

        match ($outcome) {
            'approved' => $creator->notify(new RegulationApprovedNotification($regulation)),
            'rejected' => $creator->notify(new RegulationRejectedNotification($regulation, $comments ?? '', $rejectedBy)),
            default => null,
        };
    }

    /**
     * Avisa a todos los que votaron (aprobaron) en algún paso del flujo — para llegar a
     * "approved" TODOS tuvieron que aprobar (o, en un paso "basta con uno", quien haya votado),
     * así que esto es exactamente el conjunto de aprobadores reales, sin incluir a quien quedó
     * "cancelled" sin votar. Se excluye al creador porque notifyCreator() ya le avisó aparte
     * (evita mandarle el mismo correo dos veces si además es uno de los aprobadores).
     */
    private function notifyApprovers(Regulation $regulation): void
    {
        $approverIds = $regulation->approvals()
            ->where('status', 'approved')
            ->pluck('user_id')
            ->unique()
            ->reject(fn ($id) => $id === $regulation->created_by);

        User::whereIn('id', $approverIds)->get()->each(
            fn (User $user) => $user->notify(new RegulationApprovedNotification($regulation))
        );
    }
}
