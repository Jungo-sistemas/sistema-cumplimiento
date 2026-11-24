<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditChecklistItem;
use App\Models\AuditFinding;
use App\Models\User;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Checklist que el auditor captura durante la llamada de Teams.
 *
 * Los criterios se copian a la auditoría la primera vez que se abre: la pregunta fija (va en todas)
 * y un criterio por cada "Paso N." de la versión vigente del proceso (los procesos hechos con el
 * asistente traen Quién/Qué/Cómo/Cuándo/Dónde/Evidencia). Los procesos subidos en Word no traen
 * pasos: ahí el auditor agrega sus propios criterios.
 *
 * Los hallazgos se guardan como borrador en su criterio y se vuelven hallazgos reales (con sus
 * avisos) al finalizar, junto con la calificación.
 */
class AuditChecklistService
{
    public const FIXED_QUESTION = [
        'text'        => 'El auditado conoce sus procedimientos y sabe dónde consultarlos.',
        'description' => 'Pídele al auditado que explique con sus palabras cómo realiza la actividad y que te muestre dónde consulta la versión vigente de su procedimiento.',
        'evidence'    => 'Explicación del auditado y ubicación del procedimiento vigente',
    ];

    public function __construct(private AuditFindingService $findings) {}

    /** Crea los criterios de la auditoría si todavía no tiene. */
    public function ensureItems(Audit $audit): void
    {
        if ($audit->checklistItems()->exists()) {
            return;
        }

        DB::transaction(function () use ($audit) {
            foreach ($this->initialItems($audit) as $attributes) {
                $audit->checklistItems()->create($attributes);
            }
        });
        $audit->unsetRelation('checklistItems');
    }

    /** Los criterios que tendrá, sin guardarlos (para consultarlos antes del día). */
    public function previewItems(Audit $audit): \Illuminate\Support\Collection
    {
        return collect($this->initialItems($audit))->map(function ($attributes) {
            $item = new AuditChecklistItem($attributes);
            $item->setRelation('files', collect());

            return $item;
        });
    }

    private function initialItems(Audit $audit): array
    {
        $position = 0;
        $items = [self::FIXED_QUESTION + ['position' => $position++, 'source' => AuditChecklistItem::SOURCE_FIXED]];

        $html = $audit->regulation?->currentVersion?->body_html;
        foreach (self::parseSteps((string) $html) as $step) {
            $items[] = [
                'position'    => $position++,
                'source'      => AuditChecklistItem::SOURCE_PROCESS,
                'text'        => "Paso {$step['numero']}. {$step['titulo']}",
                'description' => $step['que'] ? 'Se realiza como indica el procedimiento: ' . $step['que'] : 'Se realiza como indica el procedimiento.',
                'evidence'    => $step['evidencia'] ?: null,
                'activity'    => $step,
            ];
        }

        return $items;
    }

    /**
     * Pasos del procedimiento a partir del HTML de su versión: un renglón "Paso N. Título" seguido
     * de "Responsable:", "Quién:", "Qué:", "Cómo:", "Cuándo:", "Dónde:" y "Evidencia requerida:".
     *
     * @return array<int, array{numero:int, titulo:string, responsable:string, quien:string, que:string, como:array, cuando:string, donde:string, evidencia:string}>
     */
    public static function parseSteps(string $html): array
    {
        if (trim($html) === '' || ! preg_match('/Paso\s+\d+\./u', $html)) {
            return [];
        }

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?><div>' . $html . '</div>');
        libxml_clear_errors();

        $fields = [
            'responsable' => '/^Responsable\s*:\s*/iu',
            'quien'       => '/^Qui[eé]n\s*:\s*/iu',
            'que'         => '/^Qu[eé]\s*:\s*/iu',
            'como'        => '/^C[oó]mo\s*:\s*/iu',
            'cuando'      => '/^Cu[aá]ndo\s*:\s*/iu',
            'donde'       => '/^D[oó]nde\s*:\s*/iu',
            'evidencia'   => '/^Evidencia(\s+requerida)?\s*:\s*/iu',
        ];

        $steps = [];
        $current = null;
        foreach ((new DOMXPath($dom))->query('//p | //h1 | //h2 | //h3 | //h4 | //h5 | //h6 | //li') as $node) {
            $text = trim(preg_replace('/\s+/u', ' ', $node->textContent));
            if ($text === '') {
                continue;
            }

            if (preg_match('/^Paso\s+(\d+)\.\s*(.+)$/u', $text, $m)) {
                if ($current) {
                    $steps[] = $current;
                }
                $current = ['numero' => (int) $m[1], 'titulo' => $m[2], 'responsable' => '', 'quien' => '', 'que' => '', 'como' => [], 'cuando' => '', 'donde' => '', 'evidencia' => ''];

                continue;
            }

            if (! $current) {
                continue;
            }

            // Otro título que no es paso: terminó la sección de actividades.
            if (preg_match('/^h[1-6]$/i', $node->nodeName)) {
                $steps[] = $current;
                $current = null;

                continue;
            }

            foreach ($fields as $key => $pattern) {
                if (preg_match($pattern, $text)) {
                    $value = trim(preg_replace($pattern, '', $text));
                    $current[$key] = $key === 'como' ? [$value] : $value;
                    break;
                }
            }
        }

        if ($current) {
            $steps[] = $current;
        }

        return $steps;
    }

    /** El auditor agrega un criterio propio (al final de la lista). */
    public function addItem(Audit $audit, string $text, ?string $evidence): AuditChecklistItem
    {
        return $audit->checklistItems()->create([
            'position' => (int) $audit->checklistItems()->max('position') + 1,
            'source'   => AuditChecklistItem::SOURCE_AUDITOR,
            'text'     => trim($text),
            'evidence' => trim((string) $evidence) ?: null,
        ]);
    }

    /**
     * Guarda el avance: resultado, observaciones y hallazgos en borrador de cada criterio, la lista
     * de asistencia y las conclusiones.
     *
     * @param array{items?: array, attendance?: array, conclusions?: ?string} $data
     */
    public function save(Audit $audit, array $data): void
    {
        $auditeeIds = $audit->auditees()->pluck('users.id')->all();
        $allowedAttendance = array_merge($auditeeIds, array_filter([$audit->auditor_id]));

        DB::transaction(function () use ($audit, $data, $auditeeIds, $allowedAttendance) {
            $items = $audit->checklistItems()->get()->keyBy('id');

            foreach ($data['items'] ?? [] as $row) {
                $item = $items->get((int) ($row['id'] ?? 0));
                if (! $item) {
                    continue;
                }

                $result = $row['result'] ?? null;
                $item->update([
                    'result'         => array_key_exists((string) $result, AuditChecklistItem::RESULTS) ? $result : null,
                    'observations'   => trim((string) ($row['observations'] ?? '')) ?: null,
                    'draft_findings' => $this->cleanDrafts($row['findings'] ?? [], $auditeeIds),
                ]);
            }

            $audit->update([
                'attendance'  => array_values(array_intersect($allowedAttendance, array_map('intval', $data['attendance'] ?? []))),
                'conclusions' => trim((string) ($data['conclusions'] ?? '')) ?: null,
            ]);
        });
    }

    private function cleanDrafts(array $drafts, array $auditeeIds): array
    {
        $clean = [];
        foreach ($drafts as $draft) {
            $title = trim((string) ($draft['titulo'] ?? ''));
            $description = trim((string) ($draft['descripcion'] ?? ''));
            $severity = (string) ($draft['severidad'] ?? '');
            $responsible = (int) ($draft['responsable_id'] ?? 0);

            if ($title === '' || $description === '' || ! array_key_exists($severity, AuditFinding::SEVERITIES)
                || ! in_array($responsible, $auditeeIds, true)) {
                continue;
            }

            $clean[] = [
                'titulo'         => mb_substr($title, 0, 255),
                'descripcion'    => mb_substr($description, 0, 5000),
                'severidad'      => $severity,
                'responsable_id' => $responsible,
            ];
        }

        return $clean;
    }

    /**
     * Finaliza: revisa que esté completo, calcula la calificación (promedio de los criterios
     * evaluados; No aplica no cuenta), envía los hallazgos a sus responsables y avisa a los auditados.
     *
     * @throws ValidationException si falta algo
     */
    public function finalize(Audit $audit): void
    {
        $audit->refresh();
        $items = $audit->checklistItems()->get();
        $errors = [];

        if ($items->contains(fn ($item) => $item->result === null)) {
            $errors[] = 'Captura el resultado de cada criterio.';
        }
        $attended = $audit->attendeeIds();
        $auditeeIds = $audit->auditees()->pluck('users.id')->all();
        if (! in_array($audit->auditor_id, $attended, true) || ! array_intersect($auditeeIds, $attended)) {
            $errors[] = 'Marca en la lista de asistencia al auditor y al menos a un auditado.';
        }
        if ($items->contains(fn ($item) => in_array($item->result, AuditChecklistItem::NEEDS_FINDING, true) && empty($item->draft_findings))) {
            $errors[] = 'Registra un hallazgo en cada criterio con No cumple o Parcial.';
        }
        if ($errors) {
            throw ValidationException::withMessages(['checklist' => $errors]);
        }

        $evaluated = $items->map->points()->filter(fn ($points) => $points !== null);
        $score = $evaluated->isEmpty() ? null : (int) round($evaluated->avg());

        $responsibles = User::whereIn('id', $items->pluck('draft_findings')->flatten(1)->pluck('responsable_id')->filter()->unique())
            ->get()->keyBy('id');

        foreach ($items as $item) {
            foreach ($item->draft_findings ?? [] as $draft) {
                $responsible = $responsibles->get($draft['responsable_id']);
                if ($responsible) {
                    $this->findings->register($audit, $responsible, $draft['titulo'], $draft['descripcion'], $draft['severidad'], $item->id);
                }
            }
            $item->update(['draft_findings' => null]);
        }

        $audit->update(['score' => $score]);
        $this->findings->complete($audit, $score === null ? 'No aplica' : "{$score} / 100");
    }
}
