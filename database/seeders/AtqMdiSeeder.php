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

class AtqMdiSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::where('name', 'MERCANTIL DISTRIBUIDORA')->firstOrFail();

            $assetType = AssetType::where('name', 'ATQ')->firstOrFail();

            $responsibleUser = User::whereIn('email', ['admin@vigia.com.mx', 'dev2.int@vigia.com.mx'])
                ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
                ->firstOrFail();

            $syncService      = app(SyncAssetRequirementsService::class);
            $defaultStartDate = Carbon::now()->startOfDay();
            $defaultDueDate   = Carbon::now()->addYear()->startOfDay();

            // Pre-cargar plantas de MDI con clave en mayúsculas para match insensible a mayúsculas
            $plantasMap = Asset::where('company_id', $company->id)
                ->whereHas('assetType', fn ($q) => $q->where('name', 'Plantas'))
                ->get(['id', 'name'])
                ->keyBy(fn ($a) => strtoupper(trim($a->name)));

            $csvPath = database_path('seeders/examples/vehiculos_MDI.csv');

            if (! file_exists($csvPath)) {
                throw new \RuntimeException("No se encontró el archivo CSV en: {$csvPath}");
            }

            $handle = fopen($csvPath, 'r');

            if ($handle === false) {
                throw new \RuntimeException('No se pudo abrir el archivo CSV.');
            }

            // Encabezados
            $rawHeaders = fgetcsv($handle);
            $headers = array_map(function ($v) {
                return mb_convert_encoding(trim(preg_replace('/^\xEF\xBB\xBF/', '', (string) $v)), 'UTF-8', 'Windows-1252');
            }, $rawHeaders);

            $linked   = 0;
            $unlinked = 0;
            $count    = 0;

            while (($row = fgetcsv($handle)) !== false) {
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
                $planta = strtoupper(trim($this->col($data, 'PLANTA')));

                // Nombre: MARCA MODELO — PLACAS
                $nameParts = array_filter([$marca, $modelo]);
                $name      = implode(' ', $nameParts);
                if ($placas !== '') {
                    $name .= ' — ' . $placas;
                }

                // Intentar enlazar con planta existente (case-insensitive)
                $parentAsset   = $plantasMap->get($planta);
                $parentId      = $parentAsset?->id;
                $location      = $parentAsset?->location ?? null;
                $vaultLocation = $this->col($data, 'PERMISO') ?: null;

                // Si no se enlazó, guardar el nombre de la planta en bóveda como referencia
                if (! $parentId && $planta !== '') {
                    $vaultLocation = '[Planta: ' . $planta . ']'
                        . ($vaultLocation ? ' | ' . $vaultLocation : '');
                    $unlinked++;
                } else {
                    $linked++;
                }

                $capacidad    = $this->col($data, 'CAPACIDAD DEL RECIPIENTE');
                $capacidadInt = is_numeric($capacidad) ? (int) $capacidad : null;

                // Cada activo en su propia mini-transacción para evitar overflow de locks
                DB::transaction(function () use (
                    $company, $assetType, $responsibleUser, $syncService,
                    $defaultStartDate, $defaultDueDate, $code, $name,
                    $parentId, $location, $vaultLocation, $marca, $modelo, $placas,
                    $capacidadInt, $data
                ) {
                    $asset = Asset::updateOrCreate(
                        [
                            'company_id' => $company->id,
                            'code'       => $code,
                        ],
                        [
                            'asset_type_id'         => $assetType->id,
                            'name'                  => $name,
                            'location'              => $location,
                            'parent_asset_id'       => $parentId,
                            'vault_location'        => $vaultLocation,
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

            $this->command?->info("✓ ATQ MDI: {$count} activos | {$linked} enlazados a planta | {$unlinked} sin planta (info en bóveda).");
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
