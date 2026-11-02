<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            // Desactualizados que el admin envió a Obsoleto desde la selección del listado: salen
            // de Procedimientos y se consultan en la pantalla de Obsoleto (de ahí se pueden regresar).
            $table->timestamp('obsoleted_at')->nullable()->after('change_summary_uploaded_at');
            $table->foreignId('obsoleted_by')
                  ->nullable()
                  ->after('obsoleted_at')
                  ->constrained('users')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropForeign(['obsoleted_by']);
            $table->dropColumn(['obsoleted_at', 'obsoleted_by']);
        });
    }
};
