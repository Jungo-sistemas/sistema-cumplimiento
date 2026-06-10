<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            // Campos específicos para activos tipo vehículo (ATQ, Tracto, Semirremolque, Carro tanque, Cilindrera)
            $table->string('no_economico')->nullable()->after('vault_location');
            $table->string('numero_serie')->nullable()->after('no_economico');
            $table->string('marca')->nullable()->after('numero_serie');
            $table->string('modelo')->nullable()->after('marca');
            $table->string('placas')->nullable()->after('modelo');
            $table->string('marca_recipiente')->nullable()->after('placas');
            $table->unsignedInteger('capacidad_litros')->nullable()->after('marca_recipiente');
            $table->string('serie_recipiente')->nullable()->after('capacidad_litros');
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn([
                'no_economico',
                'numero_serie',
                'marca',
                'modelo',
                'placas',
                'marca_recipiente',
                'capacidad_litros',
                'serie_recipiente',
            ]);
        });
    }
};
