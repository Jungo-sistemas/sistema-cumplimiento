<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentFolder;
use App\Models\DocumentVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class DocumentVersionController extends Controller
{
    private function disk()
    {
        return Storage::disk('private');
    }

    public function show(DocumentFolder $category, Document $document)
    {
        $this->assertDocumentBelongsToCategory($document, $category);

        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document, $category);

        $document->load([
            'versions.uploader',
            'folder.parent',
            'company',
        ]);

        $currentVersion = $document->versions->firstWhere('is_current', true)
            ?? $document->versions->sortByDesc('version_number')->first();

        $versionHistory = $document->versions->sortByDesc('version_number');

        return view('documents.document', [
            'category'       => $category,
            'document'       => $document,
            'currentVersion' => $currentVersion,
            'versionHistory' => $versionHistory,
        ]);
    }

    public function store(Request $request, DocumentFolder $category, Document $document)
    {
        $this->assertDocumentBelongsToCategory($document, $category);

        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document, $category);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $data = $request->validate([
            'file'        => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'issued_at'   => ['nullable', 'date'],
            'valid_until' => ['nullable', 'date', 'after:today'],
        ], [
            'valid_until.after' => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        DB::transaction(function () use ($data, $document, $request) {
            $document = Document::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Mark existing current version as replaced
            DocumentVersion::query()
                ->where('document_id', $document->id)
                ->where('is_current', true)
                ->update(['is_current' => false]);

            $nextVersion = (int) DocumentVersion::query()
                ->where('document_id', $document->id)
                ->max('version_number');
            $nextVersion++;

            $file      = $request->file('file');
            $directory = "documents/{$document->company_id}/{$document->id}/versions";
            $filename  = now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName();
            $path      = $file->storeAs($directory, $filename, 'private');

            DocumentVersion::create([
                'document_id'    => $document->id,
                'version_number' => $nextVersion,
                'is_current'     => true,
                'file_path'      => $path,
                'original_name'  => $file->getClientOriginalName(),
                'mime_type'      => $file->getClientMimeType(),
                'file_size'      => $file->getSize(),
                'issued_at'      => $data['issued_at'] ?? null,
                'valid_until'    => $data['valid_until'] ?? null,
                'uploaded_by'    => auth()->id(),
            ]);
        });

        return back()->with('success', 'Versión subida correctamente. Se actualizó el historial del documento.');
    }

    public function preview(DocumentVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->canAccessCompany($version->document->company), 403);

        if (! $this->disk()->exists($version->file_path)) {
            abort(404);
        }

        $fullPath = $this->disk()->path($version->file_path);
        $mime     = $version->mime_type ?: (file_exists($fullPath) ? mime_content_type($fullPath) : null);
        $allowed  = ['application/pdf', 'image/jpeg', 'image/png'];

        if (! $mime || ! in_array($mime, $allowed, true)) {
            abort(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Vista previa no disponible para este tipo de archivo.');
        }

        return response()->file($fullPath, [
            'Content-Type'        => $mime,
            'Content-Disposition' => 'inline; filename="' . $this->safeFilename($version->original_name ?? 'documento') . '"',
        ]);
    }

    public function download(DocumentVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->canAccessCompany($version->document->company), 403);

        if (! $this->disk()->exists($version->file_path)) {
            abort(404);
        }

        return $this->disk()->download(
            $version->file_path,
            $version->original_name ?? basename($version->file_path)
        );
    }

    public function destroy(DocumentFolder $category, Document $document, DocumentVersion $version)
    {
        $this->assertDocumentBelongsToCategory($document, $category);

        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document, $category);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless((int) $version->document_id === (int) $document->id, 404);

        DB::transaction(function () use ($version, $document) {
            $version = DocumentVersion::query()
                ->whereKey($version->id)
                ->lockForUpdate()
                ->firstOrFail();

            $wasCurrent = (bool) $version->is_current;

            if ($this->disk()->exists($version->file_path)) {
                $this->disk()->delete($version->file_path);
            }

            $version->delete();

            if (! $wasCurrent) {
                return;
            }

            // Promote the most recent remaining version as current
            $replacement = DocumentVersion::query()
                ->where('document_id', $document->id)
                ->orderByDesc('version_number')
                ->orderByDesc('id')
                ->first();

            if ($replacement) {
                $replacement->update(['is_current' => true]);
            }
        });

        return back()->with('success', 'Versión eliminada correctamente.');
    }

    private function assertDocumentBelongsToCategory(Document $document, DocumentFolder $category): void
    {
        if ((int) $document->document_folder_id !== (int) $category->id) {
            abort(404);
        }
    }

    // General folders (company_id=null) are accessible to any user in the same group.
    private function authorizeDocumentAccess($user, Document $document, DocumentFolder $folder): void
    {
        if ($document->company_id !== null) {
            abort_unless($user->canAccessCompany($document->company), 403);
        } else {
            abort_unless(
                $user->isGlobalScope() || $user->group_id === $folder->group_id,
                403
            );
        }
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^\w.\-]/', '_', $name);
    }
}
