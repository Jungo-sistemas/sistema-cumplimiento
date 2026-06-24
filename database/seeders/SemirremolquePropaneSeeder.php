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

class SemirremolquePropaneSeeder extends Seeder
{
    const PERMISO = 'LP/21585/TRA/2018';

    public function run(): void
    {
        $company = Company::where('name', 'PROPANE')->firstOrFail();

        $semirremolqueType = AssetType::where('name', 'Semirremolque')->firstOrFail();

        $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->firstOrFail();

        $syncService      = app(SyncAssetRequirementsService::class);
        $defaultStartDate = Carbon::now()->startOfDay();
        $defaultDueDate   = Carbon::now()->addYear()->startOfDay();

        // Buscar el permiso de transporte existente por su código
        $permisoAsset = Asset::where('code', self::PERMISO)
            ->whereHas('assetType', fn ($q) => $q->where('name', 'Transporte'))
            ->firstOrFail();

        $this->command?->info("Permiso de transporte encontrado: [{$permisoAsset->name}] id={$permisoAsset->id}");

        $csvPath = database_path('seeders/examples/Semirremolque_Propane_Service.csv');

        if (! file_exists($csvPath)) {
            throw new \RuntimeException("No se encontró el CSV en: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            throw new \RuntimeException('No se pudo abrir el CSV.');
        }

        // Encabezados — convertir encoding y quedarse con la primera línea (headers multi-línea)
        $rawHeaders = fgetcsv($handle);
        $headers = array_map(function ($v) {
            $v = mb_convert_encoding(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), 'UTF-8', 'Windows-1252');
            return trim(explode("\n", $v)[0]);
        }, $rawHeaders);

        $count = 0;

        while (($row = fgetcsv($handle)) !== false) {
            $row = array_map(
                fn ($v) => mb_convert_encoding(trim((string) $v), 'UTF-8', 'Windows-1252'),
                $row
            );

            if (implode('', $row) === '') {
                continue;
            }

            $data = array_combine($headers, array_pad($row, count($headers), null));

            $code = $this->col($data, 'IDCNE', 'ID CNE');

            if ($code === '') {
                continue;
            }

            $noEconomico = $this->col($data, 'NÚMERO ECONÓMICO');
            $marca       = strtoupper($this->col($data, 'MARCA'));

            // Nombre: MARCA — No. ECONÓMICO
            $nameParts = array_filter([$marca, $noEconomico]);
            $name      = implode(' — ', $nameParts) ?: $code;

            $capacidad    = $this->col($data, 'CAPACIDAD');
            $capacidadInt = is_numeric($capacidad) ? (int) $capacidad : null;

            DB::transaction(function () use (
                $company, $semirremolqueType, $responsibleUser, $syncService,
                $defaultStartDate, $defaultDueDate, $permisoAsset,
                $code, $name, $noEconomico, $marca, $capacidadInt, $data
            ) {
                $asset = Asset::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'code'       => $code,
                    ],
                    [
                        'asset_type_id'         => $semirremolqueType->id,
                        'name'                  => $name,
                        'location'              => null,
                        'parent_asset_id'       => $permisoAsset->id,
                        'vault_location'        => self::PERMISO,
                        'responsible_user_id'   => $responsibleUser->id,
                        'status'                => 'active',
                        'compliance_start_date' => $defaultStartDate,
                        'compliance_due_date'   => $defaultDueDate,
                        'no_economico'          => $noEconomico ?: null,
                        'marca_recipiente'      => $marca ?: null,
                        'capacidad_litros'      => $capacidadInt,
                        'serie_recipiente'      => $this->col($data, 'NÚMERO DE SERIE') ?: null,
                        'placas'                => strtoupper($this->col($data, 'NÚMERO DE PLACA', 'PLACA')) ?: null,
                    ]
                );

                $syncService->handle($asset, removeObsolete: true);
            });

            $count++;
        }

        fclose($handle);

        $this->command?->info("✓ Semirremolques PROPANE: {$count} activos enlazados a [{$permisoAsset->name}].");
    }

    private function col(array $data, string ...$keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
            foreach ($data as $k => $v) {
                if (stripos($k, $key) !== false && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }
        }
        return '';
    }
}
