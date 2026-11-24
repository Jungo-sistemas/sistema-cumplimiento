<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El auditor solo entra a Procesos y Auditorías. Los auditores dados de alta antes de que el
 * sistema lo forzara (User::booted) pueden haber quedado con "Ambos módulos" o "Solo
 * Cumplimiento": se dejan en Procesos para que la pantalla de Usuarios muestre lo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        $auditorRoleIds = DB::table('roles')->where('slug', 'auditor')->pluck('id');

        if ($auditorRoleIds->isNotEmpty()) {
            DB::table('users')
                ->whereIn('role_id', $auditorRoleIds)
                ->where(fn ($q) => $q->whereNull('module_access')->orWhere('module_access', '!=', 'procesos'))
                ->update(['module_access' => 'procesos']);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás: no se sabe qué tenía cada uno.
    }
};
