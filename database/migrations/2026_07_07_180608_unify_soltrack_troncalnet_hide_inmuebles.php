<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Rename SOLTRACK → "SOLTRACK / TRONCALNET"
        DB::table('companies')->where('name', 'SOLTRACK')->update(['name' => 'SOLTRACK / TRONCALNET']);

        // Move any existing regulations from TRONCALNET to the unified company
        $unifiedId = DB::table('companies')->where('name', 'SOLTRACK / TRONCALNET')->value('id');
        $tronId    = DB::table('companies')->where('name', 'TRONCALNET')->value('id');

        if ($unifiedId && $tronId) {
            DB::table('regulations')->where('company_id', $tronId)->update(['company_id' => $unifiedId]);
        }

        // Hide TRONCALNET (keep the row to preserve FK integrity in other tables)
        DB::table('companies')->where('name', 'TRONCALNET')->update(['show_in_processes' => false]);

        // INMUEBLES is already show_in_processes = false — no action needed
    }

    public function down(): void
    {
        DB::table('companies')->where('name', 'SOLTRACK / TRONCALNET')->update(['name' => 'SOLTRACK']);
        DB::table('companies')->where('name', 'TRONCALNET')->update(['show_in_processes' => true]);
        // Note: regulations migrated from TRONCALNET are not reversed automatically
    }
};
