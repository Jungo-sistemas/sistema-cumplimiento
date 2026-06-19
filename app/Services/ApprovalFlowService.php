<?php

namespace App\Services;

use App\Models\JobPosition;
use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationRejectedNotification;
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
                $regulation->pendingApprovals()->update(['status' => 'cancelled']);
                $regulation->update(['approval_status' => 'rejected']);
                $this->notifyCreator($regulation, 'rejected', $comments, $approval->user);
                return;
            }

            if (! $approval->requires_all) {
                $regulation->approvalStep($approval->step_number)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
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
                    $this->notifyCreator($regulation, 'approved');
                }
            }
        });
    }

    /**
     * Reinicia el flujo desde el paso 1 (usado tras un rechazo).
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

        $requiresAll = $stepDef['requires_all'];
        $positions   = $stepDef['positions'];

        foreach ($positions as $slug) {
            $position = JobPosition::where('group_id', $regulation->group_id)
                ->where('slug', $slug)
                ->first();

            if (! $position) {
                continue;
            }

            // If specific users were assigned for this position, use only those
            if (isset($userMap[$slug])) {
                $userIds = is_array($userMap[$slug])
                    ? array_map('intval', $userMap[$slug])
                    : [(int) $userMap[$slug]];

                foreach ($userIds as $userId) {
                    if ($userId && User::where('id', $userId)->exists()) {
                        RegulationApproval::create([
                            'regulation_id'   => $regulation->id,
                            'step_number'     => $step,
                            'job_position_id' => $position->id,
                            'user_id'         => $userId,
                            'requires_all'    => $requiresAll,
                            'status'          => 'pending',
                        ]);
                    }
                }
                continue;
            }

            // Fall back to all users assigned to the position
            foreach ($position->users as $user) {
                RegulationApproval::create([
                    'regulation_id'   => $regulation->id,
                    'step_number'     => $step,
                    'job_position_id' => $position->id,
                    'user_id'         => $user->id,
                    'requires_all'    => $requiresAll,
                    'status'          => 'pending',
                ]);
            }
        }
    }

    private function isStepComplete(Regulation $regulation, int $step): bool
    {
        return ! $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->exists();
    }

    private function notifyPendingApprovers(Regulation $regulation, int $step): void
    {
        // Notificaciones desactivadas temporalmente.
    }

    private function notifyCreator(Regulation $regulation, string $outcome, ?string $comments = null, $rejectedBy = null): void
    {
        // Notificaciones desactivadas temporalmente.
    }
}
