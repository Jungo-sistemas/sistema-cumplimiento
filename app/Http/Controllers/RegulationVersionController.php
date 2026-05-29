<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RegulationVersionController extends Controller
{
    public function store(Request $request, Regulation $regulation)
    {
        $user = auth()->user();

        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);

        $data = $request->validate([
            'file'               => ['required', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png'],
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
