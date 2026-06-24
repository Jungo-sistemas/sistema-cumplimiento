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

class SemirremolqueMigarSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'MIGAR')->firstOrFail();

        $assetType = AssetType::where('name', 'Semirremolque')->firstOrFail();

        $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->firstOrFail();

        $syncService      = app(SyncAssetRequirementsService::class);
        $defaultStartDate = Carbon::now()->startOfDay();
        $defaultDueDate   = Carbon::now()->addYear()->startOfDay();

        $csvPath = database_path('seeders/examples/Semirremolque_MIGAR.csv');

        if (! file_exists($csvPath)) {
            throw new \RuntimeException("No se encontró el CSV en: {$csvPath}");
        }

        $handle = fopen($csvPath, 'r');

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

            $noEconomico  = $this->col($data, 'NÚMERO ECONÓMICO');
            $marca        = strtoupper($this->col($data, 'MARCA'));
            $nameParts    = array_filter([$marca, $noEconomico]);
            $name         = implode(' — ', $nameParts) ?: $code;
            $capacidad    = $this->col($data, 'CAPACIDAD');
            $capacidadInt = is_numeric($capacidad) ? (int) $capacidad : null;

            DB::transaction(function () use (
                $company, $assetType, $responsibleUser, $syncService,
                $defaultStartDate, $defaultDueDate,
                $code, $name, $noEconomico, $marca, $capacidadInt, $data
            ) {
                $asset = Asset::updateOrCreate(
                    ['company_id' => $company->id, 'code' => $code],
                    [
                        'asset_type_id'         => $assetType->id,
                        'name'                  => $name,
                        'location'              => null,
                        'parent_asset_id'       => null,
                        'vault_location'        => $this->col($data, 'PERMISO') ?: null,
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

        $this->command?->info("✓ Semirremolques MIGAR: {$count} activo(s) creado(s)/actualizado(s).");
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
