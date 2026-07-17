<?php

namespace App\Services;

use Anthropic\Client;
use Anthropic\Messages\JSONOutputFormat;
use Anthropic\Messages\OutputConfig;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpWord\IOFactory;
use RuntimeException;

class AiProcedureGenerationService
{
    /** Word largo con las condiciones/instrucciones de redacción. Dentro de su texto remite al documento de ejemplo. */
    private const CONDITIONS_DOCX = 'documento_condiciones.docx';

    /** Documento de ejemplo que muestra cómo debe quedar el resultado final (formato/estilo de referencia). */
    private const EXAMPLE_DOCX = 'documento_ejemplo.docx';

    /** Texto de validación/instrucciones adicional (system prompt). */
    private const VALIDATION_TEXT = 'texto_validacion.md';

    /** Imagen de ejemplo del diagrama de flujo por carriles — se manda como referencia visual (Claude ve imágenes). */
    private const DIAGRAM_EXAMPLE_IMAGE = 'diagrama_ejemplo.png';

    /** Marcador que la IA debe dejar en documento_html donde va el diagrama — se reemplaza por la imagen ya renderizada. */
    private const DIAGRAM_MARKER = '{{DIAGRAMA_FLUJO}}';

    public const DETAIL_FIELDS = [
        'problema_resuelve', 'resultado_esperado', 'areas_aplica', 'fuera_alcance',
        'indicador_proceso', 'indicador_resultado', 'meta_valor', 'frecuencia_medicion',
        'que_detona', 'lista_actividades', 'areas_ejecutan', 'decisiones_control',
        'documentos_usados', 'resultado_entregable', 'areas_roles_mapa',
        'procedimientos_relacionados', 'proveedores_clientes', 'terminos_abreviaturas',
        'riesgos_errores', 'requerimientos_normativos',
    ];

    /**
     * @param  array<string, string>  $wizardData  Campos capturados por el wizard (el esqueleto).
     * @param  array{details: array<string, string>, documento_html: string}|null  $previousResult  Resultado anterior, si esta es una revisión.
     * @param  string|null  $feedback  Cambios solicitados por el usuario sobre $previousResult.
     * @return array{details: array<string, string>, documento_html: string}
     */
    public function generate(array $wizardData, ?array $previousResult = null, ?string $feedback = null): array
    {
        $client = new Client(apiKey: config('services.anthropic.key'));
        $model = config('services.anthropic.model');
        $startedAt = microtime(true);

        $message = $client->messages->create(
            model: $model,
            maxTokens: 24000,
            system: $this->validationText(),
            messages: [[
                'role' => 'user',
                'content' => [
                    [
                        'type' => 'image',
                        'source' => [
                            'type' => 'base64',
                            'media_type' => 'image/png',
                            'data' => base64_encode($this->diagramExampleImage()),
                        ],
                    ],
                    [
                        'type' => 'text',
                        'text' => $this->buildPrompt($wizardData, $previousResult, $feedback),
                    ],
                ],
            ]],
            outputConfig: OutputConfig::with(format: JSONOutputFormat::with(schema: $this->schema())),
        );

        Log::info('AiProcedureGenerationService: generación completada', [
            'model' => $model,
            'revision' => $previousResult !== null,
            'seconds' => round(microtime(true) - $startedAt, 2),
            'input_tokens' => $message->usage->inputTokens ?? null,
            'output_tokens' => $message->usage->outputTokens ?? null,
            'stop_reason' => $message->stopReason ?? null,
        ]);

        // Con adaptive thinking, el primer bloque puede ser "thinking" en vez de "text" — buscamos el bloque de texto explícitamente.
        $textBlock = null;
        foreach ($message->content as $block) {
            if (($block->type ?? null) === 'text') {
                $textBlock = $block;
                break;
            }
        }

        $raw = $textBlock->text ?? null;

        if ($raw === null) {
            throw new RuntimeException('La IA no devolvió contenido para el procedimiento.');
        }

        $data = json_decode($raw, true);

        if (! is_array($data) || ! isset($data['details'], $data['documento_html'])) {
            throw new RuntimeException('La IA devolvió una respuesta en un formato inesperado (stop_reason: ' . ($message->stopReason ?? 'desconocido') . ').');
        }

        $data['documento_html'] = $this->insertFlowDiagram(
            $data['documento_html'],
            $data['diagrama_flujo_mermaid'] ?? null
        );
        $data['documento_html'] = $this->sanitizeHtmlForWord($data['documento_html']);

        return $data;
    }

    /**
     * Reemplaza el marcador {{DIAGRAMA_FLUJO}} por el diagrama ya renderizado como imagen
     * (Mermaid → Kroki.io → PNG). Si no hay mermaid, Kroki falla, o el marcador no aparece
     * (la IA no lo respetó), se deja una nota simple en vez de bloquear la generación —
     * el documento completo nunca debe fallar solo por el diagrama.
     */
    private function insertFlowDiagram(string $html, ?string $mermaidSource): string
    {
        if (! str_contains($html, self::DIAGRAM_MARKER)) {
            return $html;
        }

        $fallback = '<p><em>(No se pudo generar el diagrama de flujo automáticamente.)</em></p>';

        if (empty($mermaidSource)) {
            return str_replace(self::DIAGRAM_MARKER, $fallback, $html);
        }

        $png = $this->renderMermaidDiagram($mermaidSource);

        $replacement = $png !== null
            ? '<img src="data:image/png;base64,' . base64_encode($png) . '" style="max-width:100%;" />'
            : $fallback;

        return str_replace(self::DIAGRAM_MARKER, $replacement, $html);
    }

    /**
     * Convierte una definición de diagrama Mermaid a PNG vía Kroki.io (gratuito, sin cuenta).
     * No hay motor local para esto (sin Imagick/GD, y PhpWord no soporta SVG) — devuelve null
     * en vez de lanzar una excepción si el servicio falla, para no bloquear todo el documento.
     */
    private function renderMermaidDiagram(string $mermaidSource): ?string
    {
        try {
            // En local (detrás del proxy/firewall corporativo, mismo problema visto antes con
            // otras herramientas) el bundle de certificados de PHP no confía en el proxy con
            // inspección SSL y la petición falla con "self-signed certificate in certificate
            // chain" — no ocurre en producción. Se desactiva la verificación SOLO en local.
            $response = Http::withHeaders(['Content-Type' => 'text/plain'])
                ->withOptions(['verify' => ! app()->environment('local')])
                ->timeout(20)
                ->withBody($mermaidSource, 'text/plain')
                ->post('https://kroki.io/mermaid/png');

            if (! $response->successful()) {
                Log::warning('AiProcedureGenerationService: Kroki no pudo renderizar el diagrama', [
                    'status' => $response->status(),
                ]);

                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('AiProcedureGenerationService: fallo al renderizar el diagrama de flujo', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function diagramExampleImage(): string
    {
        $path = resource_path('ai-reference/' . self::DIAGRAM_EXAMPLE_IMAGE);

        if (! file_exists($path)) {
            throw new RuntimeException("Falta la imagen de ejemplo del diagrama en {$path}. Colócala antes de generar procedimientos con IA.");
        }

        return file_get_contents($path);
    }

    /**
     * PhpWord parsea el HTML como XML estricto, mucho menos tolerante que un navegador:
     * - Etiquetas vacías como <br> o <hr> deben autocerrarse (<br/>).
     * - Un mismo atributo (típicamente "style") no puede repetirse en la misma etiqueta
     *   — el modelo lo hace sobre todo al revisar (ej. agrega un segundo style="..." en
     *   vez de fusionarlo con el existente), y eso rompe DOMDocument::loadXML().
     * - Un "<" o "&" sueltos en el texto (ej. "100% en < 24 h", común en tablas de
     *   indicadores) rompen el .docx de forma más sutil: PhpWord 1.1.0 tiene un bug real
     *   donde, aunque el HTML de entrada venga bien escapado ("&lt;"), internamente lo
     *   decodifica al caracter literal y al ESCRIBIR el .docx no lo vuelve a escapar —
     *   el archivo queda con XML inválido y ni "Ver" ni "Editar" pueden volver a abrirlo
     *   (confirmado con pruebas aisladas contra el pipeline real de PhpWord). Se
     *   reemplazan por el caracter Unicode de ancho completo equivalente (se ve idéntico,
     *   no tiene significado especial en XML) en vez de intentar escaparlos mejor.
     * También se quita cualquier <script>, ya que este HTML se muestra en el navegador
     * durante la vista previa antes de convertirse a Word.
     */
    public function sanitizeHtmlForWord(string $html): string
    {
        $html = $this->stripPageWrapper($html);
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = $this->escapeStrayCharsForPhpWord($html);
        $html = $this->dedupeTagAttributes($html);

        return preg_replace_callback('/<(br|hr|img)\b([^>]*)>/i', function (array $m) {
            $attrs = rtrim($m[2]);

            return str_ends_with($attrs, '/')
                ? "<{$m[1]}{$attrs}>"
                : "<{$m[1]}{$attrs} />";
        }, $html);
    }

    /**
     * El schema/prompt prohíben explícitamente <!DOCTYPE>, <html>, <head> y <body> —
     * el modelo casi siempre lo respeta, pero de vez en cuando (más probable cuanto más
     * contenido lleva el prompt, ej. al agregar la imagen de referencia del diagrama)
     * los incluye de todos modos. Un <body> real confunde el parser de PhpWord y produce
     * "Cannot add TextRun in TextRun" al convertir a Word. Se quitan aquí en vez de
     * confiar únicamente en la instrucción del prompt.
     */
    private function stripPageWrapper(string $html): string
    {
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<head\b[^>]*>.*?<\/head>/si', '', $html);
        $html = preg_replace('/<\/?html\b[^>]*>/i', '', $html);

        return preg_replace('/<\/?body\b[^>]*>/i', '', $html);
    }

    /**
     * Reemplaza "<" y "&" sueltos (no parte de una etiqueta o entidad real) — y las
     * entidades "&lt;"/"&amp;" ya escapadas, que de todos modos se decodifican al
     * caracter literal antes de llegar al bug de escritura de PhpWord — por su
     * equivalente Unicode de ancho completo. No toca tags reales ("<table>", "</p>")
     * ni otras entidades (acentos, "&nbsp;", etc.), que no disparan el bug.
     */
    private function escapeStrayCharsForPhpWord(string $html): string
    {
        $html = str_replace(['&lt;', '&amp;'], ['＜', '＆'], $html);
        $html = preg_replace('/&(?!(?:[a-zA-Z][a-zA-Z0-9]*|#[0-9]+|#x[0-9a-fA-F]+);)/', '＆', $html);

        return preg_replace('/<(?![a-zA-Z\/!?])/', '＜', $html);
    }

    /**
     * Si una etiqueta repite un atributo (ej. dos "style"), lo fusiona en uno solo
     * (concatenando declaraciones para "style"; el resto se queda con el último valor).
     */
    private function dedupeTagAttributes(string $html): string
    {
        return preg_replace_callback('/<([a-zA-Z][a-zA-Z0-9]*)((?:\s+[^<>]*)?)>/', function (array $m) {
            $tag = $m[1];
            $rest = $m[2];

            $selfClosing = (bool) preg_match('/\/\s*$/', $rest);
            $rest = preg_replace('/\/\s*$/', '', $rest);

            preg_match_all('/([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*"([^"]*)"/', $rest, $attrMatches, PREG_SET_ORDER);

            if (count($attrMatches) === 0) {
                return $m[0];
            }

            $merged = [];
            foreach ($attrMatches as $attrMatch) {
                $name = strtolower($attrMatch[1]);
                $value = $attrMatch[2];

                $merged[$name] = isset($merged[$name]) && $name === 'style'
                    ? rtrim($merged[$name], '; ') . '; ' . $value
                    : $value;
            }

            $out = '<' . $tag;
            foreach ($merged as $name => $value) {
                $out .= ' ' . $name . '="' . $value . '"';
            }

            return $out . ($selfClosing ? ' />' : '>');
        }, $html);
    }

    private function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'details' => [
                    'type' => 'object',
                    'properties' => array_fill_keys(self::DETAIL_FIELDS, [
                        'type' => 'string',
                        'description' => 'Contenido afinado de este campo del procedimiento, en español, listo para publicarse.',
                    ]),
                    'required' => self::DETAIL_FIELDS,
                    'additionalProperties' => false,
                ],
                'documento_html' => [
                    'type' => 'string',
                    'description' => 'SOLO el fragmento de contenido del procedimiento, listo para insertarse dentro de un <body> ya existente. '
                        . 'PROHIBIDO incluir <!DOCTYPE>, <html>, <head>, <style>, <body> o <script> — el conversor a Word no los interpreta y el documento queda mal. '
                        . 'Usa únicamente: <h1>-<h6>, <p>, <table>/<tr>/<td>/<th>, <strong>, <em>, <u>, <ul>/<ol>/<li>, <br>. '
                        . 'Para color o resaltado usa style inline en la propia etiqueta (ej. <p style="color:#1A428A;">), nunca clases CSS ni hojas de estilo. '
                        . 'En la sección "Diagrama de Flujo del Proceso", el ÚNICO contenido debe ser el marcador literal '
                        . '<p>{{DIAGRAMA_FLUJO}}</p> — nada de notas, ni descripciones, ni el diagrama en sí: el sistema '
                        . 'inserta ahí la imagen ya renderizada a partir de diagrama_flujo_mermaid. '
                        . 'NO incluyas ningún encabezado con logo/nombre/código/versión/elaboró/aprobó/fecha/número de '
                        . 'página al inicio del documento — el sistema agrega ese encabezado automáticamente en cada '
                        . 'página, con formato fijo, fuera de este campo. Empieza documento_html directo en el título '
                        . 'del procedimiento u "Objetivo".',
                ],
                'diagrama_flujo_mermaid' => [
                    'type' => 'string',
                    'description' => 'El diagrama de flujo de ESTE procedimiento en sintaxis Mermaid, imitando el estilo visual '
                        . 'de la imagen de referencia adjunta (carriles por puesto/responsable, óvalos de inicio/fin, pasos '
                        . 'numerados, rombos de decisión con ramas Sí/No). Debe empezar con "flowchart LR" y usar un '
                        . '"subgraph NOMBRE_CORTO[\"Nombre del puesto\"]" con "direction TB" adentro por cada puesto '
                        . 'involucrado, en el orden en que participan. No uses acentos ni caracteres especiales en los '
                        . 'IDs de nodos/subgraphs (sí puedes usarlos dentro de las etiquetas de texto entre corchetes/comillas).',
                ],
            ],
            'required' => ['details', 'documento_html', 'diagrama_flujo_mermaid'],
            'additionalProperties' => false,
        ];
    }

    private function buildPrompt(array $wizardData, ?array $previousResult = null, ?string $feedback = null): string
    {
        $parts = [
            'La imagen adjunta es un EJEMPLO de cómo debe verse el diagrama de flujo del procedimiento — carriles por '
                . 'puesto/responsable colocados lado a lado como columnas, óvalos de inicio y fin, pasos numerados dentro '
                . 'de cada carril, rombos de decisión con ramas Sí/No, flechas que cruzan de un carril a otro cuando cambia '
                . 'el responsable. Genera el diagrama de ESTE procedimiento (campo diagrama_flujo_mermaid) siguiendo ese '
                . 'mismo estilo visual, con los pasos y responsables reales de este procedimiento — no copies el contenido '
                . 'del ejemplo, solo su forma.',

            'El usuario capturó el siguiente esqueleto de procedimiento en un wizard. Es el punto de partida, en formato JSON:',
            json_encode($wizardData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),

            'Documento con las condiciones e instrucciones de redacción que debes seguir. '
                . 'Dentro de su propio texto, este documento hace referencia al "documento de ejemplo" '
                . '(que se incluye después) como muestra de cómo debe quedar el resultado final — '
                . 'úsalo para entender las reglas, no para copiar su formato:',
            $this->docxToPlainText(self::CONDITIONS_DOCX, 'documento de condiciones'),

            'Documento de ejemplo: referencia de cómo debe quedar el resultado final '
                . '(estructura, tono y nivel de detalle esperado). Imita su forma, no su contenido literal:',
            $this->docxToPlainText(self::EXAMPLE_DOCX, 'documento de ejemplo'),
        ];

        if ($previousResult !== null && $feedback !== null) {
            $parts[] = 'Ya redactaste una versión de este procedimiento con las fuentes anteriores. Este fue el resultado, '
                . 'en el mismo formato que debes devolver ahora (details + documento_html):';
            $parts[] = json_encode($previousResult, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $parts[] = 'El usuario revisó ese resultado y pidió estos cambios puntuales: "' . $feedback . '". '
                . 'Aplica ÚNICAMENTE los cambios solicitados sobre el resultado anterior. Todo lo que no se pidió cambiar '
                . 'debe quedar exactamente igual (mismo contenido, redacción y estructura). Devuelve el documento y los '
                . 'campos completos y actualizados, no solo la parte modificada.';
        } else {
            $parts[] = 'Con las tres fuentes anteriores (esqueleto del wizard, condiciones, ejemplo), afina cada campo del '
                . 'esqueleto (mismo formato de texto plano, sin HTML) y redacta el documento completo del '
                . 'procedimiento en el campo documento_html, siguiendo también las instrucciones del system prompt.';
        }

        $parts[] = 'Recuerda: documento_html es solo el fragmento de contenido, NUNCA un documento HTML completo '
            . '(nada de <!DOCTYPE>, <html>, <head>, <style> ni <body>). Y en la sección "Diagrama de Flujo del Proceso" '
            . 'de documento_html, escribe ÚNICAMENTE <p>{{DIAGRAMA_FLUJO}}</p> — el diagrama real va en el campo '
            . 'diagrama_flujo_mermaid, no ahí. Tampoco escribas la tabla de encabezado (logo, nombre, código, versión, '
            . 'elaboró, aprobó, fecha, número de página) al inicio de documento_html — eso lo agrega el sistema '
            . 'automáticamente fuera de este campo, con un formato fijo que nunca cambia.';

        return implode("\n\n", $parts);
    }

    private function docxToPlainText(string $filename, string $label): string
    {
        $path = resource_path('ai-reference/' . $filename);

        if (! file_exists($path)) {
            throw new RuntimeException("Falta el {$label} en {$path}. Colócalo antes de generar procedimientos con IA.");
        }

        $phpWord = IOFactory::load($path);
        $writer = IOFactory::createWriter($phpWord, 'HTML');
        $tmp = tempnam(sys_get_temp_dir(), 'phpword_ref_') . '.html';
        $writer->save($tmp);
        $html = file_get_contents($tmp);
        @unlink($tmp);

        $body = preg_match('/<body[^>]*>(.*?)<\/body>/si', $html, $m) ? $m[1] : $html;

        return trim(html_entity_decode(strip_tags($body)));
    }

    private function validationText(): string
    {
        $path = resource_path('ai-reference/' . self::VALIDATION_TEXT);

        if (! file_exists($path)) {
            throw new RuntimeException("Falta el texto de validación en {$path}. Colócalo antes de generar procedimientos con IA.");
        }

        return file_get_contents($path);
    }
}
