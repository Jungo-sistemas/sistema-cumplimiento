<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            // Impacto para la periodicidad de evaluación (bajo/medio/alto). Independiente de
            // impact_level, que define el flujo de aprobación.
            $table->string('evaluation_impact')->nullable()->after('impact_level');
        });
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropColumn('evaluation_impact');
        });
    }
};
