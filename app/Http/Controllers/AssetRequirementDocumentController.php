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

        $dateMode = $request->input('date_mode', 'renewal');

        $data = $request->validate([
            'date_mode' => ['required', 'in:no_dates,no_renewal,renewal'],
            'files'     => ['required', 'array', 'min:1', 'max:5'],
            'files.*'   => ['required', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png,zip'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => $dateMode === 'renewal'
                ? ['required', 'date']
                : ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'files.required'    => 'Debes adjuntar al menos un archivo.',
            'files.max'         => 'Puedes subir un máximo de 5 archivos a la vez.',
            'files.*.required'  => 'Cada archivo es obligatorio.',
            'files.*.mimes'     => 'Solo se permiten archivos PDF, JPG, PNG o ZIP.',
            'files.*.max'       => 'Cada archivo no puede superar los 50 MB.',
            'expires_at.required' => 'La fecha de vencimiento es obligatoria cuando el documento tiene renovación.',
        ]);

        $issuedAt  = in_array($dateMode, ['no_renewal', 'renewal']) ? ($data['issued_at'] ?? null) : null;
        $expiresAt = $dateMode === 'renewal' ? ($data['expires_at'] ?? null) : null;

        $lastDocument = null;
        foreach ($request->file('files', []) as $file) {
            $lastDocument = $uploadOfficialDocumentService->handle(
                requirement: $requirement,
                file: $file,
                issuedAt: $issuedAt,
                expiresAt: $expiresAt,
                notes: $data['notes'] ?? null,
            );
        }

        if ($lastDocument && $lastDocument->expires_at) {
            $requirement->loadMissing(['asset', 'template']);
            $dueDate = Carbon::parse($lastDocument->expires_at)->subDays(60);
            if ($dueDate->isPast()) {
                $dueDate = Carbon::today()->addDay();
            }
            $titleBase = $requirement->template?->name ?? $requirement->type ?? 'Requerimiento';
            session()->flash('renewal_suggestion', [
                'title'               => 'Renovar ' . $titleBase . ' ' . $dueDate->year,
                'due_date'            => $dueDate->toDateString(),
                'responsible_user_id' => $requirement->asset?->responsible_user_id,
            ]);
        }

        $count = count($request->file('files', []));
        $msg   = $count > 1 ? "{$count} documentos guardados correctamente." : 'Documento oficial guardado correctamente.';

        return back()->with('success', $msg);
    }

    public function documentHistory(Asset $asset, AssetRequirement $requirement)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);

        $requirement->load(['template', 'documents.uploader']);

        $documentHistory = $requirement->documents
            ->sortByDesc('version_number');

        $navContext = [
            'asset'         => $asset,
            'requirement'   => $requirement,
            'task'          => null,
            'documentSection' => true,
            'documentOwner' => 'requirement',
        ];

        return view('requirements.documents-history', [
            'asset'           => $asset,
            'requirement'     => $requirement,
            'documentHistory' => $documentHistory,
            'navContext'      => $navContext,
        ]);
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

    public function storeRenewalTask(Request $request, Asset $asset, AssetRequirement $requirement)
    {
        $this->assertRequirementBelongsToAsset($asset, $requirement);
        $this->assertSameCompany($asset);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        $data = $request->validate([
            'title'               => ['required', 'string', 'max:160'],
            'due_date'            => ['required', 'date'],
            'responsible_user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $task = Task::create([
            'asset_requirement_id' => $requirement->id,
            'type'                 => Task::TYPE_RENEWAL,
            'title'                => $data['title'],
            'description'          => 'Tarea de renovación creada al registrar un nuevo documento oficial.',
            'status'               => TaskStatus::PENDING,
            'due_date'             => $data['due_date'],
            'requires_document'    => false,
        ]);

        if (! empty($data['responsible_user_id'])) {
            $task->users()->sync([$data['responsible_user_id']]);
        }

        return redirect()
            ->route('assets.requirements.documents.index', [$asset, $requirement])
            ->with('success', 'Tarea de renovación creada correctamente.');
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