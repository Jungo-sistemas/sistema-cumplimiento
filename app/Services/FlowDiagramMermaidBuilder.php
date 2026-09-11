<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Convierte la topología que redacta la IA (campo `diagrama_flujo`: carriles, decisiones, pasos
 * especiales) + `documento.pasos` (ya redactado, mismo texto que ve el cuerpo del documento) en
 * texto Mermaid plano — sin ningún color ni estilo, eso ya no le toca a Mermaid ni a la IA
 * (ver App\Services\FlowDiagramSvgPainter). El texto y el carril de cada paso normal se derivan
 * aquí directo de `documento.pasos`, nunca los redacta la IA por su cuenta — así el diagrama y el
 * cuerpo del documento nunca pueden quedar inconsistentes entre sí.
 *
 * Devuelve también un mapa id → metadatos (tipo/carril/paso/nota) para que FlowDiagramSvgPainter
 * sepa exactamente qué pintar en cada nodo sin tener que volver a inferirlo de la sintaxis Mermaid
 * (a diferencia del MermaidDiagramStyler anterior, que sí tenía que adivinar el tipo por la forma
 * del nodo porque la IA escribía el Mermaid directamente).
 */
class FlowDiagramMermaidBuilder
{
    /**
     * Mismo ajuste de espaciado/tamaño de fuente que ya tenía MermaidDiagramStyler::INIT_DIRECTIVE
     * — el diagrama siempre se encoge a 6.5in en el documento final (ver
     * AiProcedureGenerationService::imageDimensionAttrs()), así que sin esto las etiquetas largas
     * quedan ilegibles. Es afinación de LAYOUT (lo sigue resolviendo Mermaid), no de color.
     *
     * subGraphTitleMargin.top (por defecto 0 en Mermaid) fuerza espacio real de sobra entre el
     * título de cada carril y su primer nodo — sin esto, FlowDiagramSvgPainter podía dejar la
     * insignia numerada (que se superpone a la esquina de la caja) encimada con el texto del
     * encabezado de color en carriles muy compactos: no es un ajuste de la capa de pintura, es
     * espacio real reservado por Mermaid al calcular el acomodo.
     */
    private const INIT_DIRECTIVE = '%%{init: {"flowchart": {"nodeSpacing": 15, "rankSpacing": 25, '
        . '"padding": 6, "subGraphTitleMargin": {"top": 30, "bottom": 6}}, '
        . '"themeVariables": {"fontSize": "26px"}}}%%';

    /**
     * @param  array{carriles: array<int, array{id: string, nombre: string}>, decisiones: array<int, array{id: string, carril_id: string, texto: string, tras_paso: int, destino_si: string, etiqueta_si: string, destino_no: string, etiqueta_no: string}>, pasos_especiales: array<int, array{paso_numero: int, nota: string}>}  $diagrama
     * @param  array<int, array{titulo: string, responsable: string}>  $pasos  documento.pasos, mismo orden que el documento.
     * @return array{mermaid: string, nodeMeta: array<string, array{tipo: string, carril_id: ?string, paso_numero: ?int, nota: ?string}>}
     */
    public function build(array $diagrama, array $pasos): array
    {
        $carriles = $diagrama['carriles'] !== [] ? $diagrama['carriles'] : [['id' => 'proceso', 'nombre' => 'Proceso']];
        $carrilIds = array_column($carriles, 'id');

        $notasPorPaso = [];
        foreach ($diagrama['pasos_especiales'] as $especial) {
            $notasPorPaso[$especial['paso_numero']] = $especial['nota'];
        }

        // --- Nodos ---------------------------------------------------------
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
            ];

            $label = $this->escapeLabel("{$numero}. " . ($paso['titulo'] ?? ''));
            $nodesByCarril[$carrilId][] = $esEspecial ? "{$id}[[\"{$label}\"]]" : "{$id}[\"{$label}\"]";
        }

        $primerCarril = $pasos !== [] ? $this->resolveCarrilId($pasos[0]['responsable'] ?? '', $carriles) : $carrilIds[0];
        $ultimoCarril = $pasos !== [] ? $this->resolveCarrilId($pasos[count($pasos) - 1]['responsable'] ?? '', $carriles) : $carrilIds[0];

        $nodeMeta['inicio'] = ['tipo' => 'inicio', 'carril_id' => $primerCarril, 'paso_numero' => null, 'nota' => null];
        $nodeMeta['fin'] = ['tipo' => 'fin', 'carril_id' => $ultimoCarril, 'paso_numero' => null, 'nota' => null];
        array_unshift($nodesByCarril[$primerCarril], 'inicio(["' . $this->escapeLabel('INICIO DEL PROCESO') . '"])');
        $nodesByCarril[$ultimoCarril][] = 'fin(["' . $this->escapeLabel('FIN DEL PROCESO') . '"])';

        $decisionIdMap = []; // id tal como lo escribió la IA => id ya saneado
        foreach ($diagrama['decisiones'] as $decision) {
            $sane = $this->sanitizeId($decision['id']);
            $decisionIdMap[$decision['id']] = $sane;

            $carrilId = in_array($decision['carril_id'], $carrilIds, true) ? $decision['carril_id'] : $primerCarril;
            $nodeMeta[$sane] = ['tipo' => 'decision', 'carril_id' => $carrilId, 'paso_numero' => null, 'nota' => null];
            $nodesByCarril[$carrilId][] = $sane . '{"' . $this->escapeLabel($decision['texto']) . '"}';
        }

        // --- Aristas ---------------------------------------------------------
        // Cadena por defecto: inicio -> paso1 -> paso2 -> ... -> pasoN -> fin. Cada decisión
        // "corta" la arista por defecto que sale del paso indicado y la reemplaza por sus dos
        // ramas — el resto de la cadena que no toca ninguna decisión queda igual.
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

        $edges = [];
        foreach ($sequence as $id) {
            if ($id === 'fin') {
                continue;
            }
            $to = $overrides[$id] ?? $defaultNext[$id];
            $edges[] = [$id, $to, null];
        }
        foreach ($branches as $decisionId => $ramas) {
            foreach ($ramas as [$to, $label]) {
                $edges[] = [$decisionId, $to, $label];
            }
        }

        // --- Ensamblar Mermaid ---------------------------------------------
        $lines = [self::INIT_DIRECTIVE, 'flowchart LR'];

        foreach ($carriles as $carril) {
            $nodos = $nodesByCarril[$carril['id']] ?? [];
            if ($nodos === []) {
                continue;
            }
            $lines[] = 'subgraph ' . $this->sanitizeId($carril['id']) . '["' . $this->escapeLabel($carril['nombre']) . '"]';
            $lines[] = 'direction TB';
            foreach ($nodos as $nodo) {
                $lines[] = $nodo;
            }
            $lines[] = 'end';
        }

        foreach ($edges as [$from, $to, $label]) {
            $lines[] = $label
                ? "{$from} -->|" . $this->escapeLabel($label) . "| {$to}"
                : "{$from} --> {$to}";
        }

        return ['mermaid' => implode("\n", $lines), 'nodeMeta' => $nodeMeta];
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

    /** IDs de Mermaid: sin acentos, solo [A-Za-z0-9_], y que no empiecen con un dígito. */
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

    /** Las etiquetas van entre comillas dobles en Mermaid — solo hay que escapar comillas internas. */
    private function escapeLabel(string $text): string
    {
        return str_replace('"', '#quot;', $text);
    }
}
