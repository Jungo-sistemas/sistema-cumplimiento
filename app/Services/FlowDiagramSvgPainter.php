<?php

namespace App\Services;

/**
 * Edita EN EL MISMO LUGAR el SVG que ya generó mermaid-cli — nunca reconstruye un documento
 * paralelo — para que el diagrama se vea exactamente como el de referencia
 * (resources/ai-reference/diagrama_ejemplo.png): un color de encabezado distinto por carril,
 * una insignia numerada superpuesta en la esquina de cada paso, óvalos de inicio/fin pálidos con
 * borde de color, y una caja sólida distinta para los pasos marcados como "subproceso paralelo".
 *
 * Mermaid sigue resolviendo lo difícil de verdad (acomodo automático del grafo, rutas de las
 * flechas) — aquí nunca se recalcula ninguna posición: los ids de nodo/carril ya se generaron en
 * FlowDiagramMermaidBuilder, así que siempre se sabe de antemano qué tipo/carril/nota le
 * corresponde a cada id, sin tener que adivinar nada a partir de la forma del nodo en el SVG
 * (a diferencia del MermaidDiagramStyler anterior, que sí tenía que inferirlo con regex porque la
 * IA escribía el Mermaid crudo directamente).
 */
class FlowDiagramSvgPainter
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
    private const BADGE_RADIUS = 13;
    /** Cuánto se recorre el centro de la insignia hacia adentro desde la esquina exacta de la caja. */
    private const BADGE_INSET = 6;

    /**
     * @param  string  $svg  El SVG crudo tal como lo devolvió mermaid-cli.
     * @param  array<string, array{tipo: string, carril_id: ?string, paso_numero: ?int, nota: ?string}>  $nodeMeta
     */
    public function paint(string $svg, array $nodeMeta): string
    {
        $dom = new \DOMDocument();
        // LIBXML_NOERROR/NOWARNING: el SVG de mermaid-cli es HTML5-ish dentro de <foreignObject>
        // (usa <p>/<span> sin namespace explícito) — sin esto, DOMDocument llena el log de avisos
        // por markup que en realidad procesa bien.
        $dom->loadXML($svg, LIBXML_NOERROR | LIBXML_NOWARNING);

        $svgEl = $dom->documentElement;
        if ($svgEl === null) {
            return $svg;
        }

        $rootId = $svgEl->getAttribute('id');
        $xpath = new \DOMXPath($dom);

        $carrilOrder = [];
        foreach ($nodeMeta as $meta) {
            if ($meta['carril_id'] !== null && ! in_array($meta['carril_id'], $carrilOrder, true)) {
                $carrilOrder[] = $meta['carril_id'];
            }
        }

        $this->injectStyleRules($dom, $svgEl, $rootId, $nodeMeta);
        $this->paintNodes($dom, $xpath, $rootId, $nodeMeta);
        $this->paintLanes($dom, $xpath, $rootId, $carrilOrder);

        return $dom->saveXML($svgEl) ?: $svg;
    }

    /**
     * Un solo <style> (mismo mecanismo que ya usa Mermaid para sus propios estilos) con una regla
     * por id de nodo — evita tener que tocar el atributo style="" de cada forma una por una, y dos
     * generaciones nunca pueden desalinearse porque el color depende del id, no de buscar/reemplazar texto.
     */
    private function injectStyleRules(\DOMDocument $dom, \DOMElement $svgEl, string $rootId, array $nodeMeta): void
    {
        $rules = [];

        foreach ($nodeMeta as $id => $meta) {
            $colors = self::COLORS[$meta['tipo']] ?? self::COLORS['actividad'];
            $selector = "{$rootId}-flowchart-{$id}-";
            // El selector real de cada nodo trae un contador numérico al final que no conocemos de
            // antemano ([id^="..."] = "empieza con") — más robusto que intentar adivinar el índice.
            // "path" a secas, no "path.basic": el óvalo de inicio/fin es un <path> dentro de un
            // <g class="basic label-container outer-path"> — la clase "basic" vive en el <g> que
            // envuelve, no en el <path> mismo, así que exigirla en el selector nunca calzaba.
            $rules[] = "[id^=\"{$selector}\"] rect, [id^=\"{$selector}\"] polygon, [id^=\"{$selector}\"] path "
                . "{ fill: {$colors['fill']} !important; stroke: {$colors['stroke']} !important; stroke-width: 2px !important; }";
            $rules[] = "[id^=\"{$selector}\"] .nodeLabel, [id^=\"{$selector}\"] .nodeLabel p "
                . "{ color: {$colors['text']} !important; }";
        }

        $style = $dom->createElement('style', implode("\n", $rules));
        $svgEl->insertBefore($style, $svgEl->firstChild);
    }

    /**
     * Insignia numerada superpuesta en la esquina de cada paso, y la nota en cursiva sobre los
     * pasos de subproceso — ninguna de las dos se puede lograr solo con CSS, hay que insertar
     * elementos nuevos en las coordenadas LOCALES que el propio nodo ya trae (el <g> del nodo ya
     * viene con transform="translate(x,y)" puesto por Mermaid; todo lo que se agregue como hijo
     * de ese mismo <g> hereda esa posición automáticamente, sin calcular nada en absoluto).
     */
    private function paintNodes(\DOMDocument $dom, \DOMXPath $xpath, string $rootId, array $nodeMeta): void
    {
        foreach ($nodeMeta as $id => $meta) {
            if ($meta['tipo'] !== 'actividad' && $meta['tipo'] !== 'subproceso') {
                continue;
            }

            $nodeGroup = $this->findNodeGroup($xpath, $rootId, $id);
            if ($nodeGroup === null) {
                continue;
            }

            // local-name() en vez de self::rect: el SVG declara xmlns por defecto
            // (http://www.w3.org/2000/svg), y una prueba de nombre sin prefijo en XPath 1.0 solo
            // calza contra elementos SIN espacio de nombres — con self::rect esto nunca encuentra
            // nada (confirmado: la búsqueda con comodín "*" sí funciona, la que nombra la
            // etiqueta directamente no), sin lanzar ningún error que lo delate.
            $shape = $xpath->query('.//*[local-name()="rect" or local-name()="polygon"]', $nodeGroup)->item(0);
            if (! $shape instanceof \DOMElement) {
                continue;
            }

            // El paso especial (subproceso, "[[...]]") lo dibuja Mermaid como <polygon>, no
            // <rect> — confirmado con un SVG real: no tiene atributos x/y/width, así que la
            // esquina superior-izquierda se calcula del mínimo de sus propios puntos.
            if ($shape->localName === 'polygon') {
                ['x' => $x, 'y' => $y, 'width' => $width] = $this->polygonBounds($shape);
            } else {
                $x = (float) $shape->getAttribute('x');
                $y = (float) $shape->getAttribute('y');
                $width = (float) $shape->getAttribute('width');
            }

            if ($meta['paso_numero'] !== null) {
                // La insignia se recorre un poco hacia adentro de la esquina (en vez de quedar
                // centrada justo en el vértice) — cuando el carril es muy compacto y la caja
                // empieza casi pegada al encabezado de color, una insignia centrada EXACTO en la
                // esquina se monta sobre el texto del encabezado; recorrida hacia adentro sigue
                // leyéndose como "superpuesta en la esquina" sin invadir lo que hay arriba.
                $badgeX = $x + self::BADGE_INSET;
                $badgeY = $y + self::BADGE_INSET;

                // style="" (no los atributos sueltos fill/stroke): Mermaid ya trae sus propias
                // reglas de hoja de estilo tipo ".node circle{...}" que le ganan a un atributo de
                // presentación suelto sin importar in specificidad — confirmado que fill="..." se
                // ignoraba en silencio hasta cambiarlo por style="fill:...".
                $badge = $dom->createElement('circle');
                $badge->setAttribute('cx', (string) $badgeX);
                $badge->setAttribute('cy', (string) $badgeY);
                $badge->setAttribute('r', (string) self::BADGE_RADIUS);
                $badge->setAttribute('style', 'fill:' . self::BADGE_FILL . ';stroke:#FFFFFF;stroke-width:1.5px;');
                $nodeGroup->appendChild($badge);

                $text = $dom->createElement('text', (string) $meta['paso_numero']);
                $text->setAttribute('x', (string) $badgeX);
                $text->setAttribute('y', (string) ($badgeY + 5));
                $text->setAttribute('text-anchor', 'middle');
                $text->setAttribute('style', 'font-size:18px;font-weight:bold;fill:#FFFFFF;');
                $nodeGroup->appendChild($text);
            }

            if ($meta['nota'] !== null && $meta['nota'] !== '') {
                $caption = $dom->createElement('text', '* ' . $meta['nota']);
                $caption->setAttribute('x', (string) ($x + $width / 2));
                $caption->setAttribute('y', (string) ($y - 10));
                $caption->setAttribute('text-anchor', 'middle');
                $caption->setAttribute('style', 'font-size:14px;font-style:italic;fill:#595959;');
                $nodeGroup->appendChild($caption);
            }
        }
    }

    /**
     * Barra de color en la parte de arriba de cada carril (encabezado), ciclando una paleta fija
     * por índice — igual que la referencia, cada carril se distingue por su propio color en vez
     * del mismo fondo gris plano para todos.
     */
    private function paintLanes(\DOMDocument $dom, \DOMXPath $xpath, string $rootId, array $carrilOrder): void
    {
        // Mermaid dimensiona el rectángulo de cada carril justo a la altura de SU PROPIO
        // contenido — un carril con un solo paso queda mucho más corto que uno con varios,
        // dejando un hueco en blanco feo entre las franjas de color en vez de verse como
        // columnas parejas (que es como se ve en el documento de referencia). Primera pasada:
        // localizar el <rect> de fondo de cada carril y quedarnos con la altura máxima real.
        $bgRects = [];
        $maxHeight = 0.0;

        foreach ($carrilOrder as $carrilId) {
            $clusterGroup = $xpath->query("//*[@id=\"{$rootId}-{$carrilId}\"]")->item(0);
            if (! $clusterGroup instanceof \DOMElement) {
                continue;
            }

            $bg = $xpath->query('.//*[local-name()="rect"]', $clusterGroup)->item(0);
            if (! $bg instanceof \DOMElement) {
                continue;
            }

            $bgRects[$carrilId] = ['cluster' => $clusterGroup, 'rect' => $bg];
            $maxHeight = max($maxHeight, (float) $bg->getAttribute('height'));
        }

        foreach ($carrilOrder as $index => $carrilId) {
            if (! isset($bgRects[$carrilId])) {
                continue;
            }

            $clusterGroup = $bgRects[$carrilId]['cluster'];
            $bg = $bgRects[$carrilId]['rect'];

            // Todos los carriles se estiran a la misma altura (la del más alto) — el punto de
            // arriba (y) no se toca, los encabezados ya alinean ahí; solo se alarga hacia abajo.
            $bg->setAttribute('height', (string) $maxHeight);
            $bg->setAttribute('style', 'fill:' . self::LANE_BODY_FILL . ';stroke:' . self::LANE_BODY_STROKE . ';stroke-width:1px;');

            $color = self::LANE_HEADER_COLORS[$index % count(self::LANE_HEADER_COLORS)];

            // El texto del carril (cluster-label) vive dentro de un <foreignObject><div><p>
            // real de HTML, no de SVG — hay que recolorear el texto en CADA nivel (div/p/span)
            // porque el color heredado de Mermaid (".cluster-label span{color:#333}") se aplica
            // directo en cada uno de esos elementos, no solo en el contenedor.
            $labelGroup = $xpath->query('.//*[contains(@class,"cluster-label")]', $clusterGroup)->item(0);

            // Alto de la barra = el espacio que Mermaid YA reservó para su propio título del
            // carril (posición del cluster-label + alto de su foreignObject), no un número fijo —
            // un valor fijo se montaba sobre la primera caja en carriles muy compactos (con un
            // solo paso pegado arriba), porque el espacio real que Mermaid deja varía según el
            // tamaño de fuente/longitud del nombre del carril, confirmado con un diagrama real.
            $headerHeight = $this->labelReservedHeight($labelGroup) ?? 34.0;

            $header = $dom->createElement('rect');
            $header->setAttribute('x', $bg->getAttribute('x'));
            $header->setAttribute('y', $bg->getAttribute('y'));
            $header->setAttribute('width', $bg->getAttribute('width'));
            $header->setAttribute('height', (string) $headerHeight);
            $header->setAttribute('style', 'fill:' . $color . ';');
            $bg->parentNode?->insertBefore($header, $bg->nextSibling);

            if ($labelGroup instanceof \DOMElement) {
                $labelGroup->setAttribute('style', 'color:#FFFFFF !important;');
                foreach ($xpath->query('.//*', $labelGroup) as $descendant) {
                    if ($descendant instanceof \DOMElement) {
                        $descendant->setAttribute('style', 'color:#FFFFFF !important;');
                    }
                }
            }
        }
    }

    /**
     * Cuánto espacio vertical reservó Mermaid para el título de este carril: la posición Y de su
     * propio <g class="cluster-label" transform="translate(x,y)"> más el alto de su
     * <foreignObject>, con un pequeño margen. Devuelve null si no se pudo leer (el llamador cae
     * a un valor fijo en ese caso, nunca truena por esto).
     */
    private function labelReservedHeight(?\DOMElement $labelGroup): ?float
    {
        if ($labelGroup === null) {
            return null;
        }

        if (! preg_match('/translate\(\s*[-\d.]+\s*,\s*([-\d.]+)\s*\)/', $labelGroup->getAttribute('transform'), $m)) {
            return null;
        }

        $foreignObject = null;
        foreach ($labelGroup->childNodes as $child) {
            if ($child instanceof \DOMElement && $child->localName === 'foreignObject') {
                $foreignObject = $child;
                break;
            }
        }

        if ($foreignObject === null || ! $foreignObject->hasAttribute('height')) {
            return null;
        }

        return (float) $m[1] + (float) $foreignObject->getAttribute('height') + 8;
    }

    /** @return array{x: float, y: float, width: float} Esquina superior-izquierda y ancho del cuadro que envuelve al polígono. */
    private function polygonBounds(\DOMElement $polygon): array
    {
        $xs = [];
        $ys = [];

        foreach (preg_split('/\s+/', trim($polygon->getAttribute('points'))) as $pair) {
            [$px, $py] = array_pad(explode(',', $pair), 2, '0');
            $xs[] = (float) $px;
            $ys[] = (float) $py;
        }

        if ($xs === []) {
            return ['x' => 0.0, 'y' => 0.0, 'width' => 0.0];
        }

        return ['x' => min($xs), 'y' => min($ys), 'width' => max($xs) - min($xs)];
    }

    private function findNodeGroup(\DOMXPath $xpath, string $rootId, string $id): ?\DOMElement
    {
        $node = $xpath->query("//*[starts-with(@id,\"{$rootId}-flowchart-{$id}-\")]")->item(0);

        return $node instanceof \DOMElement ? $node : null;
    }
}
