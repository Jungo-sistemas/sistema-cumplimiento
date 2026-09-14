<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Convierte documentos de Office (.doc, .xls, .xlsx, .ppt, .pptx, etc.) a PDF con LibreOffice
 * en modo headless, para poder previsualizarlos en el navegador igual que un PDF nativo — el
 * navegador ya sabe renderizar PDF, así que no hace falta ningún visor propio por formato.
 *
 * Nunca lanza excepción: si LibreOffice no está instalado o la conversión falla, devuelve null
 * y quien llama debe caer de vuelta a "descargar el archivo original" en vez de romper la página.
 */
class OfficeDocumentConverter
{
    private const CONVERTIBLE_EXTENSIONS = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'odp', 'rtf'];

    public function isConvertible(string $extension): bool
    {
        return in_array(strtolower($extension), self::CONVERTIBLE_EXTENSIONS, true);
    }

    /**
     * Si LibreOffice está instalado y localizable en este servidor — sin convertir nada, solo
     * para diagnóstico (ver comando processes:check-requirements).
     */
    public function isAvailable(): bool
    {
        return $this->findSoffice() !== null;
    }

    /**
     * @param  string  $inputPath  Ruta real en disco del archivo (puede tener cualquier nombre).
     * @param  string  $extension  Extensión REAL del documento (doc/xls/xlsx/ppt/pptx/...) — los
     *                             archivos se guardan con un nombre generado (hash) sin extensión
     *                             útil, así que LibreOffice necesita que se la indiquemos explícitamente
     *                             para poder detectar el formato de entrada; sin ella, intenta adivinarlo
     *                             del nombre del archivo y falla silenciosamente con archivos "*.bin".
     */
    public function toPdf(string $inputPath, string $extension): ?string
    {
        $soffice = $this->findSoffice();

        if ($soffice === null) {
            Log::warning('OfficeDocumentConverter: LibreOffice (soffice) no está instalado — no se puede generar la vista previa de este archivo.');

            return null;
        }

        $outDir = sys_get_temp_dir() . '/office_preview_' . uniqid();
        $profileDir = sys_get_temp_dir() . '/soffice_profile_' . uniqid();
        mkdir($outDir, 0777, true);

        // Copia con la extensión real: soffice detecta el formato de entrada por la extensión del
        // nombre de archivo, y en disco los archivos se guardan con un nombre hash sin extensión útil.
        $namedInput = $outDir . '/input.' . strtolower($extension);
        copy($inputPath, $namedInput);

        try {
            [$exitCode, $stdout, $stderr] = $this->runSoffice($soffice, $namedInput, $outDir, $profileDir);

            $expected = $outDir . '/input.pdf';

            if ($exitCode === 0 && is_file($expected)) {
                return file_get_contents($expected);
            }

            Log::warning('OfficeDocumentConverter: soffice no pudo convertir el archivo a PDF', [
                'exit_code' => $exitCode,
                'output' => $stderr ?: $stdout,
                'input' => $inputPath,
            ]);

            return null;
        } catch (\Throwable $e) {
            Log::warning('OfficeDocumentConverter: fallo al convertir a PDF', ['error' => $e->getMessage()]);

            return null;
        } finally {
            $this->rrmdir($outDir);
            $this->rrmdir($profileDir);
        }
    }

    private function findSoffice(): ?string
    {
        foreach ([
            'C:/Program Files/LibreOffice/program/soffice.exe',
            'C:/Program Files (x86)/LibreOffice/program/soffice.exe',
            '/usr/bin/soffice',
            '/usr/lib/libreoffice/program/soffice',
        ] as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        $command = str_starts_with(PHP_OS, 'WIN') ? 'where soffice' : 'which soffice';
        $output = [];
        @exec($command . ' 2>&1', $output, $exitCode);

        return ($exitCode === 0 && ! empty($output[0])) ? trim($output[0]) : null;
    }

    /**
     * Igual patrón que AiProcedureGenerationService::runDiagramRenderer(): proc_open() nativo con
     * pipes no bloqueantes y sondeo manual — Symfony Process (Illuminate\Support\Facades\Process)
     * hace tronar procesos hijos de Node en este servidor Windows; por consistencia y porque ya
     * está probado que funciona, se usa el mismo mecanismo aquí para el proceso de soffice.
     *
     * -env:UserInstallation apunta a un perfil temporal único por conversión: sin esto, dos
     * conversiones concurrentes chocan porque LibreOffice bloquea su perfil de usuario por defecto.
     */
    private function runSoffice(string $soffice, string $input, string $outDir, string $profileDir): array
    {
        $profileUrl = 'file:///' . str_replace('\\', '/', $profileDir);

        $cmd = sprintf(
            '%s --headless --norestore --nolockcheck -env:UserInstallation=%s --convert-to pdf --outdir %s %s',
            escapeshellarg($soffice),
            escapeshellarg($profileUrl),
            escapeshellarg($outDir),
            escapeshellarg($input)
        );

        $proc = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (! is_resource($proc)) {
            return [1, '', 'No se pudo iniciar el proceso de LibreOffice.'];
        }

        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);

        $stdout = '';
        $stderr = '';
        $start = microtime(true);
        $timeoutSeconds = 60;

        do {
            $stdout .= stream_get_contents($pipes[1]);
            $stderr .= stream_get_contents($pipes[2]);
            $status = proc_get_status($proc);

            if ($status['running'] && (microtime(true) - $start) > $timeoutSeconds) {
                proc_terminate($proc);
                $stderr .= "\n[soffice: tiempo de espera agotado tras {$timeoutSeconds}s]";
                break;
            }

            if ($status['running']) {
                usleep(150_000);
            }
        } while ($status['running']);

        $stdout .= stream_get_contents($pipes[1]);
        $stderr .= stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        return [$exitCode, $stdout, $stderr];
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }

        @rmdir($dir);
    }
}
