<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('asset_requirements', 'vault_location')) {
            Schema::table('asset_requirements', function (Blueprint $table) {
                $table->string('vault_location')->nullable()->after('current_document_id');
            });
        }

        if (Schema::hasColumn('assets', 'vault_location')) {
            // Cada normativa puede variar de bóveda; se parte del valor que tenía el activo
            // (portable entre MySQL/Postgres: se resuelve por asset en lugar de un UPDATE...JOIN/FROM).
            DB::table('assets')
                ->whereNotNull('vault_location')
                ->where('vault_location', '!=', '')
                ->orderBy('id')
                ->select('id', 'vault_location')
                ->chunkById(200, function ($assets) {
                    foreach ($assets as $asset) {
                        DB::table('asset_requirements')
                            ->where('asset_id', $asset->id)
                            ->update(['vault_location' => $asset->vault_location]);
                    }
                });

            Schema::table('assets', function (Blueprint $table) {
                $table->dropColumn('vault_location');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('assets', 'vault_location')) {
            Schema::table('assets', function (Blueprint $table) {
                $table->string('vault_location')->nullable();
            });
        }

        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->dropColumn('vault_location');
        });
    }
};
