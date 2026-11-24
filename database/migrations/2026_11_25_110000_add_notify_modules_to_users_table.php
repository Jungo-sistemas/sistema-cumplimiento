<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De qué módulos del portal recibe avisos de administración (lista de claves de PortalModules).
 * null = la preferencia por área de antes (notification_modules).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('notify_modules')->nullable()->after('notification_modules');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notify_modules');
        });
    }
};
