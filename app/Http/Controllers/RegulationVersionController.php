<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\User;
use App\Services\AiProcedureGenerationService;
use App\Services\ChangeHighlightService;
use App\Services\RegulationDocxHeaderBuilder;
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

        // Acquire lock BEFORE converting docx, so the lock is set even if conversion is slow
        $hasDraft = $version->editing_by === $user->id && $version->draft_html !== null;
        $this->acquireLock($version, $user->id);

        $bodyHtml = $hasDraft
            ? $version->draft_html
            : $this->docxToHtml(Storage::disk('private')->path($version->file_path));

        $regulation = $version->regulation;

        return view('regulation-versions.edit', compact('version', 'regulation', 'bodyHtml', 'hasDraft'));
    }

    public function saveDraft(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);

        // Auto-acquire lock if free (defensivo: por si editForm falló en adquirirlo)
        if ($version->editing_by === null) {
            $this->acquireLock($version, $user->id);
            $version->refresh();
        }

        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $data = $request->validate(['content' => ['required', 'string']]);

        $version->update([
            'draft_html'         => $data['content'],
            'draft_saved_at'     => now(),
            'editing_expires_at' => now()->addMinutes(self::LOCK_MINUTES),
        ]);

        $version->refresh();

        return response()->json([
            'ok'         => true,
            'saved_at'   => now()->format('H:i:s'),
            'expires_at' => $version->editing_expires_at->format('H:i'),
        ]);
    }

    public function mentionUsers(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        $q = trim((string) $request->query('q', ''));

        $users = User::where('group_id', $version->regulation->group_id)
            ->where('status', 'active')
            // LOWER(...) LIKE en vez de LIKE: en Postgres (local) el operador LIKE es sensible a
            // mayúsculas por defecto — MySQL (producción) no, por su collation ci — esto funciona igual en ambos.
            ->when($q !== '', fn ($query) => $query->whereRaw('LOWER(name) LIKE ?', ['%' . mb_strtolower($q) . '%']))
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'name']);

        return response()->json($users);
    }

    public function mentionDocuments(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        $q          = trim((string) $request->query('q', ''));
        $regulation = $version->regulation;

        $docs = Regulation::where('company_id', $regulation->company_id)
            ->where('group_id', $regulation->group_id)
            ->where('is_active', true)
            ->where('id', '!=', $regulation->id)
            ->whereNotNull('code')
            ->when($q !== '', function ($query) use ($q) {
                $needle = '%' . mb_strtolower($q) . '%';
                $query->where(function ($qq) use ($needle) {
                    $qq->whereRaw('LOWER(code) LIKE ?', [$needle])
                       ->orWhereRaw('LOWER(name) LIKE ?', [$needle]);
                });
            })
            ->orderBy('code')
            ->limit(10)
            ->get(['id', 'code', 'name'])
            ->map(fn ($r) => [
                'id'   => $r->id,
                'code' => $r->code,
                'name' => $r->name,
                'url'  => route('processes.show', $r->id),
            ]);

        return response()->json($docs);
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

    public function saveEdit(Request $request, RegulationVersion $version, ChangeHighlightService $changeHighlight, AiProcedureGenerationService $sanitizer)
    {
        $user = auth()->user();
        abort_unless($user->isAdmin() || $user->isOperative(), 403);
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        // Auto-acquire lock if free (defensivo)
        if ($version->editing_by === null) {
            $this->acquireLock($version, $user->id);
            $version->refresh();
        }

        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $data = $request->validate([
            'content'            => ['required', 'string'],
            'change_description' => ['nullable', 'string', 'max:1000'],
        ]);

        $regulation = $version->regulation;

        $content = $data['content'];
        $changeDescription = $data['change_description'] ?? null;

        if ($version->file_path && Storage::disk('private')->exists($version->file_path)) {
            $oldHtml = $this->docxToHtml(Storage::disk('private')->path($version->file_path));
            $analysis = $changeHighlight->analyze($oldHtml, $content);

            if ($analysis !== null) {
                $content = $analysis['highlighted_html'];

                if (empty($changeDescription)) {
                    $changeDescription = $analysis['change_summary'];
                }
            }
        }

        $html = preg_replace(
            '/<mark\b[^>]*>(.*?)<\/mark>/si',
            '<span style="background-color: #FFFF00;">$1</span>',
            $content
        );
        $html = $sanitizer->sanitizeHtmlForWord($html);

        $next = ($regulation->versions()->max('version_number') ?? 0) + 1;
        $details = $regulation->details ?? [];

        \PhpOffice\PhpWord\Settings::setDefaultFontName('Arial');
        \PhpOffice\PhpWord\Settings::setDefaultFontSize(11);

        $phpWord = new PhpWord();
        $section = $phpWord->addSection([
            'paperSize'    => 'Letter',
            'marginTop'    => 2000,
            'marginBottom' => 1440,
            'marginLeft'   => 1440,
            'marginRight'  => 1440,
            'headerHeight' => 1300,
        ]);
        app(RegulationDocxHeaderBuilder::class)->apply($section, [
            'nombre'         => $regulation->name,
            'codigo'         => $regulation->code,
            'version'        => sprintf('%02d', $next),
            'quien_elabora'  => $details['quien_elabora'] ?? null,
            'quien_aprueba'  => $details['quien_aprueba'] ?? null,
            'fecha_vigencia' => $details['fecha_vigencia'] ?? null,
        ]);
        WordHtml::addHtml($section, $html, false, false);

        $tmp = tempnam(sys_get_temp_dir(), 'edited_docx_');
        IOFactory::createWriter($phpWord, 'Word2007')->save($tmp);

        DB::transaction(function () use ($regulation, $version, $tmp, $user, $changeDescription, $next) {
            $regulation->versions()->where('is_current', true)->update(['is_current' => false]);

            $rawName     = pathinfo($version->original_name ?? 'documento.docx', PATHINFO_FILENAME);
            $baseName    = preg_replace('/(_v\d+)+$/i', '', $rawName); // strip any previous _vN suffix
            $newName     = "{$baseName}_v{$next}.docx";
            $storagePath = "regulations/{$regulation->company_id}/{$regulation->id}/versions/{$newName}";

            Storage::disk('private')->put($storagePath, file_get_contents($tmp));

            RegulationVersion::create([
                'regulation_id'      => $regulation->id,
                'version_number'     => $next,
                'change_description' => $changeDescription ?: 'Editado en línea',
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

    /**
     * Scan HTML text nodes for regulation codes from the same company and wrap them with links.
     * Auto-detection: no manual annex setup required — any code found in the text that matches
     * an existing regulation in the company becomes a clickable link.
     *
     * @param  string  $html
     * @param  \Illuminate\Support\Collection  $regulations  Collection of {id, code, name}
     * @return array{html: string, linked: \Illuminate\Support\Collection}
     */
    private function linkRegulationCodes(string $html, \Illuminate\Support\Collection $regulations): array
    {
        if ($regulations->isEmpty()) return ['html' => $html, 'linked' => collect()];

        // Split into [text, <tag>, text, <tag>, ...] — only touch text segments
        $parts = preg_split('/(<[^>]+>)/s', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Longer codes first so "F-SAV-001A" matches before "F-SAV-001"
        $sorted = $regulations->filter(fn ($r) => !empty($r->code))
                              ->sortByDesc(fn ($r) => strlen($r->code));

        $linked = collect();

        foreach ($parts as &$part) {
            if (str_starts_with($part, '<')) continue;
            foreach ($sorted as $reg) {
                $encoded = htmlspecialchars($reg->code);
                if (!str_contains($part, $encoded)) continue;
                $url  = route('processes.show', $reg->id);
                $tip  = htmlspecialchars($reg->name, ENT_QUOTES);
                $link = '<a href="' . $url . '" target="_blank"'
                      . ' style="color:#1d4ed8;font-weight:600;text-decoration:underline;white-space:nowrap;"'
                      . ' title="' . $tip . '">' . $encoded . '</a>';
                $part = str_replace($encoded, $link, $part);
                $linked->put($reg->id, $reg); // track which were actually found
            }
        }

        return ['html' => implode('', $parts), 'linked' => $linked->values()];
    }

    public function preview(RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($user->canAccessCompany($version->regulation->company), 403);

        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));

        // .docx → convert to HTML and render in browser
        if ($ext === 'docx') {
            $bodyHtml = $this->docxToHtml(Storage::disk('private')->path($version->file_path));
            $name     = $version->original_name ?? basename($version->file_path);

            // Auto-detect any regulation code from the same company in the document text
            $regulation    = $version->regulation;
            $allRegs       = Regulation::where('company_id', $regulation->company_id)
                                ->where('group_id', $regulation->group_id)
                                ->where('is_active', true)
                                ->where('id', '!=', $regulation->id)
                                ->whereNotNull('code')
                                ->get(['id', 'code', 'name']);

            ['html' => $bodyHtml, 'linked' => $linked] = $this->linkRegulationCodes($bodyHtml, $allRegs);

            // Collapsed legend showing only codes actually found in this document
            $legendHtml = '';
            if ($linked->isNotEmpty()) {
                $items = $linked->map(fn ($r) => sprintf(
                    '<li><a href="%s" target="_blank" style="color:#1d4ed8;font-weight:600;">%s</a>&nbsp;&mdash;&nbsp;%s</li>',
                    route('processes.show', $r->id),
                    htmlspecialchars($r->code),
                    htmlspecialchars($r->name)
                ))->implode('');
                $legendHtml = '<details id="legend">'
                    . '<summary>DOCUMENTOS REFERENCIADOS EN ESTE TEXTO (' . $linked->count() . ')</summary>'
                    . '<ul>' . $items . '</ul>'
                    . '</details>';
            }

            $downloadUrl = route('regulation-versions.download', $version);
            $backUrl     = route('processes.show', $regulation);
            $versionLabel = 'v' . $version->version_number;
            $regCode      = htmlspecialchars($regulation->code ?? '');
            $regName      = htmlspecialchars($regulation->name);
            $paginationCssUrl = asset('css/document-pagination.css');
            $paginationJsUrl  = asset('js/document-pagination.js');

            $html = <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$name}</title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    background: #e5e7eb;
    min-height: 100vh;
    padding-top: 56px;
  }

  /* ── Top bar ── */
  #topbar {
    position: fixed; top: 0; left: 0; right: 0; height: 56px; z-index: 100;
    background: #1A428A;
    display: flex; align-items: center; gap: 12px; padding: 0 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,.25);
  }
  #topbar .back {
    color: rgba(255,255,255,.7); text-decoration: none; font-size: 20px; line-height: 1;
    padding: 4px 6px; border-radius: 4px; transition: background .15s;
  }
  #topbar .back:hover { background: rgba(255,255,255,.15); color: #fff; }
  #topbar .doc-info { flex: 1; min-width: 0; }
  #topbar .doc-name {
    color: #fff; font-size: .9rem; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
  }
  #topbar .doc-meta { color: rgba(255,255,255,.6); font-size: .75rem; margin-top: 1px; }
  #topbar .dl-btn {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(255,255,255,.15); border: 1px solid rgba(255,255,255,.3);
    color: #fff; font-size: .8rem; font-weight: 600;
    padding: 6px 14px; border-radius: 6px; text-decoration: none;
    white-space: nowrap; transition: background .15s;
  }
  #topbar .dl-btn:hover { background: rgba(255,255,255,.25); }

  /* ── Annex legend ── */
  #legend-wrap { max-width: 820px; margin: 0 auto 60px; }
  #legend {
    margin-top: 40px;
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
  }
  #legend summary {
    cursor: pointer; user-select: none;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: .78rem; font-weight: 700; letter-spacing: .06em;
    color: #6b7280; list-style: none;
    display: flex; align-items: center; gap: 6px;
  }
  #legend summary::before { content: '▶'; font-size: .65rem; transition: transform .2s; }
  #legend[open] summary::before { transform: rotate(90deg); }
  #legend ul {
    margin: 12px 0 0 4px; padding: 0;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
    font-size: .875rem; line-height: 2.2; list-style: none;
  }
  #legend ul a { color: #1d4ed8; font-weight: 600; text-decoration: none; }
  #legend ul a:hover { text-decoration: underline; }
</style>
<link rel="stylesheet" href="{$paginationCssUrl}">
</head>
<body>

<div id="topbar">
  <a href="{$backUrl}" class="back" title="Volver">&#8592;</a>
  <div class="doc-info">
    <div class="doc-name">{$regName}</div>
    <div class="doc-meta">{$regCode} &nbsp;·&nbsp; {$versionLabel} &nbsp;·&nbsp; {$name}</div>
  </div>
  <a href="{$downloadUrl}" class="dl-btn">&#8595; Descargar</a>
</div>

<div id="doc-source" style="display: none;">
  {$bodyHtml}
</div>
<div id="doc-pages"></div>

<div id="legend-wrap">
  {$legendHtml}
</div>

<script src="{$paginationJsUrl}"></script>
</body>
</html>
HTML;
            return response($html, 200)->header('Content-Type', 'text/html; charset=UTF-8');
        }

        // PDF and other viewable formats → serve directly
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
