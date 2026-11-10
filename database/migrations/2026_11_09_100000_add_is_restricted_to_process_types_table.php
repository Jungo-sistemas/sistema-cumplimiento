<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('process_types', function (Blueprint $table) {
            // Tipo "confidencial": sus procesos solo los ven Consejo y los admins de solo Procesos
            // (User::canSeeRestrictedProcesses()).
            $table->boolean('is_restricted')->default(false)->after('is_active');
        });

        // Cada grupo con tipos de proceso recibe el tipo "Dirección" restringido.
        foreach (DB::table('process_types')->distinct()->pluck('group_id') as $groupId) {
            $exists = DB::table('process_types')->where('group_id', $groupId)->where('name', 'Dirección')->exists();

            if ($exists) {
                DB::table('process_types')->where('group_id', $groupId)->where('name', 'Dirección')->update(['is_restricted' => true]);
                continue;
            }

            DB::table('process_types')->insert([
                'group_id'      => $groupId,
                'name'          => 'Dirección',
                'sort_order'    => (int) DB::table('process_types')->where('group_id', $groupId)->max('sort_order') + 1,
                'is_active'     => true,
                'is_restricted' => true,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('process_types', function (Blueprint $table) {
            $table->dropColumn('is_restricted');
        });
    }
};
