<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Services\ApprovalFlowService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegulationTrashController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $query = Regulation::onlyTrashed()
            ->with(['processType', 'company:id,name,process_name', 'creator:id,name', 'deletedBy:id,name', 'versions'])
            ->where('group_id', $user->group_id);

        if ($user->hasCompanyScope()) {
            $query->where('company_id', $user->company_id);
        } elseif ($user->hasMultipleCompanies()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        $regulations = $query->orderByDesc('deleted_at')->paginate(20);

        return view('processes.trash', compact('regulations'));
    }

    public function restore(int $id)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $regulation = $this->scopedTrashedQuery($user)->findOrFail($id);

        $regulation->restore();
        $regulation->update([
            'deleted_by'            => null,
            'permanently_delete_at' => null,
        ]);

        // Al enviarlo a la papelera se cancelaron sus aprobaciones abiertas; si estaba en
        // revisión/autorización, el flujo se reinicia desde el paso 1 para que no quede atorado.
        if (in_array($regulation->approval_status, ['pending_review', 'pending_authorization'], true)
            && $regulation->impact_level) {
            app(ApprovalFlowService::class)->resubmit($regulation);

            return back()->with('success', "El procedimiento \"{$regulation->name}\" fue restaurado y su flujo de aprobación se reinició desde el paso 1.");
        }

        return back()->with('success', "El procedimiento \"{$regulation->name}\" fue restaurado correctamente.");
    }

    public function forceDestroy(int $id)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $regulation = $this->scopedTrashedQuery($user)->with('versions')->findOrFail($id);

        DB::transaction(function () use ($regulation) {
            foreach ($regulation->versions as $version) {
                if ($version->file_path && Storage::disk('private')->exists($version->file_path)) {
                    Storage::disk('private')->delete($version->file_path);
                }
                $version->delete();
            }

            $regulation->forceDelete();
        });

        return back()->with('success', 'El procedimiento fue eliminado permanentemente.');
    }

    private function scopedTrashedQuery($user)
    {
        $query = Regulation::onlyTrashed()->where('group_id', $user->group_id);

        if ($user->hasCompanyScope()) {
            $query->where('company_id', $user->company_id);
        } elseif ($user->hasMultipleCompanies()) {
            $query->whereIn('company_id', $user->accessibleCompanyIds());
        }

        return $query;
    }
}
