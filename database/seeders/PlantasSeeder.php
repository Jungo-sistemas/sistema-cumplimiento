<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\User;
use App\Services\SyncAssetRequirementsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PlantasSeeder extends Seeder
{
    const MDI_RAZON_SOCIAL = 'Mercantil Distribuidora, S.A. de C.V.';

    // Sorted longest-first to avoid partial replacements (e.g. "agto" before "ago")
    const SPANISH_MONTHS = [
        'agto' => '08', 'ene' => '01', 'feb' => '02', 'mzo' => '03',
        'mar' => '03', 'abr' => '04', 'may' => '05', 'jun' => '06',
        'jul' => '07', 'ago' => '08', 'sep' => '09', 'oct' => '10',
        'nov' => '11', 'dic' => '12',
    ];

    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::where('name', 'MDI')->firstOrFail();
            $responsibleUser = User::where('email', 'admin@vigia.com.mx')->firstOrFail();
            $assetType = AssetType::where('name', 'Plantas')->firstOrFail();
            $syncService = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate = Carbon::now()->addYear()->startOfDay();

            $csvPath = database_path('seeders/examples/Plantas_ejemplos.csv');

            if (! file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el archivo CSV en: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            if ($handle === false) {
                throw new \RuntimeException("No se pudo abrir el archivo CSV.");
            }

            // Fila 1: título, se ignora
            fgetcsv($handle);

            // Fila 2: encabezados reales
            $headers = fgetcsv($handle);

            if ($headers === false) {
                fclose($handle);
                throw new \RuntimeException('El CSV no tiene encabezados válidos.');
            }

            $headers = array_map(function ($value) {
                $value = trim((string) $value);
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                return $value;
            }, $headers);

            while (($row = fgetcsv($handle)) !== false) {
                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($headers, $row);

                // Solo procesar registros de MDI
                $razonSocial = trim((string) ($data['razon_social'] ?? ''));
                if ($razonSocial !== self::MDI_RAZON_SOCIAL) {
                    continue;
                }

                $code = trim((string) ($data['code'] ?? ''));
                $name = trim((string) ($data['name'] ?? ''));
                $location = trim((string) ($data['location'] ?? ''));
                $vaultLocation = trim((string) ($data['vault_location'] ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $startDate = $this->parseInicioVigencia($data['inicio_vigencia'] ?? null, $defaultStartDate);

                $asset = Asset::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $code,
                    ],
                    [
                        'asset_type_id' => $assetType->id,
                        'name' => $name,
                        'location' => $location !== '' ? $location : null,
                        'vault_location' => $vaultLocation !== '' ? $vaultLocation : null,
                        'responsible_user_id' => $responsibleUser->id,
                        'status' => 'active',
                        'compliance_start_date' => $startDate,
                        'compliance_due_date' => $defaultDueDate,
                        'parent_asset_id' => null,
                    ]
                );

                $syncService->handle($asset, removeObsolete: true);
            }

            fclose($handle);
        });
    }

    // Handles Spanish abbreviated month formats with mixed separators:
    // "03-nov-99", "8-agto-2000", "8 mzo 2011", "8 agto 00", etc.
    protected function parseInicioVigencia(?string $dateStr, Carbon $fallback): Carbon
    {
        if (!$dateStr || trim($dateStr) === '') {
            return $fallback;
        }

        $normalized = strtolower(trim($dateStr));

        foreach (self::SPANISH_MONTHS as $abbr => $num) {
            $normalized = str_replace($abbr, $num, $normalized);
        }

        // Unify spaces and dashes into a single dash
        $normalized = preg_replace('/[\s-]+/', '-', $normalized);
        $normalized = trim($normalized, '-');

        $parts = explode('-', $normalized);
        if (count($parts) !== 3) {
            return $fallback;
        }

        [$day, $month, $year] = $parts;

        if (!is_numeric($day) || !is_numeric($month) || !is_numeric($year)) {
            return $fallback;
        }

        $year = (int) $year;
        if ($year < 100) {
            $year = $year >= 50 ? 1900 + $year : 2000 + $year;
        }

        try {
            return Carbon::createFromDate($year, (int) $month, (int) $day)->startOfDay();
        } catch (\Exception $e) {
            return $fallback;
        }
    }

    protected function combineRow(array $headers, array $row): array
    {
        $row = array_pad($row, count($headers), null);
        $raw = array_combine($headers, $row);

        return [
            'razon_social'    => $this->findValue($raw, ['Razón Social', 'Razon social', 'Razón social', 'RAZON SOCIAL']),
            'code'            => $this->findValue($raw, ['PERMISO CNE (ALTA)']),
            'name'            => $this->findValue($raw, ['Nombre de Planta']),
            'location'        => $this->findValue($raw, ['ESTADO', 'Estado']),
            'vault_location'  => $this->findValue($raw, ['Direccion CNE', 'Dirección CNE', 'DIRECCION']),
            'inicio_vigencia' => $this->findValue($raw, ['INICIO DE VIGENCIA']),
        ];
    }

    protected function findValue(array $row, array $possibleHeaders): ?string
    {
        foreach ($possibleHeaders as $header) {
            if (array_key_exists($header, $row)) {
                return $row[$header];
            }
        }

        return null;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
