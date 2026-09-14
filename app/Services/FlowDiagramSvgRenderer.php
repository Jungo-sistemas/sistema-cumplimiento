<?php

namespace App\Services;

/**
 * Arma el SVG del diagrama de flujo DESDE CERO a partir de la geometría ya calculada por
 * FlowDiagramLayoutEngine — reemplaza el rol de FlowDiagramSvgPainter (que editaba EN EL MISMO
 * LUGAR el SVG que generaba mermaid-cli). Reutiliza sin cambios la paleta de colores por tipo de
 * nodo/carril ya validada contra el documento de referencia; lo único que cambia es que ahora
 * dibuja sobre coordenadas propias en vez de las de un SVG ajeno, así que no hay ninguna
 * dependencia de namespace/XPath/especificidad CSS que adivinar.
 *
 * Orden de dibujo (de atrás hacia adelante): fondos de carril -> flechas -> nodos ->
 * insignias/notas -> encabezados de carril. Así cualquier tramo de una flecha larga que pase
 * cerca de una caja de otro carril queda DETRÁS de ella (se oculta), nunca ENCIMA de su texto; y
 * los encabezados, que viven en su propia franja reservada arriba de todo el contenido, se
 * dibujan al final solo por seguridad.
 */
class FlowDiagramSvgRenderer
{
    private const COLORS = [
        'inicio' => ['fill' => '#EAF4E9', 'stroke' => '#548235', 'text' => '#548235'],
        'fin' => ['fill' => '#FDECEA', 'stroke' => '#C0392B', 'text' => '#C0392B'],
        'actividad' => ['fill' => '#FFFFFF', 'stroke' => '#5B9BD5', 'text' => '#000000'],
        'decision' => ['fill' => '#FFF6D9', 'stroke' => '#C9A227', 'text' => '#000000'],
        'subproceso' => ['fill' => '#D4A017', 'stroke' => '#9C7A0E', 'text' => '#000000'],
    ];

    /** Colores de encabezado por carril, cíclicos si hay más carriles que colores. */
    private const LANE_HEADER_COLORS = ['#1F5C55', '#2E74B5', '#6B7280', '#1F3864', '#7D5BA6', '#8A5A2B'];

    private const LANE_BODY_FILL = '#F7F9FC';
    private const LANE_BODY_STROKE = '#CCCCCC';
    private const BADGE_FILL = '#1F3864';
    private const BADGE_RADIUS = 13.0;
    private const BADGE_INSET = 6.0;

    /** Fuente fijada explícitamente (igual que DiagramTitleBarComposer) para que el ajuste de línea del layout no adivine qué fuente usará realmente Chromium. */
    private const FONT_FAMILY = 'Arial, Helvetica, sans-serif';
    private const NODE_FONT_SIZE = 16.0;
    private const NODE_LINE_HEIGHT = 20.8;
    private const LANE_NAME_FONT_SIZE = 18.0;
    private const LANE_NAME_LINE_HEIGHT = 23.4;
    private const EDGE_STROKE = '#333333';
    private const LABEL_CHIP_FILL = '#EDEDED';
    private const LABEL_CHIP_STROKE = '#B0B0B0';

    /**
     * @param  array{canvas: array{width: float, height: float}, lanes: array<int, array<string, mixed>>, nodes: array<string, array<string, mixed>>, edges: array<int, array<string, mixed>>}  $layout
     */
    public function render(array $layout): string
    {
        $width = $this->n($layout['canvas']['width']);
        $height = $this->n($layout['canvas']['height']);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="' . $width . '" height="' . $height
            . '" viewBox="0 0 ' . $width . ' ' . $height . '" style="font-family:' . self::FONT_FAMILY . ';">'
            . '<rect x="0" y="0" width="' . $width . '" height="' . $height . '" style="fill:#FFFFFF;" />'
            . $this->defs();

        foreach ($layout['lanes'] as $lane) {
            $svg .= $this->renderLaneBackground($lane);
        }
        foreach ($layout['edges'] as $edge) {
            $svg .= $this->renderEdge($edge);
        }
        foreach ($layout['nodes'] as $id => $node) {
            $svg .= $this->renderNode($id, $node);
        }
        foreach ($layout['nodes'] as $id => $node) {
            $svg .= $this->renderDecorations($node);
        }
        foreach ($layout['lanes'] as $index => $lane) {
            $svg .= $this->renderLaneHeader($lane, $index);
        }

        return $svg . '</svg>';
    }

    private function defs(): string
    {
        // orient="auto-start-reverse": el navegador calcula solo la rotación exacta de la punta a
        // partir de la tangente real del último tramo del <path> — más robusto que rotar a mano un
        // triángulo aparte, y correcto tanto para tramos verticales como horizontales sin ningún caso especial.
        return '<defs><marker id="arrowhead" viewBox="0 0 10 10" refX="9" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">'
            . '<path d="M0,0 L10,5 L0,10 Z" style="fill:' . self::EDGE_STROKE . ';" /></marker></defs>';
    }

    private function renderLaneBackground(array $lane): string
    {
        return sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" style="fill:%s;stroke:%s;stroke-width:1px;" />',
            $this->n($lane['x']), $this->n($lane['y']), $this->n($lane['width']), $this->n($lane['height']),
            self::LANE_BODY_FILL, self::LANE_BODY_STROKE
        );
    }

    private function renderLaneHeader(array $lane, int $index): string
    {
        $color = self::LANE_HEADER_COLORS[$index % count(self::LANE_HEADER_COLORS)];

        $svg = sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" style="fill:%s;" />',
            $this->n($lane['x']), $this->n($lane['y']), $this->n($lane['width']), $this->n($lane['header_height']), $color
        );

        $cx = $lane['x'] + $lane['width'] / 2;
        $cy = $lane['y'] + $lane['header_height'] / 2;

        return $svg . $this->multilineText($cx, $cy, $lane['name_lines'], self::LANE_NAME_FONT_SIZE, self::LANE_NAME_LINE_HEIGHT, '#FFFFFF', true);
    }

    private function renderNode(string $id, array $node): string
    {
        $colors = self::COLORS[$node['tipo']] ?? self::COLORS['actividad'];
        $x = $node['x'];
        $y = $node['y'];
        $w = $node['width'];
        $h = $node['height'];
        $style = 'fill:' . $colors['fill'] . ';stroke:' . $colors['stroke'] . ';stroke-width:2px;';

        $shape = match ($node['shape']) {
            'pill' => sprintf(
                '<rect x="%s" y="%s" width="%s" height="%s" rx="%s" ry="%s" style="%s" />',
                $this->n($x), $this->n($y), $this->n($w), $this->n($h), $this->n($h / 2), $this->n($h / 2), $style
            ),
            'diamond' => sprintf(
                '<polygon points="%s,%s %s,%s %s,%s %s,%s" style="%s" />',
                $this->n($x + $w / 2), $this->n($y),
                $this->n($x + $w), $this->n($y + $h / 2),
                $this->n($x + $w / 2), $this->n($y + $h),
                $this->n($x), $this->n($y + $h / 2),
                $style
            ),
            'subprocess' => sprintf('<rect x="%s" y="%s" width="%s" height="%s" style="%s" />', $this->n($x), $this->n($y), $this->n($w), $this->n($h), $style)
                . sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" style="stroke:%s;stroke-width:2px;" />', $this->n($x + 10), $this->n($y), $this->n($x + 10), $this->n($y + $h), $colors['stroke'])
                . sprintf('<line x1="%s" y1="%s" x2="%s" y2="%s" style="stroke:%s;stroke-width:2px;" />', $this->n($x + $w - 10), $this->n($y), $this->n($x + $w - 10), $this->n($y + $h), $colors['stroke']),
            default => sprintf('<rect x="%s" y="%s" width="%s" height="%s" style="%s" />', $this->n($x), $this->n($y), $this->n($w), $this->n($h), $style),
        };

        $text = $this->multilineText($x + $w / 2, $y + $h / 2, $node['lines'], self::NODE_FONT_SIZE, self::NODE_LINE_HEIGHT, $colors['text']);

        return '<g data-node-id="' . $this->esc($id) . '">' . $shape . $text . '</g>';
    }

    /**
     * Insignia numerada superpuesta en la esquina de cada paso, y la nota en cursiva sobre los
     * pasos de subproceso — ya sobre coordenadas absolutas (el layout ya las calculó), sin
     * necesidad de heredar ningún transform local como antes.
     */
    private function renderDecorations(array $node): string
    {
        if ($node['tipo'] !== 'actividad' && $node['tipo'] !== 'subproceso') {
            return '';
        }

        $svg = '';
        $x = $node['x'];
        $y = $node['y'];
        $w = $node['width'];

        if ($node['paso_numero'] !== null) {
            $badgeX = $x + self::BADGE_INSET;
            $badgeY = $y + self::BADGE_INSET;
            $svg .= sprintf(
                '<circle cx="%s" cy="%s" r="%s" style="fill:%s;stroke:#FFFFFF;stroke-width:1.5px;" />',
                $this->n($badgeX), $this->n($badgeY), $this->n(self::BADGE_RADIUS), self::BADGE_FILL
            );
            $svg .= sprintf(
                '<text x="%s" y="%s" text-anchor="middle" style="font-size:18px;font-weight:bold;fill:#FFFFFF;">%s</text>',
                $this->n($badgeX), $this->n($badgeY + 5), (int) $node['paso_numero']
            );
        }

        if (! empty($node['nota'])) {
            $svg .= sprintf(
                '<text x="%s" y="%s" text-anchor="middle" style="font-size:14px;font-style:italic;fill:#595959;">%s</text>',
                $this->n($x + $w / 2), $this->n($y - 10), $this->esc('* ' . $node['nota'])
            );
        }

        return $svg;
    }

    private function renderEdge(array $edge): string
    {
        $points = $edge['points'];
        $d = 'M ' . $this->n($points[0]['x']) . ' ' . $this->n($points[0]['y']);
        for ($i = 1; $i < count($points); $i++) {
            $d .= ' L ' . $this->n($points[$i]['x']) . ' ' . $this->n($points[$i]['y']);
        }

        $svg = sprintf(
            '<path d="%s" fill="none" style="stroke:%s;stroke-width:2px;" marker-end="url(#arrowhead)" />',
            $d, self::EDGE_STROKE
        );

        if (! empty($edge['label'])) {
            $svg .= $this->renderLabelChip($edge['label_point'], $edge['label']);
        }

        return $svg;
    }

    private function renderLabelChip(array $point, string $label): string
    {
        $fontSize = 13.0;
        $paddingX = 6.0;
        $paddingY = 4.0;
        $textWidth = mb_strlen($label) * $fontSize * 0.55;
        $chipWidth = $textWidth + 2 * $paddingX;
        $chipHeight = $fontSize + 2 * $paddingY;

        $svg = sprintf(
            '<rect x="%s" y="%s" width="%s" height="%s" rx="4" ry="4" style="fill:%s;stroke:%s;stroke-width:1px;" />',
            $this->n($point['x'] - $chipWidth / 2), $this->n($point['y'] - $chipHeight / 2), $this->n($chipWidth), $this->n($chipHeight),
            self::LABEL_CHIP_FILL, self::LABEL_CHIP_STROKE
        );

        return $svg . sprintf(
            '<text x="%s" y="%s" text-anchor="middle" style="font-size:%spx;fill:#000000;">%s</text>',
            $this->n($point['x']), $this->n($point['y'] + $fontSize * 0.35), $this->n($fontSize), $this->esc($label)
        );
    }

    /** @param  array<int, string>  $lines */
    private function multilineText(float $cx, float $cy, array $lines, float $fontSize, float $lineHeight, string $color, bool $bold = false): string
    {
        $count = count($lines);
        $startY = $cy - ($count - 1) * $lineHeight / 2 + $fontSize * 0.35;
        $weight = $bold ? 'font-weight:bold;' : '';

        $svg = '<text text-anchor="middle" style="font-size:' . $this->n($fontSize) . 'px;fill:' . $color . ';' . $weight . '">';
        foreach ($lines as $i => $line) {
            $y = $startY + $i * $lineHeight;
            $svg .= '<tspan x="' . $this->n($cx) . '" y="' . $this->n($y) . '">' . $this->esc($line) . '</tspan>';
        }

        return $svg . '</text>';
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function n(float $value): string
    {
        $formatted = rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');

        return $formatted === '' ? '0' : $formatted;
    }
}
