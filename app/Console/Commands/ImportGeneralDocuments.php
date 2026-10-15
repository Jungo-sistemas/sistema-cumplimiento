<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImportGeneralDocuments extends Command
{
    protected $signature = 'documents:import-general
        {folder : Carpeta con el .csv y los archivos a importar (ruta absoluta o relativa al proyecto)}
        {--csv= : Nombre del archivo .csv específico a usar, dentro de la carpeta (si se omite, se usan todos los .csv que haya ahí)}
        {--company= : ID de la empresa destino. Si se omite, los documentos quedan como "generales" del grupo (sin empresa específica) — en ese caso usa --group= para indicar el grupo.}
        {--group= : ID del grupo destino (solo necesario si se omite --company; con --company el grupo se deduce solo)}
        {--dry-run : Muestra lo que se haría sin escribir en base de datos ni copiar archivos}';

    protected $description = 'Carga masiva de Documentos generales (escrituras, poderes, actas, etc.) a partir de un .csv '
        . '(columnas Escritura/Código, Descripción, Fecha, Tipo, Bodega, Responsable, Accesos) y los archivos reales en '
        . 'la misma carpeta, emparejados por el número de escritura/código que aparece en el nombre del archivo.';

    // Número con separador de miles (ej. "2,378", "39,870") O sin él (ej. "16761") — se intenta
    // primero la forma con comas (si no, un "11111" sin coma solo tomaría "111", los primeros 3
    // dígitos). El código de escritura siempre es el PRIMER número que aparece en el nombre del
    // archivo (antes de cualquier otro número en la descripción, como "11.1 Has.").
    private const CODE_PATTERN = '/\d{1,3}(?:,\d{3})+|\d+/';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $folder = $this->resolveFolder($this->argument('folder'));
        if (! $folder) {
            return self::FAILURE;
        }

        $scope = $this->resolveScope();
        if ($scope === null) {
            return self::FAILURE;
        }

        [$groupId, $companyId] = $scope;

        $uploader = User::whereIn('email', ['dev2.int@vigia.com.mx', 'admin@vigia.com.mx'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->first();

        if (! $uploader) {
            $this->error('No se encontró un usuario admin/superadmin para registrar como responsable de la carga.');
            return self::FAILURE;
        }

        // --csv=archivo.csv usa SOLO ese archivo (más simple y predecible si vas subiendo las
        // tablas una por una); sin esa opción, se admite más de un .csv en la misma carpeta — un
        // Excel con varias hojas/tablas (Escrituras, Poderes, Actas, etc.) exportado como un .csv
        // por hoja — y se juntan las filas de todos, emparejadas contra el mismo conjunto de
        // archivos de la carpeta.
        if ($csvName = $this->option('csv')) {
            $csvPath = $folder . DIRECTORY_SEPARATOR . ltrim($csvName, '/\\');

            if (! is_file($csvPath)) {
                $this->error("No existe el archivo \"{$csvName}\" dentro de: {$folder}");
                return self::FAILURE;
            }

            $csvFiles = collect([new \SplFileInfo($csvPath)]);
        } else {
            $csvFiles = collect(File::files($folder))->filter(fn ($f) => Str::lower($f->getExtension()) === 'csv');
        }

        if ($csvFiles->isEmpty()) {
            $this->error("No se encontró ningún archivo .csv directo en: {$folder}");
            return self::FAILURE;
        }

        $rows = [];
        foreach ($csvFiles as $csvFile) {
            $fileRows = $this->readCsv($csvFile->getPathname());
            $this->line("  {$csvFile->getFilename()}: " . count($fileRows) . ' fila(s)');
            $rows = array_merge($rows, $fileRows);
        }

        if (empty($rows)) {
            $this->error('Ninguno de los .csv trae filas (o no se pudieron leer).');
            return self::FAILURE;
        }

        $this->info('Grupo #' . $groupId . ($companyId ? " — empresa #{$companyId}" : ' — documentos generales (sin empresa específica)'));
        $this->info('Archivos .csv encontrados: ' . $csvFiles->count() . ' — total de filas: ' . count($rows));

        // Archivos reales de la carpeta (todo menos el propio .csv). $filesByCode agrupa los que
        // sí traen un código de escritura detectable en el nombre (la vía normal de emparejar);
        // $allFiles los junta TODOS (con o sin código) para la columna opcional "Archivo" — para
        // documentos sin número de escritura (convenios, registros, permisos) que no tienen con
        // qué emparejar más que el nombre del archivo tal cual.
        $filesByCode = [];
        $filesWithoutCode = [];
        $allFiles = [];

        foreach (File::allFiles($folder) as $file) {
            if (str_starts_with($file->getFilename(), '.') || Str::lower($file->getExtension()) === 'csv') {
                continue;
            }

            $allFiles[] = $file;
            $code = $this->extractCode($file->getFilename());

            if ($code === null) {
                $filesWithoutCode[] = $file->getFilename();
                continue;
            }

            $filesByCode[$code][] = $file;
        }

        $created = 0;
        $skippedExisting = 0;
        $rowsWithoutFile = [];
        $accessWarnings = [];
        $typeWarnings = [];
        $usedFilenames = [];

        DB::transaction(function () use (
            $rows,
            $filesByCode,
            $allFiles,
            $groupId,
            $companyId,
            $uploader,
            $dryRun,
            &$created,
            &$skippedExisting,
            &$rowsWithoutFile,
            &$accessWarnings,
            &$typeWarnings,
            &$usedFilenames,
        ) {
            foreach ($rows as $row) {
                $this->importRow(
                    $row,
                    $filesByCode,
                    $allFiles,
                    $groupId,
                    $companyId,
                    $uploader,
                    $dryRun,
                    $created,
                    $skippedExisting,
                    $rowsWithoutFile,
                    $accessWarnings,
                    $typeWarnings,
                    $usedFilenames,
                );
            }
        });

        // Lo que de verdad nunca se usó en ningún documento, filtrando los ya emparejados por la
        // columna "Archivo" (que pueden venir tanto de $filesByCode como de $filesWithoutCode).
        $filesWithoutCode = array_values(array_diff($filesWithoutCode, $usedFilenames));
        $unusedFileCodes = [];
        foreach ($filesByCode as $code => $files) {
            $remaining = array_filter($files, fn ($f) => ! in_array($f->getFilename(), $usedFilenames, true));
            if (! empty($remaining)) {
                $unusedFileCodes[$code] = $remaining;
            }
        }

        $this->printSummary(
            $created,
            $skippedExisting,
            $rowsWithoutFile,
            $filesWithoutCode,
            array_keys($unusedFileCodes),
            $unusedFileCodes,
            $accessWarnings,
            $typeWarnings,
            $dryRun,
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<string, string>  $row  fila ya normalizada (claves en minúsculas) del CSV
     * @param  array<string, array<int, \SplFileInfo>>  $filesByCode
     * @param  array<int, \SplFileInfo>  $allFiles
     */
    private function importRow(
        array $row,
        array $filesByCode,
        array $allFiles,
        int $groupId,
        ?int $companyId,
        User $uploader,
        bool $dryRun,
        int &$created,
        int &$skippedExisting,
        array &$rowsWithoutFile,
        array &$accessWarnings,
        array &$typeWarnings,
        array &$usedFilenames,
    ): void {
        $rawCode     = $this->findColumn($row, 'escritura', 'codigo', 'código');
        // 'escrituras' como candidato tolera encabezados tipo "ESCRITURAS TERRENOS" o
        // "ESCRITURAS CORPORATIVAS - DESCRIPCIÓN" (el título de la tabla original pegado al
        // nombre de columna) cuando no traen literalmente "descripción" ni "nombre". Cuando el
        // título ni siquiera menciona "escrituras" (ej. "CONTRATOS, CONVENIOS Y ACUERDOS"), se usa
        // como último recurso la columna que esté justo antes de "Fecha" — en todos los Excel de
        // este origen la descripción larga siempre cae ahí, tenga o no columna Archivo antes.
        $name = $this->findColumn($row, 'descripcion', 'descripción', 'nombre', 'escrituras');
        if ($name === '') {
            $name = $this->findColumnBeforeFecha($row);
        }
        $archivoHint = $this->findColumn($row, 'archivo', 'documento');

        if ($rawCode === '' && $name === '' && $archivoHint === '') {
            return;
        }

        // La columna "Archivo" (si viene) manda sobre el código de escritura — está pensada para
        // documentos SIN número de escritura (convenios, registros, permisos), donde no hay nada
        // más con qué emparejar que el nombre del archivo mismo. Si no viene, se usa el código
        // como siempre.
        if ($archivoHint !== '') {
            $files = $this->findFilesByHint($archivoHint, $allFiles, $usedFilenames);
        } else {
            $code  = $this->extractCode($rawCode) ?? $rawCode;
            $files = $filesByCode[$code] ?? [];

            // Sin escritura y sin columna Archivo, la descripción es lo único que queda —  pasa
            // muy seguido que el texto de la descripción ya es casi idéntico al nombre del
            // archivo, así que se intenta emparejar con ella como último recurso.
            if (empty($files) && $rawCode === '') {
                $files = $this->findFilesByHint($name, $allFiles, $usedFilenames);
            }
        }

        // La referencia que identifica al documento (y evita duplicarlo si se vuelve a correr el
        // comando): el código de escritura si hay, si no el texto de "Archivo", si no un pedazo de
        // la descripción — así hasta un documento sin escritura ni columna Archivo queda con algo
        // estable para no crearse dos veces.
        $reference = $rawCode !== '' ? $rawCode : ($archivoHint !== '' ? $archivoHint : Str::limit($name, 100, ''));

        if (empty($files)) {
            $rowsWithoutFile[] = ($rawCode !== '' ? $rawCode : ($archivoHint !== '' ? "Archivo: {$archivoHint}" : '(sin escritura ni archivo)')) . " — {$name}";
            return;
        }

        foreach ($files as $f) {
            $usedFilenames[] = $f->getFilename();
        }

        // Ya existe un documento con esta misma referencia en esta empresa/grupo — no se duplica
        // (permite volver a correr el comando sin riesgo tras corregir el CSV o agregar archivos).
        $existingQuery = Document::where('group_id', $groupId)->where('reference', $reference);
        $existingQuery = $companyId ? $existingQuery->where('company_id', $companyId) : $existingQuery->whereNull('company_id');

        if ($existingQuery->exists()) {
            $skippedExisting++;
            if ($dryRun) {
                $this->line("  [existente] {$reference} — {$name}");
            }
            return;
        }

        $documentType = $this->matchDocumentType($this->findColumn($row, 'tipo'), $reference, $typeWarnings);
        $bodega       = $this->findColumn($row, 'bodega') ?: null;
        $responsable  = $this->findColumn($row, 'responsable') ?: null;
        $issuedAt     = $this->parseCsvDate($this->findColumn($row, 'fecha'));

        $authorizedUserIds = $this->resolveAccessUserIds($this->findColumn($row, 'accesos', 'acceso'), $groupId, $reference, $accessWarnings);

        // El nombre del documento es el del archivo (sin extensión) y no la descripción larga del
        // CSV — esa va aparte en "description" para no saturar la lista de documentos. Si hay
        // varios archivos, se usa el más antiguo (el mismo que queda como primera versión).
        $ordered = collect($files)->sortBy(fn ($f) => $f->getMTime())->values();
        $shortName = pathinfo($ordered->first()->getFilename(), PATHINFO_FILENAME);

        if ($dryRun) {
            $fileNames = collect($files)->map(fn ($f) => $f->getFilename())->implode(' | ');
            $this->line("  [nuevo] {$reference} — {$shortName} (tipo: {$documentType}, " . count($files) . ' archivo(s): ' . $fileNames . ')');
            $created++;
            return;
        }

        $document = Document::create([
            'group_id'         => $groupId,
            'company_id'       => $companyId,
            'document_folder_id' => null,
            'name'             => Str::upper($shortName),
            'description'      => $name,
            'document_type'    => $documentType,
            'reference'        => $reference,
            'bodega'           => $bodega,
            'responsible_name' => $responsable,
            'is_required'      => true,
            'is_active'        => true,
            'uploaded_by'      => $uploader->id,
        ]);

        if (! empty($authorizedUserIds)) {
            $document->authorizedUsers()->sync($authorizedUserIds);
        }

        foreach ($ordered as $index => $fileInfo) {
            $directory = "documents/{$document->company_id}/{$document->id}/versions";
            $storedName = now()->format('Ymd_His') . '_' . uniqid() . '_' . $fileInfo->getFilename();
            $path = Storage::disk('private')->putFileAs(
                $directory,
                new \Illuminate\Http\File($fileInfo->getPathname()),
                $storedName,
            );

            DocumentVersion::create([
                'document_id'    => $document->id,
                'version_number' => $index + 1,
                // Un documento puede tener varios archivos vigentes a la vez (ej. la escritura y
                // su anexo), así que todos los que llegaron emparejados a esta fila quedan vigentes.
                'is_current'     => true,
                'file_path'      => $path,
                'original_name'  => $fileInfo->getFilename(),
                'mime_type'      => File::mimeType($fileInfo->getPathname()) ?: 'application/octet-stream',
                'file_size'      => $fileInfo->getSize(),
                'issued_at'      => $issuedAt,
                'uploaded_by'    => $uploader->id,
            ]);
        }

        $created++;
    }

    /**
     * Busca, entre TODOS los archivos de la carpeta, el (o los) que coincidan con lo que haya en
     * la columna "Archivo" — pensada para filas sin número de escritura (convenios, registros,
     * permisos), donde el único dato con el que emparejar es el nombre del archivo mismo. No hace
     * falta el nombre completo ni exacto: basta con que el nombre del archivo CONTENGA el texto de
     * la columna, o viceversa (sin importar mayúsculas/acentos), para tolerar que se haya escrito
     * una versión abreviada.
     *
     * @param  array<int, \SplFileInfo>  $allFiles
     * @param  array<int, string>  $usedFilenames
     * @return array<int, \SplFileInfo>
     */
    private function findFilesByHint(string $hint, array $allFiles, array $usedFilenames): array
    {
        $normalizedHint = Str::of($hint)->ascii()->lower()->trim()->value();

        if ($normalizedHint === '') {
            return [];
        }

        return array_values(array_filter($allFiles, function ($file) use ($normalizedHint, $usedFilenames) {
            if (in_array($file->getFilename(), $usedFilenames, true)) {
                return false;
            }

            $normalizedName = Str::of(pathinfo($file->getFilename(), PATHINFO_FILENAME))->ascii()->lower()->value();

            return str_contains($normalizedName, $normalizedHint) || str_contains($normalizedHint, $normalizedName);
        }));
    }

    /**
     * Busca una columna por nombre tolerando que el encabezado real no sea exactamente el
     * esperado — primero intenta coincidencia exacta con cada candidato, y si ninguna aparece tal
     * cual (p. ej. el título de la tabla del Excel original quedó pegado al nombre de la columna,
     * como "ESCRITURAS CORPORATIVAS - DESCRIPCIÓN" en vez de solo "DESCRIPCIÓN"), busca la primera
     * columna cuyo nombre CONTENGA alguno de los candidatos.
     *
     * @param  array<string, string|null>  $row
     */
    private function findColumn(array $row, string ...$candidates): string
    {
        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $row)) {
                return trim((string) $row[$candidate]);
            }
        }

        foreach ($row as $key => $value) {
            foreach ($candidates as $candidate) {
                if (str_contains($key, $candidate)) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    /**
     * Último recurso para encontrar la columna de descripción cuando su encabezado no contiene
     * ninguna palabra reconocible (ej. "CONTRATOS, CONVENIOS Y ACUERDOS"): toma la columna
     * inmediatamente anterior a "Fecha", que en todos los Excel de este origen es siempre la
     * descripción larga, exista o no una columna Archivo antes.
     *
     * @param  array<string, string|null>  $row
     */
    private function findColumnBeforeFecha(array $row): string
    {
        $keys = array_keys($row);
        $fechaIndex = array_search('fecha', $keys, true);

        if ($fechaIndex === null || $fechaIndex === false || $fechaIndex === 0) {
            return '';
        }

        return trim((string) $row[$keys[$fechaIndex - 1]]);
    }

    /** @param  array<int, string>  &$typeWarnings */
    private function matchDocumentType(string $raw, string $rawCode, array &$typeWarnings): string
    {
        $raw = trim($raw);

        if ($raw === '') {
            return 'Otros';
        }

        foreach (Document::DOCUMENT_TYPES as $type) {
            if (Str::lower($type) === Str::lower($raw)) {
                return $type;
            }
        }

        // Tolera singular/plural entre el CSV y el catálogo (ej. "Poder" en el CSV vs "Poderes" en
        // el catálogo) — en español el plural se forma con "-s" o con "-es" según la palabra, así
        // que se comparan ambas variantes de cada lado en vez de solo quitar una "s" final.
        $rawVariants = array_unique([Str::lower($raw), preg_replace('/(es|s)$/', '', Str::lower($raw))]);

        foreach (Document::DOCUMENT_TYPES as $type) {
            $typeVariants = array_unique([Str::lower($type), preg_replace('/(es|s)$/', '', Str::lower($type))]);

            if (array_intersect($rawVariants, $typeVariants)) {
                return $type;
            }
        }

        // Alias para tipos del CSV que no coinciden textualmente con el catálogo pero se
        // refieren al mismo concepto (ej. "Terreno" en el CSV vs "Escrituras de terrenos").
        $aliases = [
            'terreno'  => 'Escrituras de terrenos',
            'terrenos' => 'Escrituras de terrenos',
            'asamblea' => 'Acta de asamblea',
            'asambleas' => 'Acta de asamblea',
        ];

        if (isset($aliases[Str::lower($raw)])) {
            return $aliases[Str::lower($raw)];
        }

        $typeWarnings[] = "{$rawCode}: \"{$raw}\" no coincide con ningún tipo del catálogo — se guardó como \"Otros\".";

        return 'Otros';
    }

    /**
     * @param  array<int, string>  &$accessWarnings
     * @return array<int, int>
     */
    private function resolveAccessUserIds(string $raw, int $groupId, string $rawCode, array &$accessWarnings): array
    {
        $names = array_filter(array_map('trim', preg_split('/[,;]/', $raw)));

        if (empty($names)) {
            return [];
        }

        $groupUsers = User::where('group_id', $groupId)->get(['id', 'name', 'email']);
        $ids = [];

        foreach ($names as $name) {
            $match = $groupUsers->first(fn ($u) => Str::lower($u->name) === Str::lower($name) || Str::lower($u->email) === Str::lower($name));

            if ($match) {
                $ids[] = $match->id;
            } else {
                $accessWarnings[] = "{$rawCode}: no se encontró un usuario del grupo llamado \"{$name}\".";
            }
        }

        return array_unique($ids);
    }

    /**
     * Devuelve solo los dígitos del número de escritura (sin comas de separador de miles), para
     * que "2,378" en el nombre del archivo y "2378" (sin coma) en el CSV se reconozcan como el
     * mismo código sin importar cuál de los dos formatos haya usado cada quien.
     */
    private function extractCode(string $value): ?string
    {
        if (! preg_match(self::CODE_PATTERN, $value, $m)) {
            return null;
        }

        return str_replace(',', '', $m[0]);
    }

    private function parseCsvDate(?string $value): ?\Illuminate\Support\Carbon
    {
        $value = trim((string) $value);

        if ($value === '' || Str::lower($value) === 'no especificado') {
            return null;
        }

        try {
            return \Illuminate\Support\Carbon::createFromFormat('d-m-Y', $value)->startOfDay();
        } catch (\Exception) {
            try {
                return \Illuminate\Support\Carbon::createFromFormat('d/m/Y', $value)->startOfDay();
            } catch (\Exception) {
                try {
                    return \Illuminate\Support\Carbon::parse($value)->startOfDay();
                } catch (\Exception) {
                    return null;
                }
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
            fn ($h) => Str::lower(trim(preg_replace('/\s+/u', ' ', $h))),
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

    /** @return array{0: int, 1: ?int}|null [groupId, companyId] */
    private function resolveScope(): ?array
    {
        if ($companyId = $this->option('company')) {
            $company = Company::find($companyId);
            if (! $company) {
                $this->error("No existe una empresa con ID {$companyId}.");
                return null;
            }

            return [$company->group_id, $company->id];
        }

        if ($groupId = $this->option('group')) {
            return [(int) $groupId, null];
        }

        $this->error('Indica --company=ID (empresa específica) o --group=ID (documentos generales del grupo, sin empresa).');
        return null;
    }

    private function printSummary(
        int $created,
        int $skippedExisting,
        array $rowsWithoutFile,
        array $filesWithoutCode,
        array $unusedFileCodes,
        array $filesByCode,
        array $accessWarnings,
        array $typeWarnings,
        bool $dryRun,
    ): void {
        $this->newLine();
        $this->info(($dryRun ? '[DRY RUN] ' : '') . "Documentos procesados: {$created}. Ya existentes (sin cambios): {$skippedExisting}.");

        if (! empty($rowsWithoutFile)) {
            $this->warn('Filas del CSV cuyo código no tiene ningún archivo correspondiente en la carpeta:');
            foreach ($rowsWithoutFile as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($filesWithoutCode)) {
            $this->warn('Archivos sin ningún número de escritura/código detectable en el nombre (revisar manualmente):');
            foreach ($filesWithoutCode as $name) {
                $this->line("  - {$name}");
            }
        }

        if (! empty($unusedFileCodes)) {
            $this->warn('Archivos cuyo código no coincide con ninguna fila del CSV:');
            foreach ($unusedFileCodes as $code) {
                foreach ($filesByCode[$code] as $file) {
                    $this->line("  - {$file->getFilename()} (código detectado: {$code})");
                }
            }
        }

        if (! empty($typeWarnings)) {
            $this->warn('Tipos sin coincidencia exacta en el catálogo (revisar o agregar alias):');
            foreach (array_unique($typeWarnings) as $w) {
                $this->line("  - {$w}");
            }
        }

        if (! empty($accessWarnings)) {
            $this->warn('Nombres en "Accesos" que no coinciden con ningún usuario del grupo:');
            foreach (array_unique($accessWarnings) as $w) {
                $this->line("  - {$w}");
            }
        }
    }
}
