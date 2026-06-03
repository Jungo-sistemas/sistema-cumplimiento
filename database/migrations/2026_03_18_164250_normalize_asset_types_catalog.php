<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 0. Hacer nullable company_id para poder insertar tipos globales
        Schema::table('asset_types', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->change();
        });

        $now = now();

        $validTypes = [
            'Almacenamiento',
            'Comercialización',
            'EC',
            'ES',
            'Importación',
            'Plantas',
            'Transporte',
        ];

        // 1. Asegurar que existan los tipos válidos
        foreach ($validTypes as $name) {
            $exists = DB::table('asset_types')->where('name', $name)->exists();

            if (!$exists) {
                DB::table('asset_types')->insert([
                    'name'           => $name,
                    'company_id'     => null,
                    'priority_level' => 1,
                    'warning_days'   => 60,
                    'danger_days'    => 30,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        // 2. Reasignar activos de Pipa -> Transporte
        $pipaId       = DB::table('asset_types')->where('name', 'Pipa')->value('id');
        $transporteId = DB::table('asset_types')->where('name', 'Transporte')->value('id');

        if ($pipaId && $transporteId) {
            DB::table('assets')
                ->where('asset_type_id', $pipaId)
                ->update([
                    'asset_type_id' => $transporteId,
                    'updated_at'    => $now,
                ]);
        }

        // 3. Eliminar tipos obsoletos solo si ya no tienen activos ligados
        $obsoleteTypes = ['ATQ', 'Documentos', 'Muelles', 'Pipa', 'Terminal'];

        foreach ($obsoleteTypes as $name) {
            $typeId = DB::table('asset_types')->where('name', $name)->value('id');

            if ($typeId) {
                $hasAssets = DB::table('assets')
                    ->where('asset_type_id', $typeId)
                    ->exists();

                if (!$hasAssets) {
                    DB::table('asset_types')->where('id', $typeId)->delete();
                }
            }
        }
    }

    public function down(): void
    {
        // No revertimos para evitar inconsistencias de datos.
    }
};