<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Puesto "Consejo": aprueba en el mismo paso que Dirección y es quien ve los procesos
     * confidenciales (User::canSeeRestrictedProcesses()). Se crea en cada grupo que ya tiene puestos.
     */
    public function up(): void
    {
        foreach (DB::table('job_positions')->distinct()->pluck('group_id') as $groupId) {
            if (DB::table('job_positions')->where('group_id', $groupId)->where('slug', 'consejo')->exists()) {
                continue;
            }

            DB::table('job_positions')->insert([
                'group_id'   => $groupId,
                'slug'       => 'consejo',
                'name'       => 'Consejo',
                'sort_order' => 5,
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $ids = DB::table('job_positions')->where('slug', 'consejo')->pluck('id');

        DB::table('user_job_positions')->whereIn('job_position_id', $ids)->delete();
        DB::table('job_positions')->whereIn('id', $ids)->delete();
    }
};
