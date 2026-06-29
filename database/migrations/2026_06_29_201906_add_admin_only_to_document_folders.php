<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_folders', function (Blueprint $table) {
            $table->boolean('admin_only')->default(false)->after('is_active');
        });

        // Insert "Obsoleto" folder for each existing group
        $groups = DB::table('groups')->pluck('id');

        foreach ($groups as $groupId) {
            $exists = DB::table('document_folders')
                ->where('group_id', $groupId)
                ->where('name', 'Obsoleto')
                ->whereNull('parent_id')
                ->whereNull('company_id')
                ->exists();

            if (! $exists) {
                $maxSort = DB::table('document_folders')
                    ->where('group_id', $groupId)
                    ->whereNull('parent_id')
                    ->whereNull('company_id')
                    ->max('sort_order') ?? 0;

                DB::table('document_folders')->insert([
                    'group_id'   => $groupId,
                    'company_id' => null,
                    'parent_id'  => null,
                    'name'       => 'Obsoleto',
                    'level'      => 'folder',
                    'sort_order' => $maxSort + 1,
                    'is_active'  => true,
                    'admin_only' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_folders')
            ->where('name', 'Obsoleto')
            ->whereNull('parent_id')
            ->whereNull('company_id')
            ->delete();

        Schema::table('document_folders', function (Blueprint $table) {
            $table->dropColumn('admin_only');
        });
    }
};
