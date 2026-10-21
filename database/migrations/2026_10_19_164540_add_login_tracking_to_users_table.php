<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('login_count')->default(0)->after('status');
            $table->timestamp('last_login_at')->nullable()->after('login_count');
            // null = todavía no termina la guía de Procesos (obligatoria la primera vez que
            // entra al módulo); una vez completada, los recordatorios posteriores son opcionales.
            $table->timestamp('procesos_guide_completed_at')->nullable()->after('last_login_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['login_count', 'last_login_at', 'procesos_guide_completed_at']);
        });
    }
};
