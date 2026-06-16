<?php

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DocumentTrashController extends Controller
{
    private function disk()
    {
        return Storage::disk('private');
    }

    public function index()
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $documents = Document::onlyTrashed()
            ->with([
                'folder.parent',
                'company:id,name',
                'uploader:id,name',
                'deletedBy:id,name',
                'versions' => fn ($q) => $q->orderByDesc('version_number'),
            ])
            ->where('group_id', $user->group_id)
            ->orderByDesc('deleted_at')
            ->get();

        return view('documents.trash', compact('documents'));
    }

    public function restore(int $id)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $document = Document::onlyTrashed()
            ->where('group_id', $user->group_id)
            ->findOrFail($id);

        $document->restore();
        $document->update([
            'deleted_by'            => null,
            'permanently_delete_at' => null,
        ]);

        return back()->with('success', "El documento \"{$document->name}\" fue restaurado correctamente.");
    }

    public function forceDestroy(int $id)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin(), 403);

        $document = Document::onlyTrashed()
            ->where('group_id', $user->group_id)
            ->with('versions')
            ->findOrFail($id);

        DB::transaction(function () use ($document) {
            foreach ($document->versions as $version) {
                if ($this->disk()->exists($version->file_path)) {
                    $this->disk()->delete($version->file_path);
                }
                $version->delete();
            }

            $document->forceDelete();
        });

        return back()->with('success', 'El documento fue eliminado permanentemente.');
    }
}
