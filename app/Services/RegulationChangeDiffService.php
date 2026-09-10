<?php

namespace App\Services;

/**
 * Compara el body_html de dos versiones de un reglamento, sección por sección (usando los mismos
 * títulos que escribe RegulationBodyHtmlBuilder::SECTION_TITLES), para mostrarle al aprobador
 * exactamente qué cambió en el flujo de aprobación — sin IA: un diff de texto es determinista y
 * nunca "no capta bien los cambios", a diferencia del resaltado automático que tenía antes este
 * proyecto (ChangeHighlightService, removido por no ser confiable). Es de solo lectura: nunca
 * modifica los HTML que recibe, solo los compara para mostrarlos en una tabla.
 */
class RegulationChangeDiffService
{
    /**
     * @return array<int, array{title: string, before: string, after: string}>  Solo las secciones
     *         que de verdad cambiaron (comparando texto plano, normalizado). Si alguna de las dos
     *         versiones no tiene body_html (p. ej. se subió como archivo en vez de redactarse en el
     *         sistema), regresa un arreglo vacío — no hay nada que diferenciar sección por sección.
     */
    public function diff(?string $oldHtml, ?string $newHtml): array
    {
        if (! $oldHtml || ! $newHtml) {
            return [];
        }

        $oldSections = $this->splitSections($oldHtml);
        $newSections = $this->splitSections($newHtml);

        // Umbral, no "está vacío": documentos de antes de que existiera este formato de secciones
        // fijas (o muy editados a mano) pueden reconocer 1 o 2 títulos por pura coincidencia de
        // texto, sin que el resto de la estructura exista de verdad — comparar título por título ahí
        // sería engañoso (secciones ausentes de los dos lados se ignoran en silencio, dando una
        // falsa sensación de "sin cambios"). Si no se reconoce al menos la mitad de los títulos
        // conocidos en AMBOS lados, se compara el documento completo como un solo bloque.
        $minRecognized = (int) ceil(count(RegulationBodyHtmlBuilder::SECTION_TITLES) / 2);

        if (count($oldSections) < $minRecognized || count($newSections) < $minRecognized) {
            return $this->wholeDocumentDiff($oldHtml, $newHtml);
        }

        $changed = [];

        foreach (RegulationBodyHtmlBuilder::SECTION_TITLES as $title) {
            $before = $oldSections[$title] ?? null;
            $after  = $newSections[$title] ?? null;

            // Ninguna de las dos versiones tiene esta sección (p. ej. no aplica al tipo de
            // documento) — nada que comparar.
            if ($before === null && $after === null) {
                continue;
            }

            $beforeText = $this->toPlainText($before ?? '');
            $afterText  = $this->toPlainText($after ?? '');

            if ($beforeText === $afterText) {
                continue;
            }

            $changed[] = [
                'title'  => $title,
                'before' => $beforeText !== '' ? $beforeText : '(sin contenido)',
                'after'  => $afterText !== '' ? $afterText : '(sin contenido)',
            ];
        }

        return $changed;
    }

    /**
     * Respaldo para documentos sin la estructura de secciones fijas (de antes de que existiera, o
     * muy editados a mano): en vez de mostrar el documento completo (potencialmente enorme, todo lo
     * opuesto a "puntual"), compara párrafo por párrafo y solo regresa los que de verdad son
     * distintos de un lado al otro — un párrafo que no cambió, aunque haya cambiado de posición, no
     * cuenta como cambio.
     *
     * @return array<int, array{title: string, before: string, after: string}>
     */
    private function wholeDocumentDiff(string $oldHtml, string $newHtml): array
    {
        $beforeLines = $this->plainTextLines($oldHtml);
        $afterLines  = $this->plainTextLines($newHtml);

        $removed = array_values(array_diff($beforeLines, $afterLines));
        $added   = array_values(array_diff($afterLines, $beforeLines));

        if ($removed === [] && $added === []) {
            return [];
        }

        return [[
            'title'  => 'Documento completo',
            'before' => $removed !== [] ? implode("\n", $removed) : '(sin cambios en este lado)',
            'after'  => $added !== [] ? implode("\n", $added) : '(sin cambios en este lado)',
        ]];
    }

    /**
     * @return array<string, string>  Título de sección => HTML entre su marcador y el siguiente
     *         (o el final del documento). Un título que no aparece en $html simplemente no queda
     *         en el resultado — pasa, por ejemplo, si esa sección se borró por completo.
     */
    private function splitSections(string $html): array
    {
        $titles = array_map(fn (string $t) => preg_quote($t, '/'), RegulationBodyHtmlBuilder::SECTION_TITLES);

        // RegulationBodyHtmlBuilder escribe el título como texto directo dentro del <span> (negrita
        // vía "font-weight: bold" en el propio style, sin <strong>). Pero en cuanto ese documento se
        // abre UNA VEZ en el editor de texto libre (regulation-versions/edit.blade.php, TipTap), el
        // parser HTML de la extensión Bold reconoce ese "font-weight: bold" y le agrega su propia
        // marca — al serializar de vuelta (editor.getHTML()) el título queda envuelto en
        // "<strong>...</strong>" ADENTRO del span (el span y su estilo original se conservan aparte,
        // vía la extensión PreserveInlineStyle) — confirmado guardando una edición real de un
        // documento generado por el wizard. Sin tolerar esa etiqueta de por medio, splitSections()
        // deja de reconocer los 10 títulos desde la PRIMERA edición manual (así el usuario no haya
        // tocado esa sección), y el diff cae siempre al respaldo de "Documento completo" — que sigue
        // siendo correcto, pero pierde el detalle por sección que se le muestra a quien aprueba.
        $inline  = '(?:strong|b|em|i|u)';
        $pattern = '/<p[^>]*>\s*<span[^>]*>\s*(?:<' . $inline . '[^>]*>\s*)*('
            . implode('|', $titles) . ')\s*(?:<\/' . $inline . '>\s*)*<\/span>\s*<\/p>/u';

        if (! preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $sections = [];
        $count    = count($matches[0]);

        for ($i = 0; $i < $count; $i++) {
            $title      = $matches[1][$i][0];
            $markerEnd  = $matches[0][$i][1] + strlen($matches[0][$i][0]);
            $sectionEnd = $i + 1 < $count ? $matches[0][$i + 1][1] : strlen($html);

            $sections[$title] = substr($html, $markerEnd, $sectionEnd - $markerEnd);
        }

        return $sections;
    }

    /**
     * HTML → texto plano legible para una tabla de correo/tarjeta — conserva saltos de línea entre
     * párrafos/filas/celdas para que listas y tablas no queden pegadas en una sola línea. Las
     * imágenes (p. ej. el diagrama de flujo, embebido como &lt;img&gt; en base64) se pierden aquí a
     * propósito: no tiene caso comparar texto de una imagen, y evita inflar la tabla con basura.
     */
    public function toPlainText(string $html): string
    {
        return implode("\n", $this->plainTextLines($html));
    }

    /** @return array<int, string> */
    private function plainTextLines(string $html): array
    {
        $withBreaks = preg_replace('/<\/(p|li|tr|div|h[1-6])>/i', '</$1>' . "\n", $html);

        $text = html_entity_decode(strip_tags($withBreaks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return array_values(array_filter(array_map('trim', explode("\n", $text)), fn ($line) => $line !== ''));
    }
}
