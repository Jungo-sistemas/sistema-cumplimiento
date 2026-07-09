<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('asset_requirements', 'vault_location')) {
            // La bóveda documental era en realidad el detalle de dirección que faltaba
            // en "ubicación" (la gente lo usaba como campo libre de dirección). Se
            // traslada tal cual a street_address del activo y se elimina la bóveda.
            DB::table('asset_requirements')
                ->whereNotNull('vault_location')
                ->where('vault_location', '!=', '')
                ->orderBy('id')
                ->select('id', 'asset_id', 'vault_location')
                ->chunkById(200, function ($requirements) {
                    foreach ($requirements as $requirement) {
                        DB::table('assets')
                            ->where('id', $requirement->asset_id)
                            ->where(function ($query) {
                                $query->whereNull('street_address')->orWhere('street_address', '');
                            })
                            ->update(['street_address' => $requirement->vault_location]);
                    }
                });

            Schema::table('asset_requirements', function (Blueprint $table) {
                $table->dropColumn('vault_location');
            });
        }
    }

    public function down(): void
    {
        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->string('vault_location')->nullable()->after('current_document_id');
        });
    }
};
