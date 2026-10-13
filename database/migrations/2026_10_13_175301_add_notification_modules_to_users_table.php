<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // null = recibe avisos de ambas áreas (comportamiento actual, sin cambios para nadie
            // hasta que un superadmin lo configure explícitamente) · 'cumplimiento' · 'procesos' · 'all'
            $table->string('notification_modules', 20)->nullable()->after('module_access');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_modules');
        });
    }
};
