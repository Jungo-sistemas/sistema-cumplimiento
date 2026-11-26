<?php

namespace App\Services;

/**
 * Lee los pasos de un procedimiento P a partir del texto de su documento: una cadena por página,
 * como la entrega smalot/pdfparser. Es para los procesos subidos en Word o PDF, que no traen
 * body_html. Un paso es un renglón "Paso N. Título" (o "N. Título" en la plantilla anterior)
 * seguido de "Responsable:", con sus campos Quién / Qué / Cómo / Cuándo / Dónde / Evidencia.
 *
 * Regresa lo mismo que AuditChecklistService::parseSteps, más "otros": las etiquetas propias del
 * paso ("Riesgo de Seguridad y Sanciones: ...") como ['etiqueta' => ..., 'texto' => ...].
 */
class ProcedureActivityReader
{
    private const FIELDS = [
        'responsable' => '/^Responsables?\s*:\s*/iu',
        'quien'       => '/^Qui[eé]n(es)?\s*:\s*/iu',
        'que'         => '/^Qu[eé]\s*:\s*/iu',
        'como'        => '/^C[oó]mo\s*:\s*/iu',
        'cuando'      => '/^Cu[aá]ndo\s*:\s*/iu',
        'donde'       => '/^D[oó]nde\s*:\s*/iu',
        'evidencia'   => '/^Evidencias?(\s+requerida)?\s*:\s*/iu',
    ];

    /** Título de paso: "Paso 2. Entrega…" (también con ":" o guion) o, en la plantilla anterior, "2. Entrega…". */
    private const TITLE = '/^Paso\s+(\d+)\s*[.:\-–—]\s*(\S.*)$/iu';
    private const OLD_TITLE = '/^(\d{1,2})\.\s+(\p{Lu}.*)$/u';

    /** Punto numerado dentro de Cómo: "1.", "1.1", "6.1", pegado o no al texto, que empieza con mayúscula. */
    private const POINT = '/^(\d{1,2}\.(?:\d{1,2}\.?)*)\s*(?=[A-ZÁÉÍÓÚÑ“"¿¡])/u';

    /** Secciones que vienen después de las actividades. */
    private const END = '/^(Resultado\s*\/\s*Entregable|Riesgos\s+conocidos|Secci[oó]n\s+de\s+referencia|Historial\s+de\s+(revisiones|cambios)|Control\s+de\s+cambios|Anexos\s*:?$)/iu';

    /**
     * @param  string[]  $pages
     * @return array<int, array{numero:int, titulo:string, responsable:string, quien:string, que:string, como:array, cuando:string, donde:string, evidencia:string, otros:array}>
     */
    public static function steps(array $pages): array
    {
        $lines = self::cleanLines($pages);

        // Los pasos empiezan después de "Descripción del proceso / Actividades" (si no está, se lee todo).
        foreach ($lines as $i => $line) {
            if (preg_match('/descripci[oó]n\s+del\s+proceso\s*\/\s*actividades/iu', $line)) {
                $lines = array_slice($lines, $i + 1);
                break;
            }
        }

        $steps = [];
        $current = null;   // paso en curso
        $field = null;     // campo que se está llenando ('como', 'otros', ...)
        $skipping = false; // bloque que no es paso (p. ej. "Función Transversal") o fin de la sección

        $close = function () use (&$current, &$steps, &$field) {
            if ($current) {
                $steps[] = $current;
            }
            $current = null;
            $field = null;
        };

        foreach ($lines as $i => $line) {
            if (preg_match(self::END, $line)) {
                $close();
                $skipping = true;

                continue;
            }

            if ($title = self::titleAt($lines, $i)) {
                $close();
                $skipping = false;
                $current = ['numero' => (int) $title[1], 'titulo' => trim($title[2]), 'responsable' => '', 'quien' => '',
                    'que' => '', 'como' => [], 'cuando' => '', 'donde' => '', 'evidencia' => '', 'otros' => []];

                continue;
            }

            // Bloque que no es paso: otro "Responsable:" sin título de paso, o el párrafo que lo anuncia
            // ("Función Transversal — …"). Se omite completo para que no se pegue al paso anterior.
            $known = self::fieldOf($line);
            if ($current && $current['responsable'] !== '' && ($known === 'responsable'
                || ($known === null && preg_match('/[.:]$/u', $lines[$i - 1] ?? '') && self::blockAt($lines, $i)))) {
                $close();
                $skipping = true;

                continue;
            }
            if (! $current || $skipping) {
                continue;
            }

            if ($known) {
                $field = $known;
                $value = trim(preg_replace(self::FIELDS[$known], '', $line));
                if ($known === 'como') {
                    $current['como'] = $value === '' ? [] : [$value];
                } else {
                    $current[$known] = $value;
                }

                continue;
            }

            // Otra etiqueta al inicio de un párrafo ("Riesgo de Seguridad y Sanciones: ...").
            if (preg_match('/^(\p{Lu}[\p{L} ,\/—–-]{2,60}):\s*(.*)$/u', $line, $m)
                && preg_match_all('/[\p{L}\d]+/u', $m[1]) <= 8
                && preg_match('/[.:]$/u', $lines[$i - 1] ?? '')) {
                // "Cómo — Preparación:" o "Qué — Servicios y criterio de cobro:" van a su campo.
                $parts = preg_split('/\s*[—–-]\s*/u', trim($m[1]), 2);
                $base = count($parts) === 2 ? self::fieldOf($parts[0] . ':') : null;
                if ($base) {
                    $field = $base;
                    $text = $parts[1] . ': ' . trim($m[2]);
                    if ($base === 'como') {
                        $current['como'][] = $text;
                    } else {
                        $current[$base] = self::join($current[$base], $text);
                    }

                    continue;
                }

                $field = 'otros';
                $current['otros'][] = ['etiqueta' => trim($m[1]), 'texto' => trim($m[2])];

                continue;
            }

            // Continuación del campo en curso.
            if ($field === 'como') {
                if (preg_match(self::POINT, $line) || ! $current['como']) {
                    $current['como'][] = self::spacePoint($line);
                } else {
                    $last = count($current['como']) - 1;
                    $current['como'][$last] = self::join($current['como'][$last], $line);
                }
            } elseif ($field === 'otros') {
                $last = count($current['otros']) - 1;
                $current['otros'][$last]['texto'] = self::join($current['otros'][$last]['texto'], $line);
            } elseif ($field) {
                $current[$field] = self::join($current[$field], $line);
            } else {
                $current['titulo'] = self::join($current['titulo'], $line); // título en dos renglones
            }
        }
        $close();

        return $steps;
    }

    /**
     * Renglones del documento sin encabezados ni pies de página: lo que se repite arriba o abajo
     * en la mitad de las páginas o más (con los números cambiados por "#", por "Página 3 de 17").
     *
     * @param  string[]  $pages
     * @return string[]
     */
    private static function cleanLines(array $pages): array
    {
        $split = array_map(fn ($text) => array_values(array_filter(
            array_map(fn ($line) => trim(preg_replace('/[\t ]+/u', ' ', $line)), preg_split('/\R/u', $text)),
            fn ($line) => $line !== ''
        )), $pages);

        $key = fn ($line) => mb_strtolower(preg_replace('/\d+/u', '#', $line));
        // Un campo, un título o un punto nunca se quitan, aunque se repitan.
        $protected = fn ($line) => self::fieldOf($line) !== null || preg_match('/^Paso\s+\d/iu', $line) || preg_match(self::POINT, $line);

        $counts = [];
        foreach ($split as $lines) {
            $edges = array_unique(array_map($key, array_merge(array_slice($lines, 0, 25), array_slice($lines, -5))));
            foreach ($edges as $k) {
                $counts[$k] = ($counts[$k] ?? 0) + 1;
            }
        }
        $threshold = max(2, (int) ceil(count($split) * 0.5));
        $repeated = array_filter($counts, fn ($count) => $count >= $threshold);

        $out = [];
        foreach ($split as $lines) {
            while ($lines && isset($repeated[$key($lines[0])]) && ! $protected($lines[0])) {
                array_shift($lines);
            }
            while ($lines && isset($repeated[$key(end($lines))]) && ! $protected(end($lines))) {
                array_pop($lines);
            }
            array_push($out, ...$lines);
        }

        return $out;
    }

    /** El título de paso del renglón $i, si lo es: le sigue "Responsable:" en los 3 renglones siguientes. */
    private static function titleAt(array $lines, int $i): ?array
    {
        if (! preg_match(self::TITLE, $lines[$i], $m) && ! preg_match(self::OLD_TITLE, $lines[$i], $m)) {
            return null;
        }
        foreach (array_slice($lines, $i + 1, 3) as $next) {
            if (self::fieldOf($next) === 'responsable') {
                return $m;
            }
        }

        return null;
    }

    /** El renglón $i es el título de un bloque que no es paso: antes de otro campo o título llega "Responsable:". */
    private static function blockAt(array $lines, int $i): bool
    {
        for ($j = $i + 1; $j <= $i + 3 && $j < count($lines); $j++) {
            if (self::titleAt($lines, $j)) {
                return false;
            }
            if ($field = self::fieldOf($lines[$j])) {
                return $field === 'responsable';
            }
        }

        return false;
    }

    private static function fieldOf(string $line): ?string
    {
        foreach (self::FIELDS as $field => $pattern) {
            if (preg_match($pattern, $line)) {
                return $field;
            }
        }

        return null;
    }

    /** Une un renglón al párrafo anterior (sin espacio si venía cortado con guion: "F-" + "ALC-005"). */
    private static function join(string $previous, string $line): string
    {
        if ($previous === '') {
            return $line;
        }

        return preg_match('/[\p{L}\d]-$/u', $previous) ? $previous . $line : $previous . ' ' . $line;
    }

    /** "1.1Para dar" -> "1.1 Para dar" */
    private static function spacePoint(string $line): string
    {
        return preg_replace(self::POINT, '$1 ', $line, 1);
    }
}
