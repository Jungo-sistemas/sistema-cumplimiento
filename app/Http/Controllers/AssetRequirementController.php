<?php

namespace App\Http\Controllers;

use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreAssetRequirementRequest;
use App\Http\Requests\UpdateAssetRequirementRequest;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AssetRequirementController extends Controller
{
    public function show(Asset $asset, AssetRequirement $requirement)
    {
        abort_unless((int) $requirement->asset_id === (int) $asset->id, 404);

        $asset->loadMissing('company');

        abort_unless(auth()->user()->canAccessCompany($asset->company), 403);

        $requirement->load([
            'asset',
            'template',
            'documents',
            'tasks' => function ($q) {
                $q->with(['users', 'documents'])
                    ->withCount('documents')
                    ->orderByRaw("CASE WHEN due_date IS NULL THEN 1 ELSE 0 END")
                    ->orderBy('due_date')
                    ->latest();
            },
        ])->loadCount([
            'tasks as tasks_total',
            'tasks as tasks_done' => fn ($t) => $t->whereNotNull('completed_at'),
        ]);

        $responsibles = User::query()
            ->where('company_id', $asset->company_id)
            ->orderBy('name')
            ->get();

        $navContext = [
            'asset' => $asset,
            'requirement' => $requirement,
            'task' => null,
            'documentSection' => false,
        ];

        return view('requirements.show', compact(
            'asset',
            'requirement',
            'responsibles',
            'navContext'
        ));
    }

    public function store(StoreAssetRequirementRequest $request, Asset $asset)
    {
        $asset->loadMissing('company');

        abort_unless(auth()->user()->canAccessCompany($asset->company), 403);

        $data = $request->validated();

        $data['company_id'] = $asset->company_id;
        $data['asset_id'] = $asset->id;
        $data['completed_at'] = null;
        $data['status'] = RequirementStatus::PENDING;

        $requirement = DB::transaction(function () use ($data) {
            $requirement = AssetRequirement::create($data);

            $requirement->tasks()->create([
                'title' => 'Subir documento principal (permiso/obligación)',
                'description' => 'Adjunta el documento oficial requerido para esta obligación.',
                'status' => TaskStatus::PENDING,
                'due_date' => $requirement->due_date,
                'requires_document' => true,
                'completed_at' => null,
            ]);

            return $requirement;
        });

        return redirect()
            ->route('assets.requirements.show', [$asset, $requirement])
            ->with('success', 'Requirement creado.');
    }

    public function update(UpdateAssetRequirementRequest $request, Asset $asset, AssetRequirement $requirement)
    {
        abort_unless((int) $requirement->asset_id === (int) $asset->id, 404);

        $asset->loadMissing('company');

        abort_unless(auth()->user()->canAccessCompany($asset->company), 403);

        $beforeStatus = $requirement->status;

        $data = $request->validated();
        unset($data['company_id'], $data['asset_id']);

        DB::transaction(function () use ($requirement, $data, $beforeStatus) {
            $requirement->update($data);

            $afterStatus = $requirement->fresh()->status;

            $completedNow =
                $beforeStatus !== RequirementStatus::COMPLETED &&
                $afterStatus === RequirementStatus::COMPLETED;

            if ($completedNow) {
                if (is_null($requirement->completed_at)) {
                    $requirement->update(['completed_at' => now()]);
                }

                $this->renewIfRecurrent($requirement);
            }
        });

        return back()->with('success', 'Requirement actualizado.');
    }

    private function renewIfRecurrent(AssetRequirement $requirement): ?AssetRequirement
    {
        if (! $requirement->isRecurrent()) {
            return null;
        }

        $nextDue = $requirement->nextDueDate();

        if (! $nextDue) {
            return null;
        }

        return AssetRequirement::create([
            'company_id' => $requirement->company_id,
            'asset_id' => $requirement->asset_id,
            'requirement_template_id' => $requirement->requirement_template_id,
            'type' => $requirement->type,
            'status' => RequirementStatus::PENDING,
            'due_date' => $nextDue->toDateString(),
            'completed_at' => null,
            'recurrence_interval' => $requirement->recurrence_interval,
            'recurrence_unit' => $requirement->recurrence_unit,
            'recurrence_anchor' => $requirement->recurrence_anchor,
        ]);
    }

    public function complete(Asset $asset, AssetRequirement $requirement)
    {
        $this->authorize('complete', $requirement);

        abort_unless((int) $requirement->asset_id === (int) $asset->id, 404);

        if (! $requirement->documents()->exists()) {
            return back()->with('error', 'No puedes completar: falta subir la documentación oficial.');
        }

        $requirement->update([
            'status' => RequirementStatus::COMPLETED,
            'completed_at' => now(),
        ]);

        return back()->with('success', 'Requerimiento completado.');
    }

    public function markInTransit(Asset $asset, AssetRequirement $requirement)
    {
        abort_unless((int) $requirement->asset_id === (int) $asset->id, 404);

        $asset->loadMissing('company');

        abort_unless(auth()->user()->canAccessCompany($asset->company), 403);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        if (! $requirement->canBeMarkedInTransit()) {
            return back()->with('error', 'No se puede marcar como En trámite: deben completarse todas las tareas primero.');
        }

        $requirement->update(['status' => RequirementStatus::IN_TRANSIT]);

        return back()->with('success', 'Requerimiento marcado como En trámite.');
    }

    public function reopen(Asset $asset, AssetRequirement $requirement)
    {
        abort_unless((int) $requirement->asset_id === (int) $asset->id, 404);

        $asset->loadMissing('company');

        abort_unless(auth()->user()->canAccessCompany($asset->company), 403);

        $requirement->update([
            'status' => RequirementStatus::IN_PROGRESS,
            'completed_at' => null,
        ]);

        return back()->with('success', 'Requerimiento reabierto.');
    }
}