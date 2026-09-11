<?php

namespace App\Console\Commands;

use App\Services\AiProcedureGenerationService;
use App\Services\DiagramTitleBarComposer;
use App\Services\OfficeDocumentConverter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * Verifica, en el servidor donde corra este comando, que todas las dependencias externas del
 * módulo de Procesos (migración, GD, LibreOffice, mermaid-cli/Node.js, API key de Anthropic)
 * estén presentes — pensado para correrlo justo después de desplegar, sin tener que probar cada
 * cosa manualmente desde la interfaz (crear un documento, ver un ppt, etc.).
 *
 * `--deep` además ejecuta una conversión/render real de prueba (no solo revisa que el binario
 * exista) — más lento, pero es la única forma de detectar problemas de permisos o de que el
 * binario esté instalado pero no funcione.
 */
class CheckProcessesRequirements extends Command
{
    protected $signature = 'processes:check-requirements {--deep : Además de revisar que los binarios existan, ejecuta una conversión de prueba real}';

    protected $description = 'Verifica que las dependencias del módulo de Procesos (migración, GD, LibreOffice, mermaid-cli, Puppeteer, Anthropic) estén listas en este servidor';

    public function handle(OfficeDocumentConverter $officeConverter, DiagramTitleBarComposer $titleBarComposer, AiProcedureGenerationService $aiService): int
    {
        $deep = (bool) $this->option('deep');
        $failures = 0;

        $this->components->info('Verificando dependencias del módulo de Procesos' . ($deep ? ' (modo --deep)' : ''));

        $failures += $this->check(
            'Migración body_html (regulation_versions)',
            fn () => Schema::hasColumn('regulation_versions', 'body_html'),
            'Falta correr "php artisan migrate".'
        );

        $failures += $this->check(
            'Extensión GD de PHP',
            fn () => extension_loaded('gd'),
            'Habilita "extension=gd" en php.ini y reinicia el servidor. Sin esto, el diagrama de flujo se genera sin la barra de título.'
        );

        $failures += $this->check(
            'ANTHROPIC_API_KEY configurada',
            fn () => filled(config('services.anthropic.key')),
            'Falta ANTHROPIC_API_KEY en el .env — el wizard de IA no puede generar documentos sin esto.'
        );

        $failures += $this->check(
            'Node.js disponible',
            function () {
                exec('node --version 2>&1', $output, $exitCode);

                return $exitCode === 0;
            },
            'Node.js no está instalado o no está en el PATH del usuario que corre PHP — hace falta para renderizar el diagrama de flujo.'
        );

        $failures += $this->check(
            'mermaid-cli instalado (node_modules)',
            fn () => is_file(base_path('node_modules/@mermaid-js/mermaid-cli/src/cli.js')),
            'Falta correr "npm install" en la raíz del proyecto.'
        );

        $failures += $this->check(
            'Puppeteer instalado (node_modules)',
            fn () => is_dir(base_path('node_modules/puppeteer')),
            'Falta correr "npm install" en la raíz del proyecto — hace falta para pintar el diagrama de flujo con el estilo exacto de referencia.'
        );

        $failures += $this->check(
            'Script de renderizado del diagrama presente',
            fn () => is_file(resource_path('diagram-renderer/render.mjs')),
            'Falta resources/diagram-renderer/render.mjs — revisa que el despliegue haya traído todos los archivos nuevos.'
        );

        $failures += $this->check(
            'LibreOffice (soffice) disponible',
            fn () => $officeConverter->isAvailable(),
            'Instala LibreOffice — sin esto, "Ver" en documentos .ppt/.pptx/.xls/.xlsx/.doc solo permite descargar, no previsualizar.'
        );

        $failures += $this->check(
            'Fuente bold para la barra de título del diagrama',
            fn () => $titleBarComposer->hasBoldFont(),
            'No es bloqueante: la barra de título del diagrama se dibuja igual, solo con una tipografía más tosca (fuente de mapa de bits de GD).',
            warningOnly: true
        );

        if ($deep) {
            $this->newLine();
            $this->components->info('Pruebas reales (--deep)');

            $diagramResult = $aiService->testDiagramPipeline();
            $failures += $this->check(
                'Render de diagrama de flujo de prueba (Mermaid + Puppeteer)',
                fn () => $diagramResult['ok'],
                $diagramResult['ok']
                    ? ''
                    : "Falló (exit code {$diagramResult['exit_code']}): "
                        . ($diagramResult['stderr'] ?: $diagramResult['stdout'] ?: '(sin salida)')
            );

            if ($officeConverter->isAvailable()) {
                $failures += $this->check(
                    'Conversión de prueba a PDF con LibreOffice',
                    fn () => $this->testOfficeConversion($officeConverter),
                    'LibreOffice está instalado pero la conversión de prueba falló — revisa permisos del usuario que corre PHP sobre el directorio temporal del sistema.'
                );
            }
        }

        $this->newLine();

        if ($failures > 0) {
            $this->components->error("{$failures} verificación(es) fallaron.");

            return self::FAILURE;
        }

        $this->components->info('Todo listo — el módulo de Procesos debería funcionar por completo en este servidor.');

        return self::SUCCESS;
    }

    private function check(string $label, \Closure $test, string $hint, bool $warningOnly = false): int
    {
        try {
            $passed = (bool) $test();
        } catch (\Throwable $e) {
            $passed = false;
            $hint = "Error: {$e->getMessage()}";
        }

        if ($passed) {
            $this->components->twoColumnDetail($label, '<fg=green>OK</>');

            return 0;
        }

        $this->components->twoColumnDetail($label, $warningOnly ? '<fg=yellow>ADVERTENCIA</>' : '<fg=red>FALTA</>');
        $this->line("    <fg=gray>{$hint}</>");

        return $warningOnly ? 0 : 1;
    }

    private function testOfficeConversion(OfficeDocumentConverter $converter): bool
    {
        $input = tempnam(sys_get_temp_dir(), 'office_check_') . '.rtf';
        file_put_contents($input, '{\rtf1\ansi Documento de prueba.}');

        $pdf = $converter->toPdf($input, 'rtf');
        @unlink($input);

        return $pdf !== null && str_starts_with($pdf, '%PDF');
    }
}
