<?php

namespace App\Services;

use App\Enums\InvestmentRequestStatus;
use App\Models\InvestmentRequest;
use App\Models\InvestmentRequestApproval;
use App\Models\User;
use App\Notifications\InvestmentApprovalRequestedNotification;
use App\Notifications\InvestmentRequestDecidedNotification;
use Illuminate\Support\Facades\DB;

/**
 * Flujo de aprobación de solicitudes de inversión: las personas elegidas al crear la solicitud
 * aprueban una tras otra, en el orden en que se eligieron. Solo quien tiene el turno ("pending")
 * puede decidir; las demás esperan ("waiting"). Si alguien rechaza, el flujo se detiene.
 */
class InvestmentApprovalService
{
    public const MAX_APPROVERS = 5;

    /** @param  array<int, int>  $approverIds  en el orden en que deben aprobar */
    public function start(InvestmentRequest $investmentRequest, array $approverIds): void
    {
        DB::transaction(function () use ($investmentRequest, $approverIds) {
            $investmentRequest->approvals()->delete();

            foreach (array_values($approverIds) as $index => $userId) {
                InvestmentRequestApproval::create([
                    'investment_request_id' => $investmentRequest->id,
                    'user_id'               => $userId,
                    'sequence_order'        => $index + 1,
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
