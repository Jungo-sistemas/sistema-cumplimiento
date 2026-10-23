<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ubicación física exacta (bodega) donde se guarda el documento original de cada
     * requerimiento, para que cualquiera sepa dónde encontrarlo.
     */
    public function up(): void
    {
        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->string('bodega')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('asset_requirements', function (Blueprint $table) {
            $table->dropColumn('bodega');
        });
    }
};
