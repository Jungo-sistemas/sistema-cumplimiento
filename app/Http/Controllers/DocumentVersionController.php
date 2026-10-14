<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
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

    public function show(Document $document)
    {
        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document);

        $document->load([
            'versions.uploader',
            'company',
            'authorizedUsers:id,name',
        ]);

        // Un documento puede tener varios archivos vigentes a la vez (ej. la escritura y su
        // anexo) — todos se muestran juntos en "Versión actual"; el histórico son los demás.
        $currentVersions = $document->versions->where('is_current', true)->values();
        $versionHistory  = $document->versions->where('is_current', false)->values();

        $allCompanies = $user->hasGroupScope()
            ? Company::query()
                ->where('group_id', $user->group_id)
                ->orderBy('name')
                ->get()
            : ($user->hasMultipleCompanies()
                ? Company::query()
                    ->whereIn('id', $user->accessibleCompanyIds())
                    ->orderBy('name')
                    ->get()
                : collect());

        // Igual que en DocumentController::index(): las "otras" se muestran aparte, no se excluyen.
        $companies      = $allCompanies->where('otras', false)->values();
        $otrasCompanies = $allCompanies->where('otras', true)->values();

        $groupUsers = User::query()
            ->where('group_id', $user->group_id)
            ->orderBy('name')
            ->get(['id', 'name', 'company_id', 'role_id']);

        $responsibleUsers = User::query()
            ->where('group_id', $user->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'operative']))
            ->orderBy('name')
            ->get(['id', 'name', 'role_id']);

        return view('documents.document', [
            'document'         => $document,
            'currentVersions'  => $currentVersions,
            'versionHistory'   => $versionHistory,
            'companies'        => $companies,
            'otrasCompanies'   => $otrasCompanies,
            'groupUsers'       => $groupUsers,
            'users'            => $responsibleUsers,
            'documentTypes'    => Document::DOCUMENT_TYPES,
        ]);
    }

    public function store(Request $request, Document $document)
    {
        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document);
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        $dateMode = $request->input('date_mode', 'no_dates');

        $data = $request->validate([
            'file'        => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
            'date_mode'   => ['required', 'in:no_dates,no_renewal,renewal'],
            'issued_at'   => ['nullable', 'date'],
            'valid_until' => $dateMode === 'renewal'
                ? ['required', 'date', 'after:today']
                : ['nullable', 'date', 'after:today'],
        ], [
            'valid_until.required' => 'La fecha de vencimiento es obligatoria para un documento con emisión y vencimiento.',
            'valid_until.after'    => 'La fecha de vencimiento debe ser posterior a hoy.',
        ]);

        $issuedAt   = in_array($dateMode, ['no_renewal', 'renewal'], true) ? ($data['issued_at'] ?? null) : null;
        $validUntil = $dateMode === 'renewal' ? ($data['valid_until'] ?? null) : null;

        DB::transaction(function () use ($data, $document, $request, $issuedAt, $validUntil) {
            $document = Document::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            // Un documento puede tener varios archivos vigentes a la vez (ej. la escritura y su
            // anexo) — subir uno nuevo se agrega como otro archivo vigente, no reemplaza al resto.
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
                'issued_at'      => $issuedAt,
                'valid_until'    => $validUntil,
                'uploaded_by'    => auth()->id(),
            ]);
        });

        return back()->with('success', 'Archivo agregado correctamente.');
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

    public function destroy(Document $document, DocumentVersion $version)
    {
        $user = auth()->user();
        $this->authorizeDocumentAccess($user, $document);
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

            // Si quedan otros archivos vigentes, no se toca nada. Solo si éste era el único
            // vigente y ya no queda ninguno, se rescata el más reciente del histórico para que
            // el documento no se quede sin archivo vigente teniendo uno disponible.
            $stillHasCurrent = DocumentVersion::query()
                ->where('document_id', $document->id)
                ->where('is_current', true)
                ->exists();

            if ($stillHasCurrent) {
                return;
            }

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

    // Documentos con empresa asignada requieren acceso a esa empresa;
    // documentos generales (company_id=null) son accesibles a cualquier usuario del mismo grupo.
    private function authorizeDocumentAccess($user, Document $document): void
    {
        if ($document->company_id !== null) {
            abort_unless($user->canAccessCompany($document->company), 403);
        } else {
            abort_unless(
                $user->isGlobalScope() || $user->group_id === $document->group_id,
                403
            );
        }
    }

    private function safeFilename(string $name): string
    {
        return preg_replace('/[^\w.\-]/', '_', $name);
    }
}
