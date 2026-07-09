<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\RequirementTemplate;
use Database\Seeders\Concerns\GuessesRequirementSubtype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ComercializacionRequirementTemplateSeeder extends Seeder
{
    use GuessesRequirementSubtype;

    public function run(): void
    {
        $filePath = database_path('seeders/data/comercializacion_checklist.csv');

        if (! file_exists($filePath)) {
            $this->command?->error("No se encontró el archivo: {$filePath}");
            return;
        }

        $assetType = AssetType::query()
            ->where('name', 'Comercialización')
            ->first();

        if (! $assetType) {
            $this->command?->error('No existe el asset type Comercialización.');
            return;
        }

        $handle = fopen($filePath, 'r');

        if (! $handle) {
            $this->command?->error('No se pudo abrir el archivo CSV.');
            return;
        }

        $headers = null;
        $createdOrUpdated = [];
        $currentAuthority = null;

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            if ($this->isEmptyRow($row)) {
                continue;
            }

            $trimmedRow = array_map(fn ($value) => trim((string) $value), $row);

            if ($headers === null) {
                $candidateHeaders = $this->normalizeHeaders($trimmedRow);

                if ($this->looksLikeHeaderRow($candidateHeaders)) {
                    $headers = $candidateHeaders;
                }

                continue;
            }

            if ($this->isRepeatedHeaderRow($trimmedRow)) {
                $headers = $this->normalizeHeaders($trimmedRow);
                continue;
            }

            $rowData = $this->mapRowToHeaders($headers, $trimmedRow);

            $dependencyValue = trim((string) ($rowData['dependencia'] ?? ''));
            if ($dependencyValue !== '') {
                $currentAuthority = $dependencyValue;
            }

            $documentName = trim((string) ($rowData['documento'] ?? ''));

            if ($documentName === '') {
                continue;
            }

            $documentName = $this->normalizeRequirementName($documentName);

            if ($documentName === '') {
                continue;
            }

            $scopes = $this->extractScopes($rowData['aplica_para'] ?? null);

            foreach ($scopes as $scope) {
                $template = RequirementTemplate::updateOrCreate(
                    [
                        'name' => $documentName,
                        'asset_type_id' => $assetType->id,
                        'compliance_scope' => $scope,
                    ],
                    [
                        'authority' => $this->normalizeRegulatoryEntity($currentAuthority),
                        'description' => $this->buildDescription($rowData),
                        'subtype' => $this->guessSubtype($documentName),
                    ]
                );

                $createdOrUpdated[$template->id] = true;
            }
        }

        fclose($handle);

        if ($headers === null) {
            $this->command?->error('No se encontró una fila válida de encabezados en el CSV.');
            return;
        }

        $count = count($createdOrUpdated);

        $this->command?->info("Templates de Comercialización importados/actualizados: {$count}");
    }

    private function normalizeHeaders(array $headers): array
    {
        return collect($headers)->map(function ($header) {
            $raw = trim((string) $header);

            if ($raw === '#') {
                return 'dependencia_numero';
            }

            $normalized = Str::of($raw)
                ->replace("\xEF\xBB\xBF", '')
                ->lower()
                ->ascii()
                ->replace(['.', ',', ';', ':', '(', ')'], ' ')
                ->replaceMatches('/\s+/', ' ')
                ->trim()
                ->value();

            return match ($normalized) {
                'dependencia' => 'dependencia',
                'documento' => 'documento',
                'frecuencia', 'frecuencia del permiso' => 'frecuencia_permiso',
                'aplica para', 'aplica' => 'aplica_para',
                'autoridad', 'tipo de documento', 'tipo documento' => 'tipo_documento',
                'area responsable tramite' => 'area_responsable_tramite',
                default => $normalized,
            };
        })->toArray();
    }

    private function looksLikeHeaderRow(array $headers): bool
    {
        $headers = collect($headers);

        return $headers->contains('dependencia')
            && $headers->contains('documento')
            && $headers->contains('frecuencia_permiso')
            && $headers->contains('aplica_para');
    }

    private function isRepeatedHeaderRow(array $row): bool
    {
        return $this->looksLikeHeaderRow($this->normalizeHeaders($row));
    }

    private function mapRowToHeaders(array $headers, array $row): array
    {
        $result = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $result[$header] = isset($row[$index])
                ? trim((string) $row[$index])
                : null;
        }

        return $result;
    }

    private function extractScopes(?string $value): array
    {
        $value = Str::of((string) $value)
            ->lower()
            ->ascii()
            ->replace(['/', ';', '|'], ',')
            ->replace(' y ', ',')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if ($value === '') {
            return ['project'];
        }

        $scopes = collect(explode(',', $value))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->map(function ($item) {
                return match ($item) {
                    'cn', 'proyecto', 'project' => 'project',
                    'op', 'operacion', 'operation' => 'operation',
                    default => null,
                };
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return empty($scopes) ? ['project'] : $scopes;
    }

    private function buildDescription(array $rowData): ?string
    {
        $parts = [];

        if (! empty($rowData['dependencia_numero'])) {
            $parts[] = 'Dependencia #: ' . trim((string) $rowData['dependencia_numero']);
        }

        if (! empty($rowData['frecuencia_permiso'])) {
            $parts[] = 'Frecuencia: ' . trim((string) $rowData['frecuencia_permiso']);
        }

        if (! empty($rowData['tipo_documento'])) {
            $parts[] = 'Tipo documento: ' . trim((string) $rowData['tipo_documento']);
        }

        if (! empty($rowData['area_responsable_tramite'])) {
            $parts[] = 'Área responsable: ' . trim((string) $rowData['area_responsable_tramite']);
        }

        return empty($parts) ? null : implode(' | ', $parts);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizeRequirementName(string $name): string
    {
        return Str::of($name)
            ->replace("\xC2\xA0", ' ')
            ->replaceMatches('/\b(19|20)\d{2}\b/u', '')
            ->replace(' + hoja de ayuda + acuse de cumplimiento autoridad', '')
            ->replace('+ hoja de ayuda + acuse de cumplimiento autoridad', '')
            ->replace(' + acuse de cumplimiento autoridad', '')
            ->replace('+ acuse de cumplimiento autoridad', '')
            ->replaceMatches('/\bOPE\/CRE\b/u', '')
            ->replaceMatches('/\bOPE\/CNE\b/u', '')
            ->replaceMatches('/\bCRE\b/u', '')
            ->replaceMatches('/\bCNE\b/u', '')
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    private function normalizeRegulatoryEntity(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = Str::of($value)
            ->lower()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        if (in_array($normalized, [
            'numero de permiso',
            'domicilio cre cne',
            'marca del petrolifero',
            'tipo de petrolifero glp',
            'tipo de petrolifero/glp',
        ], true)) {
            return null;
        }

        return match (true) {
            str_contains($normalized, 'secretaria de energia') => 'SENER',
            str_contains($normalized, 'comision nacional de energia') => 'CNE',
            str_contains($normalized, 'cne') => 'CNE',
            str_contains($normalized, 'cre') => 'CRE',
            str_contains($normalized, 'sat') => 'SAT',
            str_contains($normalized, 'servicio de administracion tributaria') => 'SAT',
            str_contains($normalized, 'stps') => 'STPS',
            str_contains($normalized, 'salud') => 'SALUD',
            str_contains($normalized, 'sict') => 'SICT',
            str_contains($normalized, 'infraestructura comunicaciones y transportes') => 'SICT',
            str_contains($normalized, 'cofepris') => 'COFEPRIS',
            str_contains($normalized, 'asea') => 'ASEA',
            str_contains($normalized, 'semarnat') => 'SEMARNAT',
            str_contains($normalized, 'proteccion civil') => 'PROTECCION CIVIL',
            default => mb_strtoupper($value),
        };
    }
}