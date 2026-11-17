<?php

namespace App\Services;

use App\Enums\InvestmentRequestStatus;
use App\Models\InvestmentApprovalStep;
use App\Models\InvestmentRequest;
use App\Models\InvestmentRequestApproval;
use App\Models\User;
use App\Notifications\InvestmentApprovalRequestedNotification;
use App\Notifications\InvestmentRequestDecidedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Flujo de aprobación de solicitudes de inversión (Anexos 8). Cada razón social tiene un flujo fijo
 * (investment_approval_steps, ver `investments:sync-approval-flows`): al capturar la solicitud se
 * copia ese flujo y las personas aprueban una tras otra en el orden de POSITIONS. Solo quien tiene
 * el turno ("pending") puede decidir; las demás esperan ("waiting"). Si alguien rechaza, el flujo
 * se detiene.
 */
class InvestmentApprovalService
{
    /** Orden de aprobación de los puestos. */
    public const POSITIONS = ['Gerente', 'DD', 'CFO', 'CEO', 'Consejero'];

    /**
     * Flujo fijo de cada razón social, en orden, con su usuario.
     *
     * @param  array<int, int>  $entityIds
     * @return Collection<int, Collection<int, InvestmentApprovalStep>>  por investment_entity_id
     */
    public function stepsForEntities(array $entityIds): Collection
    {
        return InvestmentApprovalStep::query()
            ->with('user:id,name')
            ->whereIn('investment_entity_id', $entityIds)
            ->orderBy('sequence_order')
            ->get()
            ->groupBy('investment_entity_id');
    }

    /**
     * Por qué la razón social no puede arrancar una solicitud, o null si su flujo está completo.
     *
     * @param  Collection<int, InvestmentApprovalStep>  $steps
     */
    public function flowProblem(Collection $steps): ?string
    {
        if ($steps->isEmpty()) {
            return 'Esta razón social no tiene flujo de aprobación de Anexos 8. Pide a un administrador que lo configure.';
        }

        $missing = $steps->whereNull('user_id');

        if ($missing->isNotEmpty()) {
            return 'Falta dar de alta en el sistema a: '
                . $missing->map(fn ($s) => ($s->approver_name ?: 'sin nombre') . " ({$s->position})")->implode(', ')
                . '.';
        }

        return null;
    }

    public function start(InvestmentRequest $investmentRequest): void
    {
        $entityId = $investmentRequest->investment_entity_id;
        $steps    = $this->stepsForEntities([$entityId])->get($entityId, collect());

        if ($problem = $this->flowProblem($steps)) {
            throw new RuntimeException($problem);
        }

        // Si una persona ocupa dos puestos en la misma razón social, aprueba una sola vez (en el primero).
        $steps = $steps->unique('user_id')->values();

        DB::transaction(function () use ($investmentRequest, $steps) {
            $investmentRequest->approvals()->delete();

            foreach ($steps as $index => $step) {
                InvestmentRequestApproval::create([
                    'investment_request_id' => $investmentRequest->id,
                    'user_id'               => $step->user_id,
                    'sequence_order'        => $index + 1,
                    'position'              => $step->position,
                    'status'                => $index === 0 ? 'pending' : 'waiting',
                ]);
            }

            $investmentRequest->update(['status' => InvestmentRequestStatus::PENDING_APPROVAL]);
        });

        $this->notifyCurrentApprover($investmentRequest);
    }

    public function approve(InvestmentRequestApproval $approval, ?string $comments = null): void
    {
        $investmentRequest = $approval->investmentRequest;

        $finished = DB::transaction(function () use ($approval, $investmentRequest, $comments) {
            $approval->update(['status' => 'approved', 'comments' => $comments, 'decided_at' => now()]);

            $next = $investmentRequest->approvals()->where('status', 'waiting')->first();

            if ($next) {
                $next->update(['status' => 'pending']);

                return false;
            }

            $investmentRequest->update(['status' => InvestmentRequestStatus::APPROVED]);

            return true;
        });

        if ($finished) {
            $this->notifyOwners($investmentRequest, 'approved');
        } else {
            $this->notifyCurrentApprover($investmentRequest);
        }
    }

    public function reject(InvestmentRequestApproval $approval, string $comments): void
    {
        $investmentRequest = $approval->investmentRequest;

        DB::transaction(function () use ($approval, $investmentRequest, $comments) {
            $approval->update(['status' => 'rejected', 'comments' => $comments, 'decided_at' => now()]);

            // Quienes seguían en la fila ya no tienen nada que decidir.
            $investmentRequest->approvals()->where('status', 'waiting')->update(['status' => 'cancelled']);

            $investmentRequest->update(['status' => InvestmentRequestStatus::REJECTED]);
        });

        $this->notifyOwners($investmentRequest, 'rejected', $comments, $approval->user);
    }

    private function notifyCurrentApprover(InvestmentRequest $investmentRequest): void
    {
        $investmentRequest->load('currentApproval.user');

        $investmentRequest->currentApproval?->user?->notify(
            new InvestmentApprovalRequestedNotification($investmentRequest)
        );
    }

    // Avisa del resultado a quien capturó la solicitud y al solicitante (una sola vez si son la misma persona).
    private function notifyOwners(InvestmentRequest $investmentRequest, string $outcome, ?string $comments = null, ?User $rejectedBy = null): void
    {
        User::whereIn('id', array_filter([$investmentRequest->uploaded_by, $investmentRequest->requested_by]))
            ->get()
            ->each(fn (User $user) => $user->notify(
                new InvestmentRequestDecidedNotification($investmentRequest, $outcome, $comments, $rejectedBy)
            ));
    }
}
