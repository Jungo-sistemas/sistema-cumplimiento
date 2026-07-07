<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html as WordHtml;

class RegulationVersionController extends Controller
{
    public function store(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'file'               => ['required', 'file', 'max:10240', 'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx'],
            'change_description' => ['nullable', 'string', 'max:1000'],
            'responsible_name'   => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($data, $request, $regulation, $user) {
            // Mark previous current version as not current
            $regulation->versions()->where('is_current', true)->update(['is_current' => false]);

            $nextVersion = ($regulation->versions()->max('version_number') ?? 0) + 1;

            $file = $request->file('file');
            $path = $file->store(
                "regulations/{$regulation->company_id}/{$regulation->id}/versions",
                'private'
            );

            RegulationVersion::create([
                'regulation_id'      => $regulation->id,
                'version_number'     => $nextVersion,
                'change_description' => $data['change_description'] ?? null,
                'responsible_name'   => $data['responsible_name'] ?? null,
                'file_path'          => $path,
                'original_name'      => $file->getClientOriginalName(),
                'disk'               => 'private',
                'mime_type'          => $file->getMimeType(),
                'issued_at'          => now()->toDateString(),
                'valid_until'        => now()->addYear()->toDateString(),
                'is_current'         => true,
                'uploaded_by'        => $user->id,
            ]);
        });

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Nueva versión subida correctamente.');
    }

    private const LOCK_MINUTES = 30;

    private function isLockedByOther(RegulationVersion $version, int $userId): bool
    {
        return $version->editing_by
            && $version->editing_by !== $userId
            && $version->editing_expires_at
            && $version->editing_expires_at->isFuture();
    }

    private function acquireLock(RegulationVersion $version, int $userId): void
    {
        $version->update([
            'editing_by'          => $userId,
            'editing_expires_at'  => now()->addMinutes(self::LOCK_MINUTES),
        ]);
    }

    private function clearLock(RegulationVersion $version, bool $keepDraft = false): void
    {
        $version->update(array_merge(
            ['editing_by' => null, 'editing_expires_at' => null],
            $keepDraft ? [] : ['draft_html' => null, 'draft_saved_at' => null]
        ));
    }

    private function docxToHtml(string $filePath): string
    {
        $phpWord = IOFactory::load($filePath);
        $writer  = IOFactory::createWriter($phpWord, 'HTML');
        $tmp     = tempnam(sys_get_temp_dir(), 'phpword_') . '.html';
        $writer->save($tmp);
        $raw = file_get_contents($tmp);
        @unlink($tmp);
        return preg_match('/<body[^>]*>(.*?)<\/body>/si', $raw, $m) ? $m[1] : $raw;
    }

    public function editForm(RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));
        abort_unless($ext === 'docx', 422, 'Solo se pueden editar archivos .docx');
        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        // Check if another user has an active lock
        if ($this->isLockedByOther($version, $user->id)) {
            $lockedBy = \App\Models\User::find($version->editing_by);
            return redirect()
                ->route('processes.show', $version->regulation)
                ->with('error',
                    'El documento está siendo editado por ' . ($lockedBy?->name ?? 'otro usuario') .
                    '. El bloqueo expira a las ' . $version->editing_expires_at->format('H:i') . '.'
                );
        }

        // Determine content: resume draft if the same user has one, otherwise load docx
        $hasDraft    = $version->editing_by === $user->id && $version->draft_html !== null;
        $bodyHtml    = $hasDraft
            ? $version->draft_html
            : $this->docxToHtml(Storage::disk('private')->path($version->file_path));

        // Acquire (or renew) the lock
        $this->acquireLock($version, $user->id);

        $regulation = $version->regulation;

        return view('regulation-versions.edit', compact('version', 'regulation', 'bodyHtml', 'hasDraft'));
    }

    public function saveDraft(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $data = $request->validate(['content' => ['required', 'string']]);

        $version->update([
            'draft_html'         => $data['content'],
            'draft_saved_at'     => now(),
            'editing_expires_at' => now()->addMinutes(self::LOCK_MINUTES),
        ]);

        return response()->json([
            'ok'         => true,
            'saved_at'   => now()->format('H:i:s'),
            'expires_at' => $version->editing_expires_at->format('H:i'),
        ]);
    }

    public function releaseLock(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $keepDraft = filter_var($request->input('keep_draft', false), FILTER_VALIDATE_BOOLEAN);
        $this->clearLock($version, $keepDraft);

        return redirect()
            ->route('processes.show', $version->regulation)
            ->with('success', $keepDraft
                ? 'Edición pausada. Tu borrador fue conservado — puedes retomarlo cuando quieras.'
                : 'Edición cancelada.'
            );
    }

    public function saveEdit(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($version->regulation->company), 403);
        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $data = $request->validate([
            'content'            => ['required', 'string'],
            'change_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $regulation = $version->regulation;

        $html = preg_replace(
            '/<mark\b[^>]*>(.*?)<\/mark>/si',
            '<span style="background-color: #FFFF00;">$1</span>',
            $data['content']
        );

        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        WordHtml::addHtml($section, $html, false, false);

        $tmp = tempnam(sys_get_temp_dir(), 'edited_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        DB::transaction(function () use ($regulation, $version, $tmp, $user, $data) {
            $regulation->versions()->where('is_current', true)->update(['is_current' => false]);

            $next        = ($regulation->versions()->max('version_number') ?? 0) + 1;
            $baseName    = pathinfo($version->original_name ?? 'documento.docx', PATHINFO_FILENAME);
            $newName     = "{$baseName}_v{$next}.docx";
            $storagePath = "regulations/{$regulation->company_id}/{$regulation->id}/versions/{$newName}";

            Storage::disk('private')->put($storagePath, file_get_contents($tmp));

            RegulationVersion::create([
                'regulation_id'      => $regulation->id,
                'version_number'     => $next,
                'change_description' => $data['change_description'] ?? 'Editado en línea',
                'responsible_name'   => $user->name,
                'file_path'          => $storagePath,
                'original_name'      => $newName,
                'disk'               => 'private',
                'mime_type'          => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'issued_at'          => now()->toDateString(),
                'valid_until'        => $version->valid_until,
                'is_current'         => true,
                'uploaded_by'        => $user->id,
            ]);

            // Release lock and clear draft on the original version
            $this->clearLock($version, false);
        });

        @unlink($tmp);

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento editado y guardado como nueva versión con cambios resaltados.');
    }

    public function preview(RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        return response()->file(
            Storage::disk('private')->path($version->file_path),
            ['Content-Type' => $version->mime_type ?? 'application/octet-stream']
        );
    }

    public function download(RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        return Storage::disk('private')->download(
            $version->file_path,
            $version->original_name ?? basename($version->file_path)
        );
    }

    public function destroy(Regulation $regulation, RegulationVersion $version)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_unless($version->regulation_id === $regulation->id, 403);

        DB::transaction(function () use ($regulation, $version) {
            $wasCurrent = $version->is_current;

            if ($version->file_path) {
                Storage::disk('private')->delete($version->file_path);
            }

            $version->delete();

            // Promote latest remaining version as current
            if ($wasCurrent) {
                $latest = $regulation->versions()->orderByDesc('version_number')->first();
                $latest?->update(['is_current' => true]);
            }
        });

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Versión eliminada correctamente.');
    }
}
