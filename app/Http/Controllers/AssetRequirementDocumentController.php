<?php

namespace App\Http\Controllers;

use App\Services\Requirements\UploadOfficialDocumentService;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\AssetRequirementDocument;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Controllers\Concerns\ValidatesCompany;
use App\Enums\RequirementStatus;
use App\Enums\TaskStatus;
use Carbon\Carbon;

class AssetRequirementDocumentController extends Controller
{
    use ValidatesCompany;

    private function disk()
    {
        return Storage::disk('private');
    }

    public function index(Asset $asset, AssetRequirement $requirement)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);

        $assetInactive = method_exists($asset, 'isInactive')
            ? $asset->isInactive()
            : ($asset->status === 'inactive');

        $requirement->load([
            'template',
            'documents.uploader',
        ]);

        $navContext = [
            'asset' => $asset,
            'requirement' => $requirement,
            'task' => null,
            'documentSection' => true,
            'documentOwner' => 'requirement',
        ];

        return view('requirements.documents', [
            'asset' => $asset,
            'requirement' => $requirement,
            'assetInactive' => $assetInactive,
            'navContext' => $navContext,
        ]);
    }

    public function store(Request $request, Asset $asset, AssetRequirement $requirement, UploadOfficialDocumentService $uploadOfficialDocumentService) 
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        if (method_exists($asset, 'isInactive') ? $asset->isInactive() : ($asset->status === 'inactive')) {
            return back()->with('error', 'El activo está desactivado. No puedes subir documentación oficial.');
        }

        $data = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string'],
        ], [
            'expires_at.required' => 'La fecha de vencimiento es obligatoria.',
            'expires_at.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        $uploadOfficialDocumentService->handle(
            requirement: $requirement,
            file: $request->file('file'),
            issuedAt: $data['issued_at'] ?? null,
            expiresAt: $data['expires_at'],
            notes: $data['notes'] ?? null,
        );

        return back()->with('success', 'Documento oficial guardado correctamente. Se actualizó el histórico y la renovación.');
    }

    public function download(Asset $asset, AssetRequirement $requirement, AssetRequirementDocument $document)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToRequirement($document, $requirement);

        if (!$this->disk()->exists($document->file_path)) {
            abort(404);
        }

        return $this->disk()->download(
            $document->file_path,
            $document->original_name ?? basename($document->file_path)
        );
    }

    public function preview(Asset $asset, AssetRequirement $requirement, AssetRequirementDocument $document)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToRequirement($document, $requirement);

        if (!$this->disk()->exists($document->file_path)) {
            abort(404);
        }

        $fullPath = $this->disk()->path($document->file_path);

        $mime = $document->mime_type ?: (file_exists($fullPath) ? mime_content_type($fullPath) : null);

        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (!$mime || !in_array($mime, $allowed, true)) {
            abort(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Preview not supported');
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $this->safeFilename($document->original_name ?? 'document') . '"',
        ]);
    }

    public function destroy(Asset $asset, AssetRequirement $requirement, AssetRequirementDocument $document)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToRequirement($document, $requirement);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        if (method_exists($asset, 'isInactive') ? $asset->isInactive() : ($asset->status === 'inactive')) {
            abort(403, 'Asset inactive');
        }

        \DB::transaction(function () use ($requirement, $document) {
            $requirement = AssetRequirement::query()
                ->whereKey($requirement->id)
                ->lockForUpdate()
                ->firstOrFail();

            $document = AssetRequirementDocument::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasCurrent = (bool) $document->is_current;

            if ($this->disk()->exists($document->file_path)) {
                $this->disk()->delete($document->file_path);
            }

            $document->delete();

            if (! $wasCurrent) {
                return;
            }

            $replacement = AssetRequirementDocument::query()
                ->where('asset_requirement_id', $requirement->id)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                AssetRequirementDocument::query()
                    ->where('asset_requirement_id', $requirement->id)
                    ->update([
                        'is_current' => false,
                    ]);

                $replacement->update([
                    'is_current' => true,
                    'status' => 'active',
                    'replaced_by_document_id' => null,
                ]);

                $requirement->update([
                    'status' => RequirementStatus::COMPLETED,
                    'completed_at' => $requirement->completed_at ?: now(),
                    'issued_at' => $replacement->issued_at,
                    'expires_at' => $replacement->expires_at,
                    'current_document_id' => $replacement->id,
                ]);

                $this->syncRenewalTaskFromCurrentDocument($requirement, $replacement);
            } else {
                $requirement->update([
                    'status' => RequirementStatus::IN_PROGRESS,
                    'completed_at' => null,
                    'issued_at' => null,
                    'expires_at' => null,
                    'current_document_id' => null,
                ]);

                $this->deleteOpenRenewalTasks($requirement);
            }
        });

        return back()->with('status', 'Documento eliminado correctamente.');
    }

    private function deleteOpenRenewalTasks(AssetRequirement $requirement): void
    {
        Task::query()
            ->where('asset_requirement_id', $requirement->id)
            ->where('type', Task::TYPE_RENEWAL)
            ->whereIn('status', [
                TaskStatus::PENDING,
                TaskStatus::IN_PROGRESS,
            ])
            ->whereNull('completed_at')
            ->delete();
    }

    private function syncRenewalTaskFromCurrentDocument(
        AssetRequirement $requirement,
        AssetRequirementDocument $document
    ): void {
        if (! $document->expires_at) {
            $this->deleteOpenRenewalTasks($requirement);
            return;
        }

        $dueDate = Carbon::parse($document->expires_at)->subDays(60);

        if ($dueDate->isPast()) {
            $dueDate = Carbon::today()->addDay();
        }

        $title = $requirement->template?->name ?? $requirement->type;
        $expectedTitle = 'Renovar ' . $title . ' ' . $dueDate->year;

        $openRenewalTasks = Task::query()
            ->where('asset_requirement_id', $requirement->id)
            ->where('type', Task::TYPE_RENEWAL)
            ->whereIn('status', [
                TaskStatus::PENDING,
                TaskStatus::IN_PROGRESS,
            ])
            ->whereNull('completed_at')
            ->get();

        if ($openRenewalTasks->isEmpty()) {
            $task = Task::create([
                'asset_requirement_id' => $requirement->id,
                'title' => $expectedTitle,
                'description' => 'Renovación automática ajustada al documento oficial actual.',
                'type' => Task::TYPE_RENEWAL,
                'status' => TaskStatus::PENDING,
                'due_date' => $dueDate->toDateString(),
                'requires_document' => false,
            ]);

            $responsibleUserId = $requirement->asset?->responsible_user_id;
            if ($responsibleUserId) {
                $task->users()->sync([$responsibleUserId]);
            }

            return;
        }

        foreach ($openRenewalTasks as $index => $task) {
            if ($index === 0) {
                $task->update([
                    'title' => $expectedTitle,
                    'description' => 'Renovación automática ajustada al documento oficial actual.',
                    'due_date' => $dueDate->toDateString(),
                ]);
            } else {
                $task->delete();
            }
        }
    }

    private function assertRequirementBelongsToAsset(Asset $asset, AssetRequirement $requirement): void
    {
        if ((int) $requirement->asset_id !== (int) $asset->id) {
            abort(404);
        }
    }

    private function assertDocumentBelongsToRequirement(AssetRequirementDocument $document, AssetRequirement $requirement): void
    {
        if ((int) $document->asset_requirement_id !== (int) $requirement->id) {
            abort(404);
        }
    }
}