<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Services\ApprovalFlowService;
use Illuminate\Http\Request;

class RegulationApprovalController extends Controller
{
    public function __construct(private readonly ApprovalFlowService $flowService) {}

    public function approve(Regulation $regulation)
    {
        $user = auth()->user();

        // El permiso real es tener una aprobación pendiente asignada — no se requiere acceso general a la empresa
        $approval = $this->flowService->getPendingApprovalForUser($regulation, $user->id);
        abort_unless($approval !== null, 403, 'No tienes una aprobación pendiente para este reglamento.');

        $this->flowService->processApproval($approval, 'approved');

        return back()->with('success', 'Aprobación registrada correctamente.');
    }

    public function reject(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        $request->validate([
            'comments' => 'required|string|max:1000',
        ]);

        $approval = $this->flowService->getPendingApprovalForUser($regulation, $user->id);
        abort_unless($approval !== null, 403, 'No tienes una aprobación pendiente para este reglamento.');

        $this->flowService->processApproval($approval, 'rejected', $request->comments);

        return back()->with('success', 'Rechazo registrado. El creador será notificado.');
    }

    public function resubmit(Regulation $regulation)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_unless($regulation->approval_status === 'rejected', 403, 'Solo se puede re-enviar un reglamento rechazado.');

        $this->flowService->resubmit($regulation);

        return back()->with('success', 'Reglamento re-enviado a aprobación.');
    }
}
