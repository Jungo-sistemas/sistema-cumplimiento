<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\RequirementTemplate;
use Database\Seeders\Concerns\GuessesRequirementSubtype;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PlantasRequirementTemplateSeeder extends Seeder
{
    use GuessesRequirementSubtype;

    public function run(): void
    {
        $filePath = database_path('seeders/data/Checklist Plantas.csv');

        if (! file_exists($filePath)) {
            $this->command?->error("No se encontró el archivo: {$filePath}");
            return;
        }

        $assetType = AssetType::query()
            ->where('name', 'Plantas')
            ->first();

        if (! $assetType) {
            $this->command?->error('No existe el asset type Plantas.');
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

            $template = RequirementTemplate::updateOrCreate(
                [
                    'name' => $documentName,
                    'asset_type_id' => $assetType->id,
                ],
                [
                    'authority' => $this->normalizeRegulatoryEntity($currentAuthority),
                    'description' => $this->buildDescription($rowData),
                    'subtype' => $this->guessSubtype($documentName),
                    'priority' => $this->normalizePriority($rowData['prioridad'] ?? null),
                    'responsible_area' => $this->normalizeResponsibleArea($rowData['area_responsable'] ?? null),
                ]
            );

            $createdOrUpdated[$template->id] = true;
        }

        fclose($handle);

        if ($headers === null) {
            $this->command?->error('No se encontró una fila válida de encabezados en el CSV.');
            return;
        }

        $count = count($createdOrUpdated);

        $this->command?->info("Templates Plantas importados/actualizados: {$count}");
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

            if (str_starts_with($normalized, 'nivel de riesgo') || $normalized === 'prioridad') {
                return 'prioridad';
            }

            return match ($normalized) {
                'dependencia' => 'dependencia',
                'documento', 'cumplimiento normativo' => 'documento',
                'frecuencia', 'frecuencia del permiso' => 'frecuencia_permiso',
                'area responsable', 'area responsable tramite' => 'area_responsable',
                default => $normalized,
            };
        })->toArray();
    }

    private function looksLikeHeaderRow(array $headers): bool
    {
        $headers = collect($headers);

        return $headers->contains('dependencia')
            && $headers->contains('documento')
            && $headers->contains('frecuencia_permiso');
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

    private function buildDescription(array $rowData): ?string
    {
        $parts = [];

        if (! empty($rowData['dependencia_numero'])) {
            $parts[] = 'Dependencia #: ' . trim((string) $rowData['dependencia_numero']);
        }

        if (! empty($rowData['frecuencia_permiso'])) {
            $parts[] = 'Frecuencia: ' . trim((string) $rowData['frecuencia_permiso']);
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
        $name = Str::of($name)
            ->replace("\xC2\xA0", ' ')
            ->replace(' + hoja de ayuda + acuse de cumplimiento autoridad', '')
            ->replace('+ hoja de ayuda + acuse de cumplimiento autoridad', '')
            ->replace(' + acuse de cumplimiento autoridad', '')
            ->replace('+ acuse de cumplimiento autoridad', '')
            ->value();

        // Los códigos de NOM (p. ej. "NOM-016-CRE-2016") llevan año y siglas de la
        // autoridad como parte del nombre oficial de la norma — no se deben recortar.
        if (! preg_match('/^NOM[\s-]/iu', trim($name))) {
            $name = Str::of($name)
                ->replaceMatches('/\b(19|20)\d{2}\b/u', '')
                ->replaceMatches('/\bOPE\/CRE\b/u', '')
                ->replaceMatches('/\bOPE\/CNE\b/u', '')
                ->replaceMatches('/\bCRE\b/u', '')
                ->replaceMatches('/\bCNE\b/u', '')
                ->value();
        }

        return Str::of($name)->replaceMatches('/\s+/', ' ')->trim()->value();
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
        ])) {
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

    /**
     * Mapea el texto libre de "Nivel de riesgo" del checklist a alta|media|baja.
     * No confundir con AssetRequirement::risk_level (calculado por fecha de vencimiento).
     */
    private function normalizePriority(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = Str::of($value)->lower()->ascii()->trim()->value();

        return match (true) {
            str_starts_with($normalized, 'alt') => 'alta',
            str_starts_with($normalized, 'med') => 'media',
            str_starts_with($normalized, 'baj') => 'baja',
            default => null,
        };
    }

    /**
     * Normaliza el área responsable a un valor consistente (mayúsculas, sin
     * acentos ni espacios extra). Los valores conocidos se van agregando aquí
     * conforme aparecen en los checklists reales.
     */
    private function normalizeResponsibleArea(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $normalized = Str::of($value)
            ->upper()
            ->ascii()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();

        return match ($normalized) {
            'LEGAL', 'JURIDICO', 'JURIDICA' => 'LEGAL',
            default => $normalized,
        };
    }
}
