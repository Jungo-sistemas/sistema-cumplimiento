<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\User;
use App\Services\SyncAssetRequirementsService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Database\Seeders\Concerns\NormalizesLocation;
use Illuminate\Support\Facades\DB;

class EC_Seeder extends Seeder
{
    use NormalizesLocation;
    const MDI_RAZON_SOCIAL = 'Mercantil Distribuidora, S.A. de C.V.';

    public function run(): void
    {
        DB::transaction(function () {
            $company = Company::where('name', 'MERCANTIL DISTRIBUIDORA')->firstOrFail();
            $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
                ->firstOrFail();
            $assetType = AssetType::where('name', 'EC')->firstOrFail();
            $syncService = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate = Carbon::now()->addYear()->startOfDay();

            $csvPath = database_path('seeders/examples/EC_ejemplos.csv');

            if (! file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el archivo CSV en: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            if ($handle === false) {
                throw new \RuntimeException("No se pudo abrir el archivo CSV.");
            }

            // Fila 1: título
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
                $station = trim((string) ($data['station'] ?? ''));
                $location = trim((string) ($data['location'] ?? ''));
                $vaultLocation = trim((string) ($data['vault_location'] ?? ''));

                if ($code === '' || $station === '') {
                    continue;
                }

                // Normalize: strip leading "EC " if already present, then add it once
                $station = preg_replace('/^EC\s+/i', '', $station);
                $stationName = 'EC ' . strtoupper($station);

                $startDate = $this->parseInicioVigencia($data['inicio_vigencia'] ?? null, $defaultStartDate);

                $asset = Asset::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $code,
                    ],
                    [
                        'asset_type_id' => $assetType->id,
                        'name' => $stationName,
                        'location' => $this->normalizeLocation($location),
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

    protected function parseInicioVigencia(?string $dateStr, Carbon $fallback): Carbon
    {
        if (!$dateStr || trim($dateStr) === '') {
            return $fallback;
        }

        try {
            return Carbon::createFromFormat('d/m/Y', trim($dateStr))->startOfDay();
        } catch (\Exception $e) {
            return $fallback;
        }
    }

    protected function combineRow(array $headers, array $row): array
    {
        $row = array_pad($row, count($headers), null);
        $raw = array_combine($headers, $row);

        return [
            'razon_social'    => $this->findValue($raw, ['Razon social', 'Razón social', 'RAZON SOCIAL']),
            'code'            => $this->findValue($raw, ['PERMISO CRE']),
            'station'         => $this->findValue($raw, ['Estación', 'Estacion']),
            'location'        => $this->findValue($raw, ['Estado', 'ESTADO']),
            'vault_location'  => $this->findValue($raw, ['DIRECCION', 'Dirección', 'Direccion']),
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
