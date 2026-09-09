<?php

namespace App\Console\Commands;

use App\Enums\RequirementStatus;
use App\Models\Asset;
use App\Models\AssetRequirement;
use App\Models\AssetRequirementDocument;
use App\Models\RequirementTemplate;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Http\File as HttpFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportRequirementDocuments extends Command
{
    protected $signature = 'requirements:import-documents
        {folder : Carpeta con los documentos a importar (ruta absoluta o relativa al proyecto)}
        {--asset= : ID del activo destino (si se omite, se detecta a partir del nombre de la carpeta)}
        {--dry-run : Muestra lo que se haría sin escribir en base de datos ni copiar archivos}';

    protected $description = 'Carga masiva de documentos de un activo hacia sus requerimientos, marcando la versión más reciente por año/semestre como vigente y el resto como histórico. Si la carpeta trae un .csv con columnas Documento/Fecha de Emisión/Fecha de Vigencia, también actualiza esas fechas en el requerimiento.';

    private const SEMESTER_PATTERN = '/\s+(\d)(?:er|do|to)?\.?\s*sem\.?\s*((?:19|20)\d{2})\s*$/iu';

    private const YEAR_PATTERN = '/\s+((?:19|20)\d{2})\s*$/u';

    private const PAREN_SUFFIX_PATTERN = '/\s*\([^)]*\)\s*$/u';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $folder = $this->resolveFolder($this->argument('folder'));
        if (! $folder) {
            return self::FAILURE;
        }

        $asset = $this->resolveAsset($folder);
        if (! $asset) {
            return self::FAILURE;
        }

        $uploader = User::whereIn('email', ['dev2.int@vigia.com.mx', 'admin@vigia.com.mx'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->first();

        if (! $uploader) {
            $this->error('No se encontró un usuario admin/superadmin para registrar como responsable de la carga.');
            return self::FAILURE;
        }

        $this->info("Activo destino: {$asset->name} (ID {$asset->id}, código {$asset->code}) — empresa #{$asset->company_id}");

        $catalog = $this->buildCatalog($asset->asset_type_id);
        $catalog = $this->applyAliases($catalog, $asset->assetType?->name);

        [$groups, $unmatched] = $this->scanFolder($folder, $catalog, $asset);

        if (empty($groups)) {
            $this->warn('No se encontró ningún archivo que coincida con el catálogo de requerimientos de este activo.');
        }

        $imported = 0;
        $skippedExisting = 0;
        $missingAssetRequirement = [];
        $datesApplied = 0;
        $datesMissingAssetRequirement = [];
        $unmatchedDates = [];

        DB::transaction(function () use (
            $groups,
            $asset,
            $uploader,
            $dryRun,
            $folder,
            $catalog,
            &$imported,
            &$skippedExisting,
            &$missingAssetRequirement,
            &$datesApplied,
            &$datesMissingAssetRequirement,
            &$unmatchedDates,
        ) {
            foreach ($groups as $group) {
                $this->importGroup(
                    $group,
                    $asset,
                    $uploader,
                    $dryRun,
                    $imported,
                    $skippedExisting,
                    $missingAssetRequirement,
                );
            }

            $this->applyDatesFromCsv(
                $folder,
                $catalog,
                $asset,
                $dryRun,
                $datesApplied,
                $datesMissingAssetRequirement,
                $unmatchedDates,
            );
        });

        $this->printSummary(
            $imported,
            $skippedExisting,
            $missingAssetRequirement,
            $unmatched,
            $dryRun,
            $datesApplied,
            $datesMissingAssetRequirement,
            $unmatchedDates,
        );

        return self::SUCCESS;
    }

    private function importGroup(
        array $group,
        Asset $asset,
        User $uploader,
        bool $dryRun,
        int &$imported,
        int &$skippedExisting,
        array &$missingAssetRequirement,
    ): void {
        $template = $group['template'];

        $assetRequirement = AssetRequirement::where('asset_id', $asset->id)
            ->where('requirement_template_id', $template->id)
            ->first();

        if (! $assetRequirement) {
            $missingAssetRequirement[] = $template->name;
            return;
        }

        $ordered = $this->orderFiles($group['files']);
        $lastIndex = count($ordered) - 1;

        $existingByName = AssetRequirementDocument::where('asset_requirement_id', $assetRequirement->id)
            ->get()
            ->keyBy('original_name');

        $nextVersion = (int) $existingByName->max('version_number');

        $docs = [];

        foreach ($ordered as $index => $fileInfo) {
            $isCurrent = $index === $lastIndex;
            $existing = $existingByName->get($fileInfo['original_name']);

            if ($existing) {
                $desiredStatus = $isCurrent ? 'active' : 'replaced';

                if ($dryRun) {
                    $this->line("  [existente] {$fileInfo['original_name']} -> " . ($isCurrent ? 'VIGENTE' : 'histórico'));
                } elseif ($existing->is_current !== $isCurrent || $existing->status !== $desiredStatus) {
                    $existing->update(['is_current' => $isCurrent, 'status' => $desiredStatus]);
                }

                $docs[] = $existing;
                $skippedExisting++;
                continue;
            }

            $nextVersion++;

            if ($dryRun) {
                $label = $fileInfo['year']
                    ? $fileInfo['year'] . ($fileInfo['semester'] ? " (semestre {$fileInfo['semester']})" : '')
                    : 'sin período detectado';
                $this->line("  [nuevo] {$fileInfo['original_name']} -> requerimiento \"{$template->name}\", versión {$nextVersion}, {$label}, " . ($isCurrent ? 'VIGENTE' : 'histórico'));
                $imported++;
                continue;
            }

            $directory = "companies/{$asset->company_id}/requirements/{$assetRequirement->id}/official-documents";
            $storedName = now()->format('Ymd_His') . '_' . uniqid() . '_' . $fileInfo['original_name'];
            $path = Storage::disk('private')->putFileAs($directory, new HttpFile($fileInfo['path']), $storedName);

            $note = $fileInfo['year']
                ? "Importado por carga masiva. Periodo detectado en el nombre del archivo: {$fileInfo['year']}" . ($fileInfo['semester'] ? " (semestre {$fileInfo['semester']})." : '.')
                : 'Importado por carga masiva. Sin período detectado en el nombre del archivo' . ($fileInfo['usedMtimeFallback'] ? ', orden inferido por fecha de modificación.' : '.');

            $docs[] = AssetRequirementDocument::create([
                'company_id' => $asset->company_id,
                'asset_requirement_id' => $assetRequirement->id,
                'file_path' => $path,
                'original_name' => $fileInfo['original_name'],
                'mime_type' => File::mimeType($fileInfo['path']) ?: 'application/octet-stream',
                'size' => filesize($fileInfo['path']),
                'uploaded_by' => $uploader->id,
                'uploaded_at' => now(),
                'is_current' => $isCurrent,
                'status' => $isCurrent ? 'active' : 'replaced',
                'version_number' => $nextVersion,
                'notes' => $note,
            ]);

            $imported++;
        }

        if ($dryRun) {
            return;
        }

        for ($i = 0; $i < count($docs) - 1; $i++) {
            if ($docs[$i]->replaced_by_document_id !== $docs[$i + 1]->id) {
                $docs[$i]->update(['replaced_by_document_id' => $docs[$i + 1]->id]);
            }
        }

        $currentDoc = end($docs) ?: null;

        if ($currentDoc) {
            $assetRequirement->update([
                'status' => RequirementStatus::COMPLETED,
                'completed_at' => $assetRequirement->completed_at ?? now(),
                'current_document_id' => $currentDoc->id,
            ]);
        }
    }

    /** @return array{0: array<int, array{template: RequirementTemplate, files: array}>, 1: array<int, string>} */
    private function scanFolder(string $folder, array $catalog, Asset $asset): array
    {
        $groups = [];
        $unmatched = [];

        $files = collect(File::allFiles($folder))
            ->reject(fn ($f) => str_starts_with($f->getFilename(), '.'))
            ->reject(fn ($f) => Str::lower($f->getExtension()) === 'csv');

        foreach ($files as $file) {
            $baseName = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            $match = $this->matchFile($baseName, $catalog, $asset);

            if (! $match) {
                $unmatched[] = $file->getRelativePathname();
                continue;
            }

            $templateId = $match['template']->id;
            $groups[$templateId]['template'] = $match['template'];
            $groups[$templateId]['files'][] = [
                'path' => $file->getPathname(),
                'original_name' => $file->getFilename(),
                'year' => $match['year'],
                'semester' => $match['semester'],
                'mtime' => $file->getMTime(),
                'usedMtimeFallback' => false,
            ];
        }

        foreach ($groups as &$group) {
            $hasMissingYear = collect($group['files'])->contains(fn ($f) => $f['year'] === null);
            $hasAnyYear = collect($group['files'])->contains(fn ($f) => $f['year'] !== null);

            if ($hasMissingYear && $hasAnyYear && count($group['files']) > 1) {
                foreach ($group['files'] as &$fileInfo) {
                    if ($fileInfo['year'] === null) {
                        $fileInfo['usedMtimeFallback'] = true;
                    }
                }
                unset($fileInfo);
            }
        }
        unset($group);

        return [$groups, $unmatched];
    }

    private function matchFile(string $baseName, array $catalog, Asset $asset): ?array
    {
        $baseName = trim(preg_replace(self::PAREN_SUFFIX_PATTERN, '', $baseName));
        $baseName = $this->stripAssetSuffix($baseName, $asset);

        $normalizedFull = $this->normalize($baseName);
        if (isset($catalog[$normalizedFull])) {
            return ['template' => $catalog[$normalizedFull], 'year' => null, 'semester' => null];
        }

        if (preg_match(self::SEMESTER_PATTERN, $baseName, $m)) {
            $stripped = trim(preg_replace(self::SEMESTER_PATTERN, '', $baseName, 1));
            $normalizedStripped = $this->normalize($stripped);
            if (isset($catalog[$normalizedStripped])) {
                return ['template' => $catalog[$normalizedStripped], 'year' => (int) $m[2], 'semester' => (int) $m[1]];
            }
        }

        if (preg_match(self::YEAR_PATTERN, $baseName, $m)) {
            $stripped = trim(preg_replace(self::YEAR_PATTERN, '', $baseName, 1));
            $normalizedStripped = $this->normalize($stripped);
            if (isset($catalog[$normalizedStripped])) {
                return ['template' => $catalog[$normalizedStripped], 'year' => (int) $m[1], 'semester' => null];
            }
        }

        return null;
    }

    /**
     * Quita un sufijo final "- <nombre del activo>" (opcionalmente precedido por el nombre del
     * tipo de activo, p. ej. "- ES Linares 3") que el proveedor a veces agrega al nombre del
     * archivo pero que el catálogo de requerimientos ni el CSV de vigencias traen.
     */
    private function stripAssetSuffix(string $baseName, Asset $asset): string
    {
        $pos = strrpos($baseName, ' - ');
        if ($pos === false) {
            return $baseName;
        }

        $suffix = $this->normalize(substr($baseName, $pos + 3));

        $candidates = [$this->normalize($asset->name)];
        if ($typeName = $asset->assetType?->name) {
            $candidates[] = $this->normalize($typeName . ' ' . $asset->name);
        }

        // Tolera singular/plural entre el nombre del activo y el que trae el archivo
        // (p. ej. archivo "ES Crucero" vs activo "CRUCEROS"): compara también quitando
        // una "s" final de ambos lados.
        $matches = in_array($suffix, $candidates, true)
            || in_array(rtrim($suffix, 's'), array_map(fn ($c) => rtrim($c, 's'), $candidates), true);

        return $matches ? trim(substr($baseName, 0, $pos)) : $baseName;
    }

    private function orderFiles(array $files): array
    {
        usort($files, fn ($a, $b) => $this->sortKey($a) <=> $this->sortKey($b));

        return $files;
    }

    private function sortKey(array $file): array
    {
        $year = $file['year'] ?? (int) date('Y', $file['mtime']);
        $semester = $file['semester'] ?? 0;

        return [$year, $semester, $file['mtime']];
    }

    /** @return array<string, RequirementTemplate> */
    private function buildCatalog(int $assetTypeId): array
    {
        $catalog = [];

        RequirementTemplate::where('asset_type_id', $assetTypeId)
            ->orderByRaw("CASE WHEN category = 'expediente' THEN 0 ELSE 1 END")
            ->get()
            ->each(function (RequirementTemplate $template) use (&$catalog) {
                $key = $this->normalize($template->name);
                $catalog[$key] ??= $template;
            });

        return $catalog;
    }

    /** @param array<string, RequirementTemplate> $catalog */
    private function applyAliases(array $catalog, ?string $assetTypeName): array
    {
        if (! $assetTypeName) {
            return $catalog;
        }

        $path = database_path('seeders/data/requirement_document_aliases.php');
        if (! is_file($path)) {
            return $catalog;
        }

        $aliases = require $path;
        $aliasesForType = $aliases[$assetTypeName] ?? [];

        foreach ($aliasesForType as $aliasText => $canonicalName) {
            $canonicalKey = $this->normalize($canonicalName);

            if (! isset($catalog[$canonicalKey])) {
                $this->warn("Alias mal configurado: \"{$canonicalName}\" no existe en el catálogo de {$assetTypeName} (revisar database/seeders/data/requirement_document_aliases.php).");
                continue;
            }

            $catalog[$this->normalize($aliasText)] = $catalog[$canonicalKey];
        }

        return $catalog;
    }

    /**
     * Busca un .csv (Documento, Fecha de Emisión, Fecha de Vigencia) directo en la carpeta de la
     * estación y aplica esas fechas a issued_at/expires_at del requerimiento correspondiente.
     * Cuando el mismo documento aparece varias veces con distinto año (una fila por entrega anual),
     * se queda con la fila del año más reciente — igual criterio que ya usa importGroup() con archivos.
     */
    private function applyDatesFromCsv(
        string $folder,
        array $catalog,
        Asset $asset,
        bool $dryRun,
        int &$datesApplied,
        array &$datesMissingAssetRequirement,
        array &$unmatchedDates,
    ): void {
        $csvFiles = collect(File::files($folder))
            ->filter(fn ($f) => Str::lower($f->getExtension()) === 'csv');

        foreach ($csvFiles as $csvFile) {
            $rows = $this->readCsv($csvFile->getPathname());

            $byTemplate = [];

            foreach ($rows as $row) {
                $documento = trim($row['documento'] ?? '');
                if ($documento === '') {
                    continue;
                }

                $match = $this->matchFile($documento, $catalog, $asset);

                if (! $match) {
                    $unmatchedDates[] = $documento;
                    continue;
                }

                $templateId = $match['template']->id;
                $year = $match['year'] ?? 0;

                if (! isset($byTemplate[$templateId]) || $year >= $byTemplate[$templateId]['year']) {
                    $byTemplate[$templateId] = [
                        'template' => $match['template'],
                        'year' => $year,
                        'issued_at' => $this->parseCsvDate($row['fecha de emision'] ?? null),
                        'expires_at' => $this->parseCsvDate($row['fecha de vigencia'] ?? null),
                    ];
                }
            }

            foreach ($byTemplate as $data) {
                $template = $data['template'];

                $assetRequirement = AssetRequirement::where('asset_id', $asset->id)
                    ->where('requirement_template_id', $template->id)
                    ->first();

                if (! $assetRequirement) {
                    $datesMissingAssetRequirement[] = $template->name;
                    continue;
                }

                if ($dryRun) {
                    $this->line(sprintf(
                        '  [fecha] %s -> emisión: %s, vigencia: %s',
                        $template->name,
                        $data['issued_at']?->format('d-m-Y') ?? '—',
                        $data['expires_at']?->format('d-m-Y') ?? '—',
                    ));
                    $datesApplied++;
                    continue;
                }

                $assetRequirement->update([
                    'issued_at' => $data['issued_at'],
                    'expires_at' => $data['expires_at'],
                ]);

                $datesApplied++;
            }
        }
    }

    /** @return array<int, array<string, string|null>> filas con claves normalizadas del encabezado */
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

        $header = array_map(
            fn ($h) => $this->normalize(trim($h)),
            str_getcsv(array_shift($lines), $delimiter)
        );

        $rows = [];
        foreach ($lines as $line) {
            $values = str_getcsv($line, $delimiter);
            $values = array_slice(array_pad($values, count($header), null), 0, count($header));
            $rows[] = array_combine($header, $values);
        }

        return $rows;
    }

    private function parseCsvDate(?string $value): ?Carbon
    {
        $value = trim((string) $value);

        if ($value === '' || $this->normalize($value) === 'no especificado') {
            return null;
        }

        try {
            return Carbon::createFromFormat('d-m-Y', $value)->startOfDay();
        } catch (\Exception) {
            try {
                return Carbon::parse($value)->startOfDay();
            } catch (\Exception) {
                return null;
            }
        }
    }

    private function normalize(string $value): string
    {
        // Str::ascii quita acentos (Análisis -> Analisis) — el catálogo y los nombres
        // entregados no siempre coinciden en acentuación, y no queremos que eso rompa el match.
        return Str::lower(trim(preg_replace('/\s+/u', ' ', Str::ascii($value))));
    }

    private function normalizeForMatching(string $value): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]+/u', '', $value));
    }

    private function resolveFolder(string $input): ?string
    {
        $isAbsolute = preg_match('#^([A-Za-z]:[\\\\/]|/)#', $input) === 1;
        $path = rtrim($isAbsolute ? $input : base_path($input), '/\\');

        if (! is_dir($path)) {
            $this->error("No existe la carpeta: {$path}");
            return null;
        }

        return $path;
    }

    private function resolveAsset(string $folder): ?Asset
    {
        if ($assetId = $this->option('asset')) {
            $asset = Asset::find($assetId);
            if (! $asset) {
                $this->error("No existe un activo con ID {$assetId}.");
                return null;
            }
            return $asset;
        }

        $folderName = basename($folder);
        $normalizedFolder = $this->normalizeForMatching($folderName);

        $candidates = Asset::all()->filter(function (Asset $asset) use ($normalizedFolder) {
            $normalizedName = $this->normalizeForMatching($asset->name);
            if ($normalizedName === '' || ! str_contains($normalizedFolder, $normalizedName)) {
                return false;
            }

            $normalizedCode = $asset->code ? $this->normalizeForMatching($asset->code) : '';
            if ($normalizedCode !== '' && ! str_contains($normalizedFolder, $normalizedCode)) {
                return false;
            }

            return true;
        });

        if ($candidates->count() === 1) {
            return $candidates->first();
        }

        if ($candidates->isEmpty()) {
            $this->error("No se pudo detectar el activo a partir del nombre de carpeta \"{$folderName}\". Usa --asset=ID para indicarlo manualmente.");
        } else {
            $list = $candidates->map(fn ($a) => "#{$a->id} {$a->name}")->implode(', ');
            $this->error("El nombre de carpeta \"{$folderName}\" coincide con varios activos ({$list}). Usa --asset=ID para desambiguar.");
        }

        return null;
    }

    private function printSummary(
        int $imported,
        int $skippedExisting,
        array $missingAssetRequirement,
        array $unmatched,
        bool $dryRun,
        int $datesApplied = 0,
        array $datesMissingAssetRequirement = [],
        array $unmatchedDates = [],
    ): void {
        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Documentos procesados: {$imported}. Ya existentes (sin cambios): {$skippedExisting}. Fechas aplicadas: {$datesApplied}.");

        if (! empty($missingAssetRequirement)) {
            $this->warn('Se encontraron archivos para requerimientos del catálogo que este activo no tiene registrados (corre antes el sync de requerimientos):');
            foreach (array_unique($missingAssetRequirement) as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($unmatched)) {
            $this->warn('Archivos sin coincidencia exacta en el catálogo de requerimientos (revisar nombre manualmente):');
            foreach ($unmatched as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($datesMissingAssetRequirement)) {
            $this->warn('El CSV trae fechas para requerimientos del catálogo que este activo no tiene registrados (corre antes el sync de requerimientos):');
            foreach (array_unique($datesMissingAssetRequirement) as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($unmatchedDates)) {
            $this->warn('Filas del CSV cuyo "Documento" no coincide con ningún requerimiento del catálogo (revisar nombre o agregar alias en requirement_document_aliases.php):');
            foreach (array_unique($unmatchedDates) as $name) {
                $this->line("  - {$name}");
            }
        }
    }
}
