<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\ProcessType;
use App\Models\Regulation;
use App\Models\RegulationVersion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Carga masiva de procedimientos antiguos (documentos ya existentes de antes de este sistema,
 * identificados en el inventario de origen por llevar "REG" en el código) hacia Procesos. Cada
 * fila del CSV se registra como un Regulation con is_legacy=true — visible pero no editable hasta
 * que alguien lo actualice con el wizard o subiendo una versión nueva (ver Regulation::isEditableBy
 * / RegulationController::confirmEditDraft / RegulationVersionController::store).
 *
 * El CSV es un maestro con TODAS las empresas mezcladas (no viene pre-filtrado) — el filtro real
 * de empresa lo pone la carpeta: cada fila solo se importa si su CÓDIGO coincide con un archivo
 * presente en {folder}, así que basta con que esa carpeta solo tenga los documentos de --company
 * para que las filas de otras empresas se omitan solas (se reportan como "sin archivo").
 *
 * Columnas del CSV, en este orden fijo (sin encabezados confiables: la primera fila de datos
 * puede venir vacía, y el separador decimal/de fecha varía):
 *   0 Impacto | 1 EMPRESA | 2 TIPO DE PROCESO | 3 TIPO DE DOCUMENTO | 4 ES UN ANEXO (Sí/No) |
 *   5 NOMBRE DEL DOCUMENTO | 6 CÓDIGO | 7 ELABORADO POR | 8 APROBADO POR | 9 FECHA DE EMISIÓN |
 *   10 FECHA DE VENCIMIENTO
 */
class ImportLegacyRegulations extends Command
{
    protected $signature = 'regulations:import-legacy
        {csv : CSV filtrado de una sola empresa (mismas columnas que el Excel de origen)}
        {folder : Carpeta con los documentos Word de esa empresa}
        {--company= : ID de la empresa destino}
        {--dry-run : Muestra lo que se haría sin escribir en base de datos ni copiar archivos}';

    protected $description = 'Carga masiva de procedimientos antiguos (pre-existentes) hacia Procesos, marcados como solo-lectura (is_legacy) hasta que se actualicen.';

    private const COL_IMPACTO = 0;
    private const COL_EMPRESA = 1;
    private const COL_TIPO_PROCESO = 2;
    private const COL_TIPO_DOCUMENTO = 3;
    private const COL_ES_ANEXO = 4;
    private const COL_NOMBRE = 5;
    private const COL_CODIGO = 6;
    private const COL_ELABORADO_POR = 7;
    private const COL_APROBADO_POR = 8;
    private const COL_FECHA_EMISION = 9;
    private const COL_FECHA_VENCIMIENTO = 10;

    private const IMPACT_ALIASES = [
        'alto' => 'alto',
        'medio alto' => 'medio_alto',
        'medioalto' => 'medio_alto',
        'medio' => 'medio',
        'bajo' => 'bajo',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $csvPath = $this->resolvePath($this->argument('csv'));
        $folder = $this->resolvePath($this->argument('folder'));
        if (! $csvPath || ! $folder) {
            return self::FAILURE;
        }

        $company = $this->resolveCompany();
        if (! $company) {
            return self::FAILURE;
        }

        $uploader = User::whereIn('email', ['dev2.int@vigia.com.mx', 'admin@vigia.com.mx'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->first();

        if (! $uploader) {
            $this->error('No se encontró un usuario admin/superadmin para registrar como responsable de la carga.');
            return self::FAILURE;
        }

        $this->info("Empresa destino: {$company->name} (ID {$company->id}, grupo {$company->group_id})");

        $processTypes = ProcessType::where('group_id', $company->group_id)->where('is_active', true)->get();
        $files = $this->indexFolder($folder);

        $rows = $this->readCsv($csvPath);
        if (empty($rows)) {
            $this->warn('El CSV no trae filas de datos.');
            return self::SUCCESS;
        }

        $imported = 0;
        $skippedExisting = 0;
        $problems = [];

        DB::transaction(function () use (
            $rows,
            $company,
            $processTypes,
            $files,
            $uploader,
            $dryRun,
            &$imported,
            &$skippedExisting,
            &$problems,
        ) {
            foreach ($rows as $i => $row) {
                $lineNo = $i + 2; // +1 por índice base 0, +1 por la fila de encabezado
                $this->importRow($row, $lineNo, $company, $processTypes, $files, $uploader, $dryRun, $imported, $skippedExisting, $problems);
            }
        });

        $this->printSummary($imported, $skippedExisting, $problems, $dryRun);

        return self::SUCCESS;
    }

    private function importRow(
        array $row,
        int $lineNo,
        Company $company,
        \Illuminate\Support\Collection $processTypes,
        array $files,
        User $uploader,
        bool $dryRun,
        int &$imported,
        int &$skippedExisting,
        array &$problems,
    ): void {
        $code = trim($row[self::COL_CODIGO] ?? '');
        $name = trim($row[self::COL_NOMBRE] ?? '');

        if ($code === '' && $name === '' && trim(implode('', $row)) === '') {
            return; // fila vacía (separador), no es un error que valga la pena reportar
        }

        if ($code === '' || $name === '') {
            $problems[] = "Línea {$lineNo}: sin código o sin nombre, se omite.";
            return;
        }

        if (Regulation::where('company_id', $company->id)->where('code', Str::upper($code))->exists()) {
            $skippedExisting++;
            $problems[] = "Línea {$lineNo} [{$code}]: ya existe un documento con ese código en esta empresa, se omite.";
            return;
        }

        $processType = $this->matchProcessType($row[self::COL_TIPO_PROCESO] ?? '', $processTypes);
        if (! $processType) {
            $problems[] = "Línea {$lineNo} [{$code}]: \"TIPO DE PROCESO\" = \"{$row[self::COL_TIPO_PROCESO]}\" no coincide con ningún proceso del catálogo de este grupo, se omite.";
            return;
        }

        $documentType = $this->matchDocumentType($row[self::COL_TIPO_DOCUMENTO] ?? '');
        if (! $documentType) {
            $problems[] = "Línea {$lineNo} [{$code}]: \"TIPO DE DOCUMENTO\" = \"{$row[self::COL_TIPO_DOCUMENTO]}\" no coincide con ningún tipo válido (" . implode('/', Regulation::DOCUMENT_TYPES) . "), se omite.";
            return;
        }

        $isAnnex = $this->parseYesNo($row[self::COL_ES_ANEXO] ?? '');

        $match = $this->matchFile($code, $files);
        if (! $match) {
            $problems[] = "Línea {$lineNo} [{$code}]: no se encontró un archivo .docx/.doc/.pdf en la carpeta cuyo nombre empiece con ese código, se omite.";
            return;
        }
        if (count($match['candidates']) > 1) {
            $problems[] = "Línea {$lineNo} [{$code}]: varios archivos coinciden (" . implode(', ', $match['candidates']) . ") — se usó \"{$match['file']}\", revisa si es el correcto.";
        }

        $impactLevel = $this->matchImpact($row[self::COL_IMPACTO] ?? '');
        $elaboraPor = trim($row[self::COL_ELABORADO_POR] ?? '') ?: null;
        $apruebaPor = trim($row[self::COL_APROBADO_POR] ?? '') ?: null;
        $issuedAt = $this->parseDate($row[self::COL_FECHA_EMISION] ?? null);
        $validUntil = $this->parseDate($row[self::COL_FECHA_VENCIMIENTO] ?? null);

        if ($dryRun) {
            $this->line("  [nuevo] {$code} — {$name} ({$documentType}, {$processType->name}) <- {$match['file']}");
            $imported++;
            return;
        }

        $regulation = Regulation::create([
            'group_id' => $company->group_id,
            'company_id' => $company->id,
            'process_type_id' => $processType->id,
            'document_type' => $documentType,
            'is_annex' => $isAnnex,
            'is_legacy' => true,
            'code' => Str::upper($code),
            'name' => Str::upper($name),
            'details' => array_filter([
                'quien_elabora' => $elaboraPor,
                'quien_aprueba' => $apruebaPor,
                'fecha_vigencia' => $issuedAt?->toDateString(),
            ]),
            'is_active' => true,
            'created_by' => $uploader->id,
            'impact_level' => $impactLevel,
            // Sin flujo interno — mismo criterio que un documento "cargado con aprobación previa"
            // (ver RegulationController::storeCargar): ya viene aprobado del sistema anterior.
            'approval_status' => 'approved',
        ]);

        $directory = "regulations/{$company->id}/{$regulation->id}/versions";
        $storedName = 'v1_' . Str::slug(pathinfo($match['file'], PATHINFO_FILENAME)) . '.' . pathinfo($match['file'], PATHINFO_EXTENSION);
        $path = Storage::disk('private')->putFileAs($directory, new HttpFile($match['path']), $storedName);

        RegulationVersion::create([
            'regulation_id' => $regulation->id,
            'version_number' => 1,
            'change_description' => 'Documento antiguo — carga masiva desde el inventario previo al sistema.',
            'responsible_name' => $elaboraPor,
            'file_path' => $path,
            'original_name' => $match['file'],
            'disk' => 'private',
            'mime_type' => File::mimeType($match['path']) ?: 'application/octet-stream',
            'issued_at' => $issuedAt,
            'valid_until' => $validUntil,
            'is_current' => true,
            'uploaded_by' => $uploader->id,
        ]);

        $imported++;
    }

    /** @return array<string, array{path: string, ext: string}> archivos de la carpeta, indexados por nombre base en mayúsculas */
    private function indexFolder(string $folder): array
    {
        $allowedExt = ['docx', 'doc', 'pdf'];

        $indexed = [];
        foreach (File::allFiles($folder) as $file) {
            $ext = Str::lower($file->getExtension());
            if (! in_array($ext, $allowedExt, true)) {
                continue;
            }

            $indexed[] = [
                'name' => $file->getFilename(),
                'base' => Str::upper(pathinfo($file->getFilename(), PATHINFO_FILENAME)),
                'path' => $file->getPathname(),
                'ext' => $ext,
            ];
        }

        return $indexed;
    }

    /** @param array<int, array{name: string, base: string, path: string, ext: string}> $files */
    private function matchFile(string $code, array $files): ?array
    {
        $needle = Str::upper(trim($code));

        $candidates = array_values(array_filter(
            $files,
            fn ($f) => str_starts_with($f['base'], $needle)
        ));

        if (empty($candidates)) {
            return null;
        }

        // docx > doc > pdf si hay varios (p. ej. el mismo documento repetido con distinta extensión).
        $order = ['docx' => 0, 'doc' => 1, 'pdf' => 2];
        usort($candidates, fn ($a, $b) => $order[$a['ext']] <=> $order[$b['ext']]);

        return [
            'file' => $candidates[0]['name'],
            'path' => $candidates[0]['path'],
            'candidates' => array_map(fn ($c) => $c['name'], $candidates),
        ];
    }

    private function matchProcessType(string $value, \Illuminate\Support\Collection $processTypes): ?ProcessType
    {
        $needle = $this->normalize($value);
        if ($needle === '') {
            return null;
        }

        return $processTypes->first(fn (ProcessType $pt) => $this->normalize($pt->name) === $needle);
    }

    private function matchDocumentType(string $value): ?string
    {
        $needle = $this->normalize($value);

        foreach (Regulation::DOCUMENT_TYPES as $type) {
            if ($this->normalize($type) === $needle) {
                return $type;
            }
        }

        return null;
    }

    private function parseYesNo(string $value): bool
    {
        // normalize() ya quita acentos (Str::ascii) — "sí"/"Sí" llegan aquí como "si".
        return in_array($this->normalize($value), ['si', 'yes', 'x', '1', 'true'], true);
    }

    private function matchImpact(string $value): ?string
    {
        $needle = str_replace(['-', '_'], ' ', $this->normalize($value));

        return self::IMPACT_ALIASES[$needle] ?? null;
    }

    private function parseDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        foreach (['d-m-Y', 'd/m/Y', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->startOfDay();
            } catch (\Exception) {
                continue;
            }
        }

        try {
            return Carbon::parse($value)->startOfDay();
        } catch (\Exception) {
            return null;
        }
    }

    private function normalize(string $value): string
    {
        return Str::lower(trim(preg_replace('/\s+/u', ' ', Str::ascii($value))));
    }

    /** @return array<int, array<int, string>> filas de datos (sin encabezado), cada una indexada por posición de columna */
    private function readCsv(string $path): array
    {
        $content = preg_replace('/^\xEF\xBB\xBF/', '', file_get_contents($path));

        $firstLine = strtok($content, "\n");
        $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';

        $lines = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', $content),
            fn ($l) => trim($l) !== ''
        ));

        if (empty($lines)) {
            return [];
        }

        // Se descarta la fila de encabezado tal cual (dos columnas repiten "FECHA DE" — no sirve
        // para mapear por nombre, ver el bloque de constantes COL_* al inicio del archivo).
        array_shift($lines);

        return array_map(fn ($line) => str_getcsv($line, $delimiter), $lines);
    }

    private function resolvePath(string $input): ?string
    {
        $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $input) === 1;
        $path = rtrim($isAbsolute ? $input : base_path($input), '/\\');

        if (! file_exists($path)) {
            $this->error("No existe: {$path}");
            return null;
        }

        return $path;
    }

    private function resolveCompany(): ?Company
    {
        $companyId = $this->option('company');
        if (! $companyId) {
            $this->error('Falta indicar --company=ID (una empresa por corrida).');
            return null;
        }

        $company = Company::find($companyId);
        if (! $company) {
            $this->error("No existe una empresa con ID {$companyId}.");
            return null;
        }

        return $company;
    }

    private function printSummary(int $imported, int $skippedExisting, array $problems, bool $dryRun): void
    {
        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Documentos importados: {$imported}. Ya existentes (omitidos): {$skippedExisting}.");

        if (! empty($problems)) {
            $this->warn('Filas con observaciones:');
            foreach ($problems as $p) {
                $this->line("  - {$p}");
            }
        }
    }
}
