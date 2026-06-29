<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $groups = DB::table('groups')->get(['id']);

        foreach ($groups as $group) {
            $exists = DB::table('document_folders')
                ->where('group_id', $group->id)
                ->whereNull('company_id')
                ->whereNull('parent_id')
                ->where('name', 'En trámite y regularización')
                ->exists();

            if (! $exists) {
                $maxSort = DB::table('document_folders')
                    ->where('group_id', $group->id)
                    ->whereNull('company_id')
                    ->whereNull('parent_id')
                    ->max('sort_order') ?? 0;

                DB::table('document_folders')->insert([
                    'group_id'   => $group->id,
                    'company_id' => null,
                    'parent_id'  => null,
                    'name'       => 'En trámite y regularización',
                    'level'      => 'folder',
                    'sort_order' => $maxSort + 1,
                    'is_active'  => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        DB::table('document_folders')
            ->whereNull('company_id')
            ->whereNull('parent_id')
            ->where('name', 'En trámite y regularización')
            ->delete();
    }
};
