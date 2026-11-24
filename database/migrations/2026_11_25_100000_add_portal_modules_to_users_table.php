<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Módulos del portal que ve cada usuario fuera de Procesos y Auditorías (Fiscal, Regulatorio,
 * Objetivos, Flujos, Cursos). null = todos los de su área, así los usuarios de antes no cambian.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('portal_modules')->nullable()->after('module_access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('portal_modules');
        });
    }
};
