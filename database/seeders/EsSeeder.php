<?php

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Company;
use App\Models\Group;
use App\Models\User;
use App\Services\SyncAssetRequirementsService;
use Database\Seeders\Concerns\NormalizesLocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EsSeeder extends Seeder
{
    use NormalizesLocation;

    const MDI_RAZON_SOCIAL = 'Mercantil Distribuidora, S.A. de C.V.';

    public function run(): void
    {
        DB::transaction(function () {
            $mdiCompany      = Company::where('name', 'MERCANTIL DISTRIBUIDORA')->firstOrFail();
            $vigiaGroup      = Group::where('slug', 'vigia')->firstOrFail();
            $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
                ->firstOrFail();
            $assetType       = AssetType::where('name', 'ES')->firstOrFail();
            $syncService     = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate   = Carbon::now()->addYear()->startOfDay();

            $csvPath = database_path('seeders/examples/ES_ejemplos.csv');

            if (! file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el archivo CSV en: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el archivo CSV.');
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

                $razonSocial   = trim((string) ($data['razon_social'] ?? ''));
                $code          = trim((string) ($data['code'] ?? ''));
                $name          = trim((string) ($data['name'] ?? ''));
                $location      = trim((string) ($data['location'] ?? ''));
                $vaultLocation = trim((string) ($data['vault_location'] ?? ''));

                if ($razonSocial === '' || $code === '' || $name === '') {
                    continue;
                }

                // Para MDI usamos la empresa ya existente;
                // para cualquier otra razón social creamos una empresa con otras = true.
                if ($razonSocial === self::MDI_RAZON_SOCIAL) {
                    $company = $mdiCompany;
                } else {
                    $company = Company::firstOrCreate(
                        ['name' => $razonSocial],
                        [
                            'otras'    => true,
                            'group_id' => $vigiaGroup->id,
                        ]
                    );
                }

                $startDate = $this->parseInicioVigencia($data['inicio_vigencia'] ?? null, $defaultStartDate);

                $asset = Asset::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code'       => $code,
                    ],
                    [
                        'asset_type_id'         => $assetType->id,
                        'name'                  => $name,
                        'location'              => $this->normalizeLocation($location),
                        'vault_location'        => $vaultLocation !== '' ? $vaultLocation : null,
                        'responsible_user_id'   => $responsibleUser->id,
                        'status'                => 'active',
                        'compliance_start_date' => $startDate,
                        'compliance_due_date'   => $defaultDueDate,
                        'parent_asset_id'       => null,
                    ]
                );

                $syncService->handle($asset, removeObsolete: true);
            }

            fclose($handle);
        });
    }

    // Dates in ES CSV use dd/mm/yyyy format (e.g. "28/01/2022")
    protected function parseInicioVigencia(?string $dateStr, Carbon $fallback): Carbon
    {
        if (! $dateStr || trim($dateStr) === '') {
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
            'razon_social'    => $this->findValue($raw, ['Razon social', 'Razón Social', 'Razón social', 'RAZON SOCIAL']),
            'code'            => $this->findValue($raw, ['PERMISO CRE', 'PERMISO CRE ']),
            'name'            => $this->findValue($raw, ['Estacion', 'Estación', 'ESTACION']),
            'location'        => $this->findValue($raw, ['Estado', 'ESTADO']),
            'vault_location'  => $this->findValue($raw, ['DIRECCION', 'Direccion', 'Dirección']),
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
