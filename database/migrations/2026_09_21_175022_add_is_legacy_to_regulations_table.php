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
        Schema::table('regulations', function (Blueprint $table) {
            // Documento histórico cargado tal cual (carga masiva de procedimientos antiguos, ver
            // regulations:import-legacy) — solo puede verse, no editarse (ni con el editor en
            // línea ni "Info básica") hasta que alguien lo actualice con el wizard o subiendo una
            // versión nueva, momento en el que se apaga automáticamente.
            $table->boolean('is_legacy')->default(false)->after('is_annex');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropColumn('is_legacy');
        });
    }
};
