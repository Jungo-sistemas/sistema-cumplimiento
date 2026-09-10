<?php

namespace App\Http\Controllers;

use App\Models\Regulation;
use App\Models\RegulationShare;
use App\Models\RegulationVersion;
use App\Models\User;
use App\Services\AiProcedureGenerationService;
use App\Services\ApprovalFlowService;
use App\Services\OfficeDocumentConverter;
use App\Services\RegulationChangeTableService;
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

        // Solo admins pueden subir un archivo como nueva versión — a diferencia de "Editar", que
        // sigue abierto a operativos.
        abort_unless($user->isAdmin(), 403);
        abort_unless($user->canAccessCompany($regulation->company), 403);
        abort_unless(! $regulation->hasActiveApprovalFlow(), 403, 'No se puede subir una nueva versión mientras el documento está en proceso de aprobación.');

        $wasApproved = $regulation->approval_status === 'approved';

        $data = $request->validate([
            // Ver comentario en RegulationController::storeCargar() — "mimes" rechaza .docx/.xlsx/.pptx
            // válidos en este entorno porque el detector de tipo de archivo del servidor los reporta
            // como "application/zip" en vez de su tipo específico. "extensions" valida la extensión.
            'file'               => ['required', 'file', 'max:10240', 'extensions:pdf,doc,docx,xls,xlsx,ppt,pptx'],
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

        // Si el archivo subido reemplaza un documento ya aprobado, ese contenido nuevo no ha sido
        // revisado por nadie — no puede seguir mostrándose como "aprobado".
        if ($wasApproved) {
            app(ApprovalFlowService::class)->resubmit($regulation);
        }

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Nueva versión subida correctamente.');
    }

    private const LOCK_MINUTES = 30;

    /**
     * Mismo criterio de acceso que RegulationController::show(): empresa propia, aprobación
     * pendiente asignada, o el documento compartido directamente con este usuario. Antes preview()
     * y download() solo revisaban canAccessCompany() — así que alguien de otra empresa del mismo
     * grupo a quien SÍ se le compartió el documento (o se le asignó como aprobador) pasaba el
     * candado de show() pero se topaba con un 403 al intentar ver o descargar el archivo en sí.
     */
    private function authorizeVersionAccess(RegulationVersion $version, User $user): void
    {
        $regulation = $version->regulation;

        $hasPendingApproval = app(ApprovalFlowService::class)
            ->getPendingApprovalForUser($regulation, $user->id) !== null;

        $hasShare = RegulationShare::where('regulation_id', $regulation->id)
            ->where('user_id', $user->id)
            ->exists();

        abort_unless(
            $user->canAccessCompany($regulation->company) || $hasPendingApproval || $hasShare,
            403
        );
    }

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

    /**
     * @return array{html: string, lostContent: bool}
     */
    private function docxToHtml(string $filePath): array
    {
        $phpWord = IOFactory::load($filePath);
        $writer  = IOFactory::createWriter($phpWord, 'HTML');
        $tmp     = tempnam(sys_get_temp_dir(), 'phpword_') . '.html';
        $writer->save($tmp);
        $raw = file_get_contents($tmp);
        @unlink($tmp);
        $html = preg_match('/<body[^>]*>(.*?)<\/body>/si', $raw, $m) ? $m[1] : $raw;

        // La sola presencia de tablas/imágenes en el .docx original NO implica que se vayan a
        // perder: probado con casos reales, el escritor HTML de PhpWord normalmente sí las
        // reconstruye bien. Lo que se compara aquí es el CONTEO antes/después — si el HTML
        // resultante trae menos tablas o imágenes que las que el lector encontró en el .docx
        // original, esas sí se perdieron de verdad para ESTE documento en particular.
        $original = $this->countComplexElements($phpWord);
        $rebuilt  = ['tables' => substr_count($html, '<table'), 'images' => substr_count($html, '<img')];
        $lostContent = $rebuilt['tables'] < $original['tables'] || $rebuilt['images'] < $original['images'];

        return ['html' => $html, 'lostContent' => $lostContent];
    }

    /** @return array{tables: int, images: int} */
    private function countComplexElements(PhpWord $phpWord): array
    {
        $counts = ['tables' => 0, 'images' => 0];

        foreach ($phpWord->getSections() as $section) {
            $this->countContainerComplexElements($section, $counts);
        }

        return $counts;
    }

    /** @param  array{tables: int, images: int}  $counts */
    private function countContainerComplexElements(object $container, array &$counts): void
    {
        // Table no expone getElements() (su contenido vive en filas → celdas, no en una lista
        // plana) — sin este caso especial, cualquier imagen dentro de una celda de tabla queda
        // invisible para el contador y el chequeo de pérdida no la detecta.
        if ($container instanceof \PhpOffice\PhpWord\Element\Table) {
            $counts['tables']++;

            foreach ($container->getRows() as $row) {
                foreach ($row->getCells() as $cell) {
                    $this->countContainerComplexElements($cell, $counts);
                }
            }

            return;
        }

        if (! method_exists($container, 'getElements')) {
            return;
        }

        foreach ($container->getElements() as $element) {
            if ($element instanceof \PhpOffice\PhpWord\Element\Image) {
                $counts['images']++;
            }

            $this->countContainerComplexElements($element, $counts);
        }
    }

    public function editForm(RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($version->regulation->isEditableBy($user), 403);

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

        // body_html es el HTML exacto con el que se compiló el .docx actual — cargarlo tal cual
        // evita reconvertir con PhpWord (que puede reescribir bgcolor/estilos de forma distinta)
        // y asegura que lo que se ve al editar es idéntico a "Ver"/"Descargar". Solo se reconvierte
        // para versiones sin body_html (subidas manualmente, de antes de esta columna).
        $reconstructedFromDocx = ! $hasDraft && ! $version->body_html;

        if ($reconstructedFromDocx) {
            // Guardar este HTML reconstruido lo deja como el nuevo body_html permanente — si para
            // ESTE documento en particular la reconstrucción perdió tablas o imágenes reales
            // (verificado por conteo, no solo "podría perder"), se bloquea de plano en vez de
            // guardarlo en silencio: ya pasó una vez con un documento real.
            $reconstructed = $this->docxToHtml(Storage::disk('private')->path($version->file_path));

            if ($reconstructed['lostContent']) {
                $this->clearLock($version, keepDraft: false);

                return redirect()
                    ->route('processes.show', $version->regulation)
                    ->with('error',
                        'Al reconstruir este documento para poder editarlo se perdió alguna tabla o imagen ' .
                        '(posiblemente el diagrama de flujo o una tabla de datos) — no se puede continuar sin ' .
                        'riesgo de perderla permanentemente. Usa "Prompt" (IA) para regenerarlo, o sube una ' .
                        'nueva versión en .docx desde "Subir versión".'
                    );
            }

            $bodyHtml = $reconstructed['html'];
        } else {
            $bodyHtml = $hasDraft ? $version->draft_html : $version->body_html;
        }

        $regulation = $version->regulation;

        $rejectionComment = $regulation->isRejected() ? $regulation->latestRejectionComment() : null;

        return view('regulation-versions.edit', compact('version', 'regulation', 'bodyHtml', 'hasDraft', 'rejectionComment', 'reconstructedFromDocx'));
    }

    public function saveDraft(Request $request, RegulationVersion $version)
    {
        $user = auth()->user();
        abort_unless($version->regulation->isEditableBy($user), 403);

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
                'url'  => route('processes.show', ['regulation' => $r->id, 'open_pdf' => 1]),
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

    public function saveEdit(Request $request, RegulationVersion $version, AiProcedureGenerationService $sanitizer, RegulationChangeTableService $changeTableService)
    {
        $user = auth()->user();
        abort_unless($version->regulation->isEditableBy($user), 403);

        // Auto-acquire lock if free (defensivo)
        if ($version->editing_by === null) {
            $this->acquireLock($version, $user->id);
            $version->refresh();
        }

        abort_unless($version->editing_by === $user->id, 403, 'No tienes el bloqueo de edición.');

        $data = $request->validate([
            'content'               => ['required', 'string'],
            'change_description'   => ['nullable', 'string', 'max:1000'],
            'change_justification' => ['required', 'string', 'max:1000'],
        ]);

        $regulation = $version->regulation;
        $wasApproved = $regulation->approval_status === 'approved';

        $changeDescription = $data['change_description'] ?? null;
        $changeJustification = $data['change_justification'];

        // El documento se guarda tal cual lo dejó el editor — el "qué cambió" para quien aprueba
        // se calcula aparte, sección por sección, al mostrar el flujo de aprobación (ver
        // RegulationChangeDiffService), comparando esta versión contra la anterior en el momento
        // de mostrarla, sin alterar el contenido guardado.
        $html = $sanitizer->sanitizeHtmlForWord($data['content']);

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

        // Se genera antes de la transacción: es una llamada a la IA (puede tardar unos segundos)
        // y de fallar no debe revertir nada — changeTableService ya atrapa sus propios errores y
        // regresa null en vez de lanzar, así que esto nunca bloquea el guardado de la versión.
        $changesTable = $changeTableService->generate($changeJustification, $changeDescription, $version->body_html, $html);

        DB::transaction(function () use ($regulation, $version, $tmp, $user, $changeDescription, $changeJustification, $changesTable, $next, $html) {
            $regulation->versions()->where('is_current', true)->update(['is_current' => false]);

            $rawName     = pathinfo($version->original_name ?? 'documento.docx', PATHINFO_FILENAME);
            $baseName    = preg_replace('/(_v\d+)+$/i', '', $rawName); // strip any previous _vN suffix
            $newName     = "{$baseName}_v{$next}.docx";
            $storagePath = "regulations/{$regulation->company_id}/{$regulation->id}/versions/{$newName}";

            Storage::disk('private')->put($storagePath, file_get_contents($tmp));

            RegulationVersion::create([
                'regulation_id'         => $regulation->id,
                'version_number'        => $next,
                'change_description'    => $changeDescription ?: 'Editado en línea',
                'change_justification'  => $changeJustification,
                'changes_table'         => $changesTable,
                'body_html'             => $html,
                'responsible_name'      => $user->name,
                'file_path'             => $storagePath,
                'original_name'         => $newName,
                'disk'                  => 'private',
                'mime_type'             => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'issued_at'             => now()->toDateString(),
                'valid_until'           => $version->valid_until,
                'is_current'            => true,
                'uploaded_by'           => $user->id,
            ]);

            // Release lock and clear draft on the original version
            $this->clearLock($version, false);
        });

        @unlink($tmp);

        // Si el reglamento estaba rechazado, esta edición libre es (se asume) la corrección —
        // avisar a los admins que ya pueden reiniciar el flujo.
        app(ApprovalFlowService::class)->notifyIfCorrectedAfterRejection($regulation);

        // Si en cambio ya estaba aprobado, el contenido acaba de cambiar bajo un documento que se
        // consideraba definitivo — no puede seguir "aprobado" sin que alguien lo revise de nuevo.
        if ($wasApproved) {
            app(ApprovalFlowService::class)->resubmit($regulation);
        }

        return redirect()
            ->route('processes.show', $regulation)
            ->with('success', 'Documento editado y guardado como nueva versión.');
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
                $url  = route('processes.show', ['regulation' => $reg->id, 'open_pdf' => 1]);
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
        $this->authorizeVersionAccess($version, $user);

        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));

        // .docx → render in browser. Si el sistema generó/editó esta versión, ya tenemos el HTML
        // exacto usado para compilar el .docx (body_html) — usarlo evita reconvertir el .docx con
        // PhpWord y arriesgar diferencias entre "Ver" y "Descargar". Solo se reconvierte para
        // versiones subidas manualmente (sin body_html).
        if ($ext === 'docx') {
            $bodyHtml = $version->body_html ?: $this->docxToHtml(Storage::disk('private')->path($version->file_path))['html'];
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
                    route('processes.show', ['regulation' => $r->id, 'open_pdf' => 1]),
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

            // Mismo encabezado fijo que ya se ve en la vista previa del wizard antes de confirmar
            // (processes/preview.blade.php) — aquí faltaba por completo: "Ver" sobre una versión ya
            // guardada (la pantalla que de verdad usa alguien para revisar el documento después)
            // nunca lo mostraba, así que el formato ya autorizado con el cliente parecía "perdido"
            // en pantalla aunque el .docx real sí lo trae siempre (RegulationDocxHeaderBuilder).
            $details = $regulation->details ?? [];
            $headerTableHtml = view('processes.partials.header-table', ['meta' => [
                'nombre'                   => $regulation->name,
                'codigo'                   => $regulation->code ? \Illuminate\Support\Str::upper($regulation->code) : null,
                'version'                  => sprintf('%02d', $version->version_number),
                'quien_elabora'            => $details['quien_elabora'] ?? null,
                'quien_aprueba'            => $details['quien_aprueba'] ?? null,
                'fecha_vigencia_formatted' => ! empty($details['fecha_vigencia'])
                    ? \Carbon\Carbon::parse($details['fecha_vigencia'])->format('d/m/Y')
                    : null,
            ]])->render();

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
<template id="doc-header-template">
  {$headerTableHtml}
</template>
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

        // Office (doc/xls/xlsx/ppt/pptx/...) → convertir a PDF con LibreOffice y mostrar ese PDF
        // (el navegador ya lo renderiza nativamente, no hace falta un visor propio por formato).
        // El resultado se cachea junto al archivo original: la conversión con LibreOffice tarda
        // varios segundos, no tiene sentido repetirla cada vez que alguien abre "Ver".
        $converter = app(OfficeDocumentConverter::class);

        if ($ext !== 'pdf' && $converter->isConvertible($ext)) {
            $previewPath = $version->file_path . '.preview.pdf';

            if (! Storage::disk('private')->exists($previewPath)) {
                $pdf = $converter->toPdf(Storage::disk('private')->path($version->file_path), $ext);

                if ($pdf !== null) {
                    Storage::disk('private')->put($previewPath, $pdf);
                }
            }

            if (Storage::disk('private')->exists($previewPath)) {
                return response()->file(
                    Storage::disk('private')->path($previewPath),
                    ['Content-Type' => 'application/pdf']
                );
            }

            // LibreOffice no disponible en este servidor / la conversión falló: cae a servir el
            // archivo original de todos modos, en vez de romper la vista previa por completo.
        }

        // PDF y cualquier otro formato sin conversión disponible → servir directo
        return response()->file(
            Storage::disk('private')->path($version->file_path),
            ['Content-Type' => $version->mime_type ?? 'application/octet-stream']
        );
    }

    public function download(RegulationVersion $version)
    {
        $user = auth()->user();
        $this->authorizeVersionAccess($version, $user);

        abort_unless($version->file_path && Storage::disk('private')->exists($version->file_path), 404);

        $ext = strtolower(pathinfo($version->original_name ?? $version->file_path, PATHINFO_EXTENSION));

        // Por ahora solo .docx: se descarga siempre como PDF sin importar que se haya guardado en
        // ese formato — mismo PDF cacheado (y mismo sidecar ".preview.pdf") que ya usa preview()
        // para otros formatos de Office. file_path es inmutable por versión (cada edición crea una
        // versión nueva, ver saveEdit()), así que este PDF cacheado nunca queda desactualizado.
        if ($ext === 'docx') {
            $converter = app(OfficeDocumentConverter::class);
            $previewPath = $version->file_path . '.preview.pdf';

            if (! Storage::disk('private')->exists($previewPath)) {
                $pdf = $converter->toPdf(Storage::disk('private')->path($version->file_path), 'docx');

                if ($pdf !== null) {
                    Storage::disk('private')->put($previewPath, $pdf);
                }
            }

            if (Storage::disk('private')->exists($previewPath)) {
                $downloadName = pathinfo($version->original_name ?? basename($version->file_path), PATHINFO_FILENAME) . '.pdf';

                return Storage::disk('private')->download($previewPath, $downloadName);
            }

            // LibreOffice no disponible / la conversión falló: cae a descargar el .docx original
            // en vez de romper la descarga por completo.
        }

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
