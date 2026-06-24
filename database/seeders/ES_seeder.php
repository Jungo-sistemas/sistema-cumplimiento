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

class ES_Seeder extends Seeder
{
    const MDI_RAZON_SOCIAL = 'Mercantil Distribuidora, S.A. de C.V.';

    public function run(): void
    {
        DB::transaction(function () {

            $company = Company::where('name', 'MERCANTIL DISTRIBUIDORA')->firstOrFail();
            $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
                ->firstOrFail();
            $assetType = AssetType::where('name', 'ES')->firstOrFail();

            $syncService = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate = Carbon::now()->addYear()->startOfDay();

            $csvPath = database_path('seeders/examples/ES_ejemplos.csv');

            if (!file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el CSV: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            // Fila 1: título
            fgetcsv($handle);

            // Fila 2: headers reales
            $headers = fgetcsv($handle);

            $headers = array_map(function ($value) {
                $value = trim((string)$value);
                $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);
                return $value;
            }, $headers);

            while (($row = fgetcsv($handle)) !== false) {

                if ($this->isEmptyRow($row)) {
                    continue;
                }

                $data = $this->combineRow($headers, $row);

                // Solo procesar registros de MDI
                $razonSocial = trim((string)($data['razon_social'] ?? ''));
                if ($razonSocial !== self::MDI_RAZON_SOCIAL) {
                    continue;
                }

                $code = trim((string)($data['code'] ?? ''));
                $name = trim((string)($data['name'] ?? ''));
                $location = trim((string)($data['location'] ?? ''));
                $vaultLocation = trim((string)($data['vault_location'] ?? ''));

                if ($code === '' || $name === '') {
                    continue;
                }

                $name = strtoupper($name);

                $startDate = $this->parseInicioVigencia($data['inicio_vigencia'] ?? null, $defaultStartDate);

                $asset = Asset::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code' => $code,
                    ],
                    [
                        'asset_type_id' => $assetType->id,
                        'name' => $name,
                        'location' => $location ?: null,
                        'vault_location' => $vaultLocation ?: null,
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
            'razon_social'   => $this->findValue($raw, ['Razon social', 'Razón social', 'RAZON SOCIAL']),
            'code'           => $this->findValue($raw, ['PERMISO CRE']),
            'name'           => $this->findValue($raw, ['Estacion', 'Estación']),
            'location'       => $this->findValue($raw, ['Estado', 'ESTADO']),
            'vault_location' => $this->findValue($raw, ['DIRECCION', 'Direccion', 'Dirección']),
            'inicio_vigencia' => $this->findValue($raw, ['INICIO DE VIGENCIA']),
        ];
    }

    protected function findValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $row)) {
                return $row[$key];
            }
        }
        return null;
    }

    protected function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string)$value) !== '') {
                return false;
            }
        }
        return true;
    }
}
