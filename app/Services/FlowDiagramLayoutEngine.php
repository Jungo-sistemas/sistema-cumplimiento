<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Calcula la geometría COMPLETA del diagrama de flujo (posición y tamaño de cada carril, cada
 * nodo, y la ruta de cada flecha) — reemplaza a FlowDiagramMermaidBuilder. La resolución de
 * TOPOLOGÍA (a qué carril pertenece cada paso, la cadena secuencial con los cortes por decisión,
 * el encadenado de decisión a decisión) es la misma lógica ya probada que tenía
 * FlowDiagramMermaidBuilder — lo único que cambia es que en vez de emitir texto Mermaid para que
 * `dagre` decida el acomodo, aquí se calculan las coordenadas directamente, en una cuadrícula
 * pareja de verdad: todos los carriles con el mismo ancho, la misma altura, el mismo punto de
 * arranque arriba y la misma separación entre ellos — algo que no se pudo lograr nunca ajustando
 * el SVG que generaba Mermaid (medido con Chromium real: sus anchos/huecos/alturas de carril
 * salían estructuralmente distintos según el contenido de cada uno).
 *
 * El ancho de carril sale parejo por construcción: cada tipo de nodo tiene un ancho FIJO (nunca
 * medido del texto), así que el carril simplemente se dimensiona al nodo más ancho posible
 * (la decisión) — no hace falta medir ni corregir nada después. El texto de cada nodo se AJUSTA
 * (word-wrap real, palabra por palabra) contra ese ancho fijo para calcular cuántas líneas
 * necesita y, con eso, el ALTO del nodo.
 *
 * FlowDiagramSvgRenderer consume la salida de build() para dibujar el SVG desde cero.
 */
class FlowDiagramLayoutEngine
{
    private const ACTIVITY_WIDTH = 220.0;
    private const DECISION_WIDTH = 260.0;
    /** Un rombo solo tiene texto legible cerca del centro — el resto del ancho nominal se va en las puntas. */
    private const DECISION_TEXT_WIDTH_RATIO = 0.57;
    private const DECISION_MIN_HEIGHT = 140.0;
    private const PILL_WIDTH = 200.0;
    private const PILL_MIN_HEIGHT = 64.0;

    private const NODE_FONT_SIZE = 16.0;
    private const LANE_NAME_FONT_SIZE = 18.0;
    private const LINE_HEIGHT_RATIO = 1.3;
    private const NODE_PADDING_Y = 14.0;
    private const NODE_PADDING_X = 16.0;
    private const LANE_HEADER_PADDING_Y = 10.0;
    private const LANE_HEADER_MIN_HEIGHT = 40.0;

    private const LANE_PADDING_X = 24.0;
    private const LANE_GAP = 60.0;
    private const NODE_GAP_Y = 50.0;
    private const CANVAS_PADDING = 30.0;
    /** Franja horizontal reservada justo debajo del encabezado, nunca ocupada por contenido — ahí se rutean las flechas que saltan más de un carril o van hacia atrás. */
    private const ROUTING_GUTTER_HEIGHT = 50.0;
    private const LANE_BOTTOM_PADDING = 30.0;

    /** Desfase entre varias flechas que convergen en el mismo nodo, para que no se dibujen exactamente encimadas. */
    private const EDGE_STAGGER_STEP = 14.0;

    /**
     * Carril lateral propio para una arista que brinca uno o más nodos dentro del MISMO carril
     * (ej. la rama "No" de una decisión que regresa varios pasos atrás) — sin esto, comparte el
     * pasillo vertical central con la cadena normal del carril y solo el desfase de convergencia
     * (unos px) las separa, viéndose como "rieles de tren" pegados en vez de una ruta aparte.
     */
    private const SAME_LANE_LOOP_OFFSET = 40.0;

    /** Anchos de glifo aproximados (relativos al tamaño de fuente) usados por el estimador de ajuste de línea. */
    private const GLYPH_NARROW = "iIl1.,:;|!'\" jfrt";
    private const GLYPH_WIDE = 'MWmw@%&';

    /**
     * @param  array{carriles: array<int, array{id: string, nombre: string}>, decisiones: array<int, array{id: string, carril_id: string, texto: string, tras_paso: int, destino_si: string, etiqueta_si: string, destino_no: string, etiqueta_no: string}>, pasos_especiales: array<int, array{paso_numero: int, nota: string}>}  $diagrama
     * @param  array<int, array{titulo: string, responsable: string}>  $pasos  documento.pasos, mismo orden que el documento.
     * @return array{canvas: array{width: float, height: float}, lanes: array<int, array<string, mixed>>, nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>}
     */
    public function build(array $diagrama, array $pasos): array
    {
        $carriles = $diagrama['carriles'] !== [] ? $diagrama['carriles'] : [['id' => 'proceso', 'nombre' => 'Proceso']];
        $carrilIds = array_column($carriles, 'id');

        $notasPorPaso = [];
        foreach ($diagrama['pasos_especiales'] as $especial) {
            $notasPorPaso[$especial['paso_numero']] = $especial['nota'];
        }

        // --- Nodos (misma resolución de topología que FlowDiagramMermaidBuilder) ------------
        $nodeMeta = [];
        $nodesByCarril = array_fill_keys($carrilIds, []);

        foreach ($pasos as $index => $paso) {
            $numero = $index + 1;
            $id = "paso{$numero}";
            $carrilId = $this->resolveCarrilId($paso['responsable'] ?? '', $carriles);
            $esEspecial = isset($notasPorPaso[$numero]);

            $nodeMeta[$id] = [
                'tipo' => $esEspecial ? 'subproceso' : 'actividad',
                'carril_id' => $carrilId,
                'paso_numero' => $numero,
                'nota' => $notasPorPaso[$numero] ?? null,
                'label' => trim($numero . '. ' . ($paso['titulo'] ?? '')),
            ];
            $nodesByCarril[$carrilId][] = $id;
        }

        $primerCarril = $pasos !== [] ? $this->resolveCarrilId($pasos[0]['responsable'] ?? '', $carriles) : $carrilIds[0];
        $ultimoCarril = $pasos !== [] ? $this->resolveCarrilId($pasos[count($pasos) - 1]['responsable'] ?? '', $carriles) : $carrilIds[0];

        $nodeMeta['inicio'] = ['tipo' => 'inicio', 'carril_id' => $primerCarril, 'paso_numero' => null, 'nota' => null, 'label' => 'INICIO DEL PROCESO'];
        $nodeMeta['fin'] = ['tipo' => 'fin', 'carril_id' => $ultimoCarril, 'paso_numero' => null, 'nota' => null, 'label' => 'FIN DEL PROCESO'];
        array_unshift($nodesByCarril[$primerCarril], 'inicio');
        $nodesByCarril[$ultimoCarril][] = 'fin';

        $decisionIdMap = [];
        foreach ($diagrama['decisiones'] as $decision) {
            $sane = $this->sanitizeId($decision['id']);
            $decisionIdMap[$decision['id']] = $sane;

            $carrilId = in_array($decision['carril_id'], $carrilIds, true) ? $decision['carril_id'] : $primerCarril;
            $nodeMeta[$sane] = [
                'tipo' => 'decision',
                'carril_id' => $carrilId,
                'paso_numero' => null,
                'nota' => null,
                'label' => $decision['texto'],
            ];
            $nodesByCarril[$carrilId][] = $sane;
        }

        // --- Aristas: cadena por defecto inicio->paso1->...->pasoN->fin, cortada y reemplazada
        // por sus dos ramas en cada punto de decisión — igual que FlowDiagramMermaidBuilder.
        $sequence = ['inicio'];
        foreach ($pasos as $index => $paso) {
            $sequence[] = 'paso' . ($index + 1);
        }
        $sequence[] = 'fin';

        $defaultNext = [];
        for ($i = 0; $i < count($sequence) - 1; $i++) {
            $defaultNext[$sequence[$i]] = $sequence[$i + 1];
        }

        $overrides = [];
        $branches = [];
        foreach ($diagrama['decisiones'] as $decision) {
            $sourceId = 'paso' . $decision['tras_paso'];
            $decisionId = $decisionIdMap[$decision['id']];
            $overrides[$sourceId] = $decisionId;

            $branches[$decisionId] = [
                [$this->resolveDestino($decision['destino_si'], $decisionIdMap), $decision['etiqueta_si']],
                [$this->resolveDestino($decision['destino_no'], $decisionIdMap), $decision['etiqueta_no']],
            ];
        }

        $edgeDefs = [];
        foreach ($sequence as $id) {
            if ($id === 'fin') {
                continue;
            }
            $to = $overrides[$id] ?? $defaultNext[$id];
            $edgeDefs[] = [$id, $to, null];
        }
        foreach ($branches as $decisionId => $ramas) {
            foreach ($ramas as [$to, $label]) {
                $edgeDefs[] = [$decisionId, $to, $label];
            }
        }

        return $this->layout($carriles, $nodeMeta, $nodesByCarril, $edgeDefs);
    }

    /**
     * @param  array<int, array{id: string, nombre: string}>  $carriles
     * @param  array<string, array{tipo: string, carril_id: string, paso_numero: ?int, nota: ?string, label: string}>  $nodeMeta
     * @param  array<string, array<int, string>>  $nodesByCarril
     * @param  array<int, array{0: string, 1: string, 2: ?string}>  $edgeDefs
     */
    private function layout(array $carriles, array $nodeMeta, array $nodesByCarril, array $edgeDefs): array
    {
        $carrilIds = array_column($carriles, 'id');

        // El ancho de carril nunca depende del contenido: todos los tipos de nodo tienen un
        // ancho FIJO, así que basta con dimensionar al más ancho de todos (la decisión) — la
        // uniformidad de ancho queda garantizada por construcción, no medida después.
        $laneWidth = self::DECISION_WIDTH + 2 * self::LANE_PADDING_X;
        $laneNameLineHeight = self::LANE_NAME_FONT_SIZE * self::LINE_HEIGHT_RATIO;

        $headerHeight = self::LANE_HEADER_MIN_HEIGHT;
        $laneNameLines = [];
        foreach ($carriles as $carril) {
            $lines = $this->wrapLines($carril['nombre'], $laneWidth - 2 * self::NODE_PADDING_X, self::LANE_NAME_FONT_SIZE);
            $laneNameLines[$carril['id']] = $lines;
            $h = count($lines) * $laneNameLineHeight + 2 * self::LANE_HEADER_PADDING_Y + $laneNameLineHeight * 0.5;
            $headerHeight = max($headerHeight, $h);
        }

        $nodeSizes = [];
        foreach ($nodeMeta as $id => $meta) {
            $nodeSizes[$id] = $this->nodeSize($meta['tipo'], $meta['label']);
        }

        $laneContentHeight = [];
        foreach ($carrilIds as $carrilId) {
            $ids = $nodesByCarril[$carrilId];
            $total = 0.0;
            foreach ($ids as $id) {
                $total += $nodeSizes[$id]['height'];
            }
            $total += self::NODE_GAP_Y * max(0, count($ids) - 1);
            $laneContentHeight[$carrilId] = $total;
        }
        $maxContentHeight = $laneContentHeight === [] ? 0.0 : max($laneContentHeight);

        $laneTop = self::CANVAS_PADDING;
        // Los carriles más cortos no se recortan: se estiran hasta la altura del más alto,
        // dejando el aire de sobra al final — mismo principio ya validado para la altura pareja,
        // ahora aplicado desde el cálculo mismo en vez de sobre un SVG ajeno.
        $laneHeight = $headerHeight + self::ROUTING_GUTTER_HEIGHT + $maxContentHeight + self::LANE_BOTTOM_PADDING;

        $lanes = [];
        $laneX = [];
        foreach ($carriles as $i => $carril) {
            $x = self::CANVAS_PADDING + $i * ($laneWidth + self::LANE_GAP);
            $laneX[$carril['id']] = $x;
            $lanes[] = [
                'id' => $carril['id'],
                'nombre' => $carril['nombre'],
                'name_lines' => $laneNameLines[$carril['id']],
                'x' => $x,
                'y' => $laneTop,
                'width' => $laneWidth,
                'height' => $laneHeight,
                'header_height' => $headerHeight,
            ];
        }
        $laneIndex = array_flip($carrilIds);

        $stackIndex = [];
        foreach ($carrilIds as $carrilId) {
            foreach (array_values($nodesByCarril[$carrilId]) as $position => $id) {
                $stackIndex[$id] = $position;
            }
        }

        $nodes = [];
        foreach ($carrilIds as $carrilId) {
            $y = $laneTop + $headerHeight + self::ROUTING_GUTTER_HEIGHT;

            foreach ($nodesByCarril[$carrilId] as $id) {
                $size = $nodeSizes[$id];
                $x = $laneX[$carrilId] + ($laneWidth - $size['width']) / 2;

                $nodes[$id] = array_merge($nodeMeta[$id], [
                    'shape' => $this->shapeFor($nodeMeta[$id]['tipo']),
                    'x' => $x,
                    'y' => $y,
                    'width' => $size['width'],
                    'height' => $size['height'],
                    'lines' => $size['lines'],
                    'lane_index' => $laneIndex[$carrilId],
                ]);

                $y += $size['height'] + self::NODE_GAP_Y;
            }
        }

        $gutterY = $laneTop + $headerHeight + self::ROUTING_GUTTER_HEIGHT / 2;

        $edges = [];
        $convergingCount = [];
        foreach ($edgeDefs as [$from, $to, $label]) {
            if (! isset($nodes[$from], $nodes[$to])) {
                continue;
            }

            $convergingCount[$to] = ($convergingCount[$to] ?? -1) + 1;
            $stagger = $convergingCount[$to] * self::EDGE_STAGGER_STEP;

            // Una arista que "brinca" uno o más nodos DENTRO del mismo carril (ej. la rama "No"
            // de una decisión que regresa varios pasos atrás) comparte, si no se distingue, el
            // mismo pasillo vertical que la cadena normal del carril — el desfase de convergencia
            // por sí solo (unos cuantos px) no basta para leerse como una ruta aparte, se ve como
            // "rieles de tren" pegados. Se le da un carril lateral propio, como el lazo de retorno
            // de un diagrama de flujo convencional.
            $sameLaneSkip = $nodes[$from]['lane_index'] === $nodes[$to]['lane_index']
                && abs($stackIndex[$to] - $stackIndex[$from]) > 1;

            $edges[] = $this->routeEdge($from, $nodes[$from], $to, $nodes[$to], $label, $gutterY, $stagger, $sameLaneSkip);
        }

        $canvasWidth = self::CANVAS_PADDING * 2 + count($carriles) * $laneWidth + max(0, count($carriles) - 1) * self::LANE_GAP;
        $canvasHeight = self::CANVAS_PADDING * 2 + $laneHeight;

        return [
            'canvas' => ['width' => $canvasWidth, 'height' => $canvasHeight],
            'lanes' => $lanes,
            'nodes' => $nodes,
            'edges' => $edges,
        ];
    }

    /** @return array{width: float, height: float, lines: array<int, string>} */
    private function nodeSize(string $tipo, string $label): array
    {
        return match ($tipo) {
            'inicio', 'fin' => $this->boxSize($label, self::PILL_WIDTH, self::PILL_WIDTH - 2 * self::NODE_PADDING_X, self::PILL_MIN_HEIGHT),
            'decision' => $this->diamondSize($label),
            // Las dos líneas verticales internas del subproceso restan ancho útil de texto.
            'subproceso' => $this->boxSize($label, self::ACTIVITY_WIDTH, self::ACTIVITY_WIDTH - 2 * self::NODE_PADDING_X - 20.0, 0.0),
            default => $this->boxSize($label, self::ACTIVITY_WIDTH, self::ACTIVITY_WIDTH - 2 * self::NODE_PADDING_X, 0.0),
        };
    }

    /** @return array{width: float, height: float, lines: array<int, string>} */
    private function boxSize(string $label, float $width, float $textWidth, float $minHeight): array
    {
        $lines = $this->wrapLines($label, $textWidth, self::NODE_FONT_SIZE);
        $lineHeight = self::NODE_FONT_SIZE * self::LINE_HEIGHT_RATIO;
        // Margen de seguridad de media línea extra: un texto recortado se ve mucho peor que una caja con un poco de aire de más.
        $height = max($minHeight, count($lines) * $lineHeight + 2 * self::NODE_PADDING_Y + $lineHeight * 0.5);

        return ['width' => $width, 'height' => $height, 'lines' => $lines];
    }

    /** @return array{width: float, height: float, lines: array<int, string>} */
    private function diamondSize(string $label): array
    {
        $effectiveWidth = self::DECISION_WIDTH * self::DECISION_TEXT_WIDTH_RATIO - 2 * self::NODE_PADDING_X;
        $lines = $this->wrapLines($label, $effectiveWidth, self::NODE_FONT_SIZE);
        $lineHeight = self::NODE_FONT_SIZE * self::LINE_HEIGHT_RATIO;
        $textBlockHeight = count($lines) * $lineHeight + $lineHeight * 0.5;
        // Un rombo necesita bastante más alto que un rectángulo para el mismo bloque de texto,
        // porque su área legible se angosta hacia arriba y abajo, no solo hacia los lados.
        $height = max(self::DECISION_MIN_HEIGHT, $textBlockHeight * 2.0 + 2 * self::NODE_PADDING_Y);

        return ['width' => self::DECISION_WIDTH, 'height' => $height, 'lines' => $lines];
    }

    private function shapeFor(string $tipo): string
    {
        return match ($tipo) {
            'inicio', 'fin' => 'pill',
            'decision' => 'diamond',
            'subproceso' => 'subprocess',
            default => 'rect',
        };
    }

    /**
     * Calcula la ruta (polilínea) de una arista. Adyacente/mismo carril: conector directo de
     * a lo más 2 quiebres. Cualquier arista que salte más de un carril o vaya hacia atrás
     * (rama "No" que regresa a un carril anterior) se rutea por la franja horizontal reservada
     * bajo el encabezado — nunca comparte esa franja con contenido real, así que cruzar varios
     * carriles ahí no puede atravesar el texto de una caja ajena. Qué lado de cada nodo usa cada
     * arista se decide por la posición relativa real del destino (nunca por el orden en que la
     * IA escribió las ramas), así que dos diagramas con la misma lógica siempre enrutan igual.
     */
    private function routeEdge(string $fromId, array $from, string $toId, array $to, ?string $label, float $gutterY, float $stagger, bool $sameLaneSkip = false): array
    {
        $laneDiff = $to['lane_index'] - $from['lane_index'];
        $isGutter = $laneDiff < 0 || $laneDiff > 1;

        $fromCenterX = $from['x'] + $from['width'] / 2;
        $fromCenterY = $from['y'] + $from['height'] / 2;
        $toCenterX = $to['x'] + $to['width'] / 2;
        $toCenterY = $to['y'] + $to['height'] / 2;

        if ($isGutter) {
            $exit = ['x' => $fromCenterX + $stagger, 'y' => $from['y']];
            $enter = ['x' => $toCenterX - $stagger, 'y' => $to['y']];
            $railY = $gutterY - $stagger;
            $points = [$exit, ['x' => $exit['x'], 'y' => $railY], ['x' => $enter['x'], 'y' => $railY], $enter];
            $routing = 'gutter';
        } elseif ($laneDiff === 0) {
            $lateral = $sameLaneSkip ? self::SAME_LANE_LOOP_OFFSET : 0.0;
            $goingDown = $toCenterY >= $fromCenterY;
            $exit = ['x' => $fromCenterX + $lateral + $stagger, 'y' => $goingDown ? $from['y'] + $from['height'] : $from['y']];
            $enter = ['x' => $toCenterX + $lateral + $stagger, 'y' => $goingDown ? $to['y'] : $to['y'] + $to['height']];
            $points = [$exit, $enter];
            $routing = 'simple';
        } else {
            $exit = ['x' => $from['x'] + $from['width'], 'y' => $fromCenterY];
            $enter = ['x' => $to['x'], 'y' => $toCenterY + $stagger];
            $midX = ($exit['x'] + $enter['x']) / 2;
            $points = [$exit, ['x' => $midX, 'y' => $exit['y']], ['x' => $midX, 'y' => $enter['y']], $enter];
            $routing = 'simple';
        }

        return [
            'from' => $fromId,
            'to' => $toId,
            'label' => $label,
            'routing' => $routing,
            'points' => $points,
            'label_point' => $this->labelPoint($points),
            'arrow_angle' => $this->arrowAngle($points),
        ];
    }

    /** @param  array<int, array{x: float, y: float}>  $points */
    private function labelPoint(array $points): array
    {
        $bestLen = -1.0;
        $best = null;

        for ($i = 0; $i < count($points) - 1; $i++) {
            $p1 = $points[$i];
            $p2 = $points[$i + 1];
            $len = sqrt(($p2['x'] - $p1['x']) ** 2 + ($p2['y'] - $p1['y']) ** 2);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = ['x' => ($p1['x'] + $p2['x']) / 2, 'y' => ($p1['y'] + $p2['y']) / 2];
            }
        }

        // Tramo más largo demasiado corto (lazos apretados): mejor caer al centro de todo el
        // recorrido que dejar la etiqueta sin espacio alrededor.
        if ($bestLen < 40.0) {
            $xs = array_column($points, 'x');
            $ys = array_column($points, 'y');
            $best = ['x' => (min($xs) + max($xs)) / 2, 'y' => (min($ys) + max($ys)) / 2];
        }

        return $best ?? ['x' => 0.0, 'y' => 0.0];
    }

    /** @param  array<int, array{x: float, y: float}>  $points */
    private function arrowAngle(array $points): float
    {
        $n = count($points);
        $p1 = $points[$n - 2];
        $p2 = $points[$n - 1];

        return atan2($p2['y'] - $p1['y'], $p2['x'] - $p1['x']) * 180 / M_PI;
    }

    /** @return array<int, string> */
    private function wrapLines(string $text, float $maxWidth, float $fontSize): array
    {
        $words = preg_split('/\s+/u', trim($text)) ?: [];
        if ($words === [] || $words === ['']) {
            return [''];
        }

        $lines = [];
        $current = '';

        foreach ($words as $word) {
            $candidate = $current === '' ? $word : $current . ' ' . $word;
            if ($current === '' || $this->textWidth($candidate, $fontSize) <= $maxWidth) {
                $current = $candidate;
            } else {
                $lines[] = $current;
                $current = $word;
            }
        }
        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function textWidth(string $text, float $fontSize): float
    {
        $width = 0.0;
        foreach (mb_str_split($text) as $ch) {
            $width += $this->charWidth($ch, $fontSize);
        }

        return $width;
    }

    private function charWidth(string $ch, float $fontSize): float
    {
        if (str_contains(self::GLYPH_NARROW, $ch)) {
            return $fontSize * 0.28;
        }
        if (str_contains(self::GLYPH_WIDE, $ch)) {
            return $fontSize * 0.85;
        }
        if (ctype_upper($ch)) {
            return $fontSize * 0.68;
        }

        return $fontSize * 0.52;
    }

    /**
     * Empareja "responsable" (texto libre, tal como lo redactó la IA en documento.pasos) contra
     * el "nombre" de cada carril — exacto primero, luego por contención en cualquier dirección
     * (ej. "Gerente" adentro de "Gerente Unidad de Negocio"). Si nada coincide, cae al primer
     * carril declarado en vez de fallar: un carril "equivocado" es un problema menor de
     * presentación, nunca debe bloquear la generación del documento completo.
     */
    private function resolveCarrilId(string $responsable, array $carriles): string
    {
        $normalize = fn (string $s) => mb_strtolower(trim(preg_replace('/\s+/', ' ', $s) ?? ''));
        $target = $normalize($responsable);

        foreach ($carriles as $carril) {
            if ($normalize($carril['nombre']) === $target) {
                return $carril['id'];
            }
        }

        foreach ($carriles as $carril) {
            $nombre = $normalize($carril['nombre']);
            if ($nombre !== '' && $target !== '' && (str_contains($target, $nombre) || str_contains($nombre, $target))) {
                return $carril['id'];
            }
        }

        return $carriles[0]['id'];
    }

    private function resolveDestino(string $destino, array $decisionIdMap): string
    {
        $destino = trim($destino);

        if (strcasecmp($destino, 'fin') === 0) {
            return 'fin';
        }
        if (preg_match('/^paso:\s*(\d+)$/i', $destino, $m)) {
            return 'paso' . $m[1];
        }

        return $decisionIdMap[$destino] ?? $this->sanitizeId($destino);
    }

    /** IDs internos: sin acentos, solo [A-Za-z0-9_], y que no empiecen con un dígito (se usan como id de <g> en el SVG). */
    private function sanitizeId(string $id): string
    {
        $ascii = Str::ascii($id);
        $clean = preg_replace('/[^A-Za-z0-9_]/', '_', $ascii) ?? '';
        $clean = trim($clean, '_');

        if ($clean === '' || ctype_digit($clean[0])) {
            $clean = 'n_' . $clean;
        }

        return $clean;
    }
}
