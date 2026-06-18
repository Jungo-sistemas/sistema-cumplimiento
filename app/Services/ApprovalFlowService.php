<?php

namespace App\Services;

use App\Models\JobPosition;
use App\Models\Regulation;
use App\Models\RegulationApproval;
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
    private const FLOWS = [
        'alto' => [
            1 => ['requires_all' => true,  'positions' => ['lider', 'ejecutivo_reglamentos']],
            2 => ['requires_all' => true,  'positions' => ['direccion_general']],
        ],
        'medio_alto' => [
            1 => ['requires_all' => true,  'positions' => ['lider', 'ejecutivo_reglamentos']],
            2 => ['requires_all' => true,  'positions' => ['direccion_general', 'director_finanzas']],
        ],
        'medio' => [
            1 => ['requires_all' => true,  'positions' => ['ejecutivo_reglamentos']],
            2 => ['requires_all' => false, 'positions' => ['lider', 'gerente']],
        ],
        'bajo' => [
            1 => ['requires_all' => true,  'positions' => ['ejecutivo_reglamentos']],
        ],
    ];

    public static function getFlowSteps(string $level): array
    {
        return self::FLOWS[$level] ?? [];
    }

    /**
     * Inicializa el flujo de aprobación al crear un reglamento.
     */
    public function initFlow(Regulation $regulation): void
    {
        DB::transaction(function () use ($regulation) {
            $regulation->approvals()->delete();
            $this->createStepRecords($regulation, 1);
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

            if ($status === 'rejected') {
                // Cancelar todos los pendientes del mismo reglamento
                $regulation->pendingApprovals()->update(['status' => 'cancelled']);
                $regulation->update(['approval_status' => 'rejected']);
                $this->notifyCreator($regulation, 'rejected', $comments, $approval->user);
                return;
            }

            // Si es OR y ya hay una aprobación, cancelar los demás del paso
            if (! $approval->requires_all) {
                $regulation->approvalStep($approval->step_number)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }

            // Verificar si el paso actual está completo
            if ($this->isStepComplete($regulation, $approval->step_number)) {
                $flow = self::FLOWS[$regulation->impact_level] ?? [];
                $nextStep = $approval->step_number + 1;

                if (isset($flow[$nextStep])) {
                    $this->createStepRecords($regulation, $nextStep);
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
        DB::transaction(function () use ($regulation) {
            $regulation->approvals()->delete();
            $regulation->update(['approval_status' => 'pending_review']);
            $this->createStepRecords($regulation, 1);
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

    private function createStepRecords(Regulation $regulation, int $step): void
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
        $approvals = $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->with('user')
            ->get();

        foreach ($approvals as $approval) {
            $approval->user->notify(new ApprovalRequestedNotification($regulation));
        }
    }

    private function notifyCreator(Regulation $regulation, string $outcome, ?string $comments = null, $rejectedBy = null): void
    {
        $creator = $regulation->creator;

        if (! $creator) {
            return;
        }

        if ($outcome === 'approved') {
            $creator->notify(new RegulationApprovedNotification($regulation));
        } else {
            $creator->notify(new RegulationRejectedNotification($regulation, $comments ?? '', $rejectedBy));
        }
    }
}
