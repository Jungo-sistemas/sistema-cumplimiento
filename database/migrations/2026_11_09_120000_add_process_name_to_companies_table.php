<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Nombre con el que la empresa aparece solo en Procesos (p. ej. VIDENCI → SAVSA). Vacío =
            // el nombre normal. Cumplimiento siempre usa `name`. Ver Company::$procesos_name.
            $table->string('process_name')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('process_name');
        });
    }
};
