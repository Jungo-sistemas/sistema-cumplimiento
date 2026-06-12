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

class AtqKiwiSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'KIWI GAS')->firstOrFail();

            $assetType = AssetType::where('name', 'ATQ')->firstOrFail();

            $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
                ->firstOrFail();

            $syncService     = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate   = Carbon::now()->addYear()->startOfDay();

            $csvPath = database_path('seeders/examples/ATQ_KIWI.csv');

            if (! file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el archivo CSV en: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el archivo CSV.');
            }

            // Encabezados — convertir de Windows-1252 a UTF-8
            $rawHeaders = fgetcsv($handle);
            $headers = array_map(function ($v) {
                $v = trim((string) $v);
                $v = preg_replace('/^\xEF\xBB\xBF/', '', $v);
                return mb_convert_encoding($v, 'UTF-8', 'Windows-1252');
            }, $rawHeaders);

            $count = 0;

            while (($row = fgetcsv($handle)) !== false) {
                // Convertir encoding Windows-1252 → UTF-8
                $row = array_map(
                    fn ($v) => mb_convert_encoding(trim((string) $v), 'UTF-8', 'Windows-1252'),
                    $row
                );

                if (implode('', $row) === '') {
                    continue;
                }

                $data = array_combine($headers, array_pad($row, count($headers), null));

                $code = $this->col($data, 'ID CNE');

                if ($code === '') {
                    continue;
                }

                $marca  = strtoupper($this->col($data, 'MARCA'));
                $modelo = strtoupper($this->col($data, 'MODELO'));
                $placas = strtoupper($this->col($data, 'PLACAS'));

                // Nombre auto-generado: MARCA MODELO — PLACAS
                $nameParts = array_filter([$marca, $modelo]);
                $name = implode(' ', $nameParts);
                if ($placas !== '') {
                    $name .= ' — ' . $placas;
                }

                $capacidad = $this->col($data, 'CAPACIDAD DEL RECIPIENTE');
                if ($capacidad === '') {
                    // Fallback: intentar con el nombre completo de la columna
                    foreach (array_keys($data) as $key) {
                        if (stripos($key, 'CAPACIDAD') !== false) {
                            $capacidad = trim((string) $data[$key]);
                            break;
                        }
                    }
                }
                $capacidadInt = is_numeric($capacidad) ? (int) $capacidad : null;

                DB::transaction(function () use (
                    $company, $assetType, $responsibleUser, $syncService,
                    $defaultStartDate, $defaultDueDate, $code, $name, $capacidadInt, $data,
                    $marca, $modelo, $placas
                ) {
                    $asset = Asset::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'code'       => $code,
                        ],
                        [
                            'asset_type_id'         => $assetType->id,
                            'name'                  => $name,
                            'vault_location'        => $this->col($data, 'PERMISO') ?: null,
                            'responsible_user_id'   => $responsibleUser->id,
                            'status'                => 'active',
                            'compliance_start_date' => $defaultStartDate,
                            'compliance_due_date'   => $defaultDueDate,
                            'no_economico'          => $this->col($data, 'NÚMERO ECONÓMICO') ?: null,
                            'numero_serie'          => $this->col($data, 'NÚMERO DE SERIE') ?: null,
                            'marca'                 => $marca ?: null,
                            'modelo'                => $modelo ?: null,
                            'placas'                => $placas ?: null,
                            'marca_recipiente'      => strtoupper($this->col($data, 'MARCA DEL RECIPIENTE')) ?: null,
                            'capacidad_litros'      => $capacidadInt,
                            'serie_recipiente'      => $this->col($data, 'NÚMERO DE SERIE DEL RECIPIENTE') ?: null,
                        ]
                    );

                    $syncService->handle($asset, removeObsolete: true);
                });

                $count++;
            }

            fclose($handle);

            $this->command?->info("✓ ATQ KIWI GAS: {$count} activos creados/actualizados.");
    }

    private function col(array $data, string ...$keys): string
    {
        foreach ($keys as $key) {
            // Búsqueda exacta
            if (array_key_exists($key, $data) && trim((string) $data[$key]) !== '') {
                return trim((string) $data[$key]);
            }
            // Búsqueda parcial (para columnas con saltos de línea en el header)
            foreach ($data as $k => $v) {
                if (stripos($k, $key) !== false && trim((string) $v) !== '') {
                    return trim((string) $v);
                }
            }
        }
        return '';
    }
}
