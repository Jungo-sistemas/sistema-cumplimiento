<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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

        foreach ($validTypes as $name) {
            $exists = DB::table('asset_types')->where('name', $name)->exists();

            if (!$exists) {
                DB::table('asset_types')->insert([
                    'name'           => $name,
                    'priority_level' => 1,
                    'warning_days'   => 60,
                    'danger_days'    => 30,
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]);
            }
        }

        $pipaId      = DB::table('asset_types')->where('name', 'Pipa')->value('id');
        $transporteId = DB::table('asset_types')->where('name', 'Transporte')->value('id');

        if ($pipaId && $transporteId) {
            DB::table('assets')
                ->where('asset_type_id', $pipaId)
                ->update(['asset_type_id' => $transporteId, 'updated_at' => $now]);
        }

        $obsoleteTypes = ['ATQ', 'Documentos', 'Muelles', 'Pipa', 'Terminal'];

        foreach ($obsoleteTypes as $name) {
            $typeId = DB::table('asset_types')->where('name', $name)->value('id');

            if ($typeId) {
                $hasAssets = DB::table('assets')->where('asset_type_id', $typeId)->exists();

                if (!$hasAssets) {
                    DB::table('asset_types')->where('id', $typeId)->delete();
                }
            }
        }
    }

    public function down(): void {}
};
