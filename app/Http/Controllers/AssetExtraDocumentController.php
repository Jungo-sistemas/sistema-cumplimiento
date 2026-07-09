<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesCompany;
use App\Models\Asset;
use App\Models\AssetExtraDocument;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AssetExtraDocumentController extends Controller
{
    use ValidatesCompany;

    private function disk()
    {
        return Storage::disk('private');
    }

    public function index(Asset $asset)
    {
        $this->assertSameCompany($asset);

        $assetInactive = method_exists($asset, 'isInactive')
            ? $asset->isInactive()
            : ($asset->status === 'inactive');

        $documents = $asset->extraDocuments()
            ->with('uploader')
            ->latest()
            ->get();

        $navContext = [
            'asset' => $asset,
            'requirement' => null,
            'task' => null,
            'documentSection' => true,
            'documentOwner' => 'asset',
        ];

        return view('assets.extra-documents', [
            'asset' => $asset,
            'documents' => $documents,
            'assetInactive' => $assetInactive,
            'navContext' => $navContext,
        ]);
    }

    public function store(Request $request, Asset $asset)
    {
        $this->assertSameCompany($asset);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        if (method_exists($asset, 'isInactive') ? $asset->isInactive() : ($asset->status === 'inactive')) {
            return back()->with('error', 'El activo está desactivado. No puedes subir documentación extra.');
        }

        $dateMode = $request->input('date_mode', 'no_dates');

        $data = $request->validate([
            'date_mode' => ['required', 'in:no_dates,no_renewal,renewal'],
            'files'     => ['required', 'array', 'min:1', 'max:10'],
            'files.*'   => ['required', 'file', 'max:51200', 'mimes:pdf,jpg,jpeg,png,zip'],
            'issued_at' => ['nullable', 'date'],
            'expires_at' => $dateMode === 'renewal'
                ? ['required', 'date']
                : ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ], [
            'files.required'    => 'Debes adjuntar al menos un archivo.',
            'files.max'         => 'Puedes subir un máximo de 10 archivos a la vez.',
            'files.*.required'  => 'Cada archivo es obligatorio.',
            'files.*.mimes'     => 'Solo se permiten archivos PDF, JPG, PNG o ZIP.',
            'files.*.max'       => 'Cada archivo no puede superar los 50 MB.',
            'expires_at.required' => 'La fecha de vencimiento es obligatoria cuando el documento tiene renovación.',
        ]);

        $issuedAt  = in_array($dateMode, ['no_renewal', 'renewal']) ? ($data['issued_at'] ?? null) : null;
        $expiresAt = $dateMode === 'renewal' ? ($data['expires_at'] ?? null) : null;

        $directory = "companies/{$asset->company_id}/assets/{$asset->id}/extra-documents";

        foreach ($request->file('files', []) as $file) {
            $filename = now()->format('Ymd_His') . '_' . uniqid() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs($directory, $filename, 'private');

            AssetExtraDocument::create([
                'asset_id' => $asset->id,
                'company_id' => $asset->company_id,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size' => $file->getSize(),
                'issued_at' => $issuedAt,
                'expires_at' => $expiresAt,
                'notes' => $data['notes'] ?? null,
                'uploaded_by' => Auth::id(),
            ]);
        }

        $count = count($request->file('files', []));
        $msg   = $count > 1 ? "{$count} documentos guardados correctamente." : 'Documento guardado correctamente.';

        return back()->with('success', $msg);
    }

    public function preview(Asset $asset, AssetExtraDocument $document)
    {
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToAsset($document, $asset);

        if (! $this->disk()->exists($document->file_path)) {
            abort(404);
        }

        $fullPath = $this->disk()->path($document->file_path);

        $mime = $document->mime_type ?: (file_exists($fullPath) ? mime_content_type($fullPath) : null);

        $allowed = ['application/pdf', 'image/jpeg', 'image/png'];
        if (! $mime || ! in_array($mime, $allowed, true)) {
            abort(Response::HTTP_UNSUPPORTED_MEDIA_TYPE, 'Preview not supported');
        }

        return response()->file($fullPath, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="' . $document->original_name . '"',
        ]);
    }

    public function download(Asset $asset, AssetExtraDocument $document)
    {
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToAsset($document, $asset);

        if (! $this->disk()->exists($document->file_path)) {
            abort(404);
        }

        return $this->disk()->download(
            $document->file_path,
            $document->original_name ?? basename($document->file_path)
        );
    }

    public function destroy(Asset $asset, AssetExtraDocument $document)
    {
        $this->assertSameCompany($asset);
        $this->assertDocumentBelongsToAsset($document, $asset);
        abort_unless(auth()->user()->isAdmin() || auth()->user()->isOperative(), 403);

        if ($this->disk()->exists($document->file_path)) {
            $this->disk()->delete($document->file_path);
        }

        $document->delete();

        return back()->with('status', 'Documento eliminado correctamente.');
    }

    private function assertDocumentBelongsToAsset(AssetExtraDocument $document, Asset $asset): void
    {
        if ((int) $document->asset_id !== (int) $asset->id) {
            abort(404);
        }
    }
}
