<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rol "Jefe de Auditoría" (nov 2026): planea el programa anual de auditorías y asigna a los
 * auditores. Va en una migración, igual que superadmin, para que llegue a producción con
 * "php artisan migrate" sin tener que correr RoleSeeder a mano.
 */
return new class extends Migration
{
    public function up(): void
    {
        // insertOrIgnore: si el rol ya existe (alguien corrió antes RoleSeeder), no truena.
        DB::table('roles')->insertOrIgnore([
            'name'       => 'Jefe de Auditoría',
            'slug'       => 'jefe_auditoria',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('roles')->where('slug', 'jefe_auditoria')->delete();
    }
};
