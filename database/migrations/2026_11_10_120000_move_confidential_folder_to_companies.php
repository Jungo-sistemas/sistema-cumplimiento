<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La carpeta confidencial "Dirección" va al nivel de las empresas, no de los tipos de proceso
 * (lo aclaró el cliente el 2026-11-10). Cada grupo con Procesos tiene una empresa DIRECCIÓN con
 * is_restricted (solo Consejo y los admins de solo Procesos la ven), y dentro tiene las mismas
 * carpetas por tipo que las demás. El tipo 01 DIRECCIÓN queda como un tipo normal. Los procesos
 * que ya estaban en el tipo confidencial se pasan a la empresa DIRECCIÓN, para que no queden
 * a la vista de todos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('is_restricted')->default(false)->after('show_in_processes');
        });

        $now = now();

        foreach (DB::table('process_types')->distinct()->pluck('group_id') as $groupId) {
            $companyId = DB::table('companies')
                ->where('group_id', $groupId)
                ->whereRaw('UPPER(TRIM(name)) IN (?, ?)', ['DIRECCIÓN', 'DIRECCION'])
                ->value('id');

            if ($companyId) {
                DB::table('companies')->where('id', $companyId)
                    ->update(['is_restricted' => true, 'show_in_processes' => true, 'otras' => false, 'updated_at' => $now]);
            } else {
                $companyId = DB::table('companies')->insertGetId([
                    'name' => 'DIRECCIÓN', 'group_id' => $groupId, 'show_in_processes' => true, 'is_restricted' => true,
                    'otras' => false, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }

            $restrictedTypeIds = DB::table('process_types')->where('group_id', $groupId)->where('is_restricted', true)->pluck('id');
            DB::table('regulations')->whereIn('process_type_id', $restrictedTypeIds)
                ->update(['company_id' => $companyId, 'updated_at' => $now]);
        }

        Schema::table('process_types', function (Blueprint $table) {
            $table->dropColumn('is_restricted');
        });
    }

    public function down(): void
    {
        Schema::table('process_types', function (Blueprint $table) {
            $table->boolean('is_restricted')->default(false)->after('is_active');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('is_restricted');
        });
    }
};
