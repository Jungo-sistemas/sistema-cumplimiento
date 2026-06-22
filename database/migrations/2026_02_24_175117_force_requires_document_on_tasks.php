<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN requires_document SET DEFAULT true');
        } else {
            Schema::table('tasks', function (Blueprint $table) {
                $table->boolean('requires_document')->default(true)->change();
            });
        }

        DB::table('tasks')->update(['requires_document' => true]);
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE tasks ALTER COLUMN requires_document SET DEFAULT false');
        } else {
            Schema::table('tasks', function (Blueprint $table) {
                $table->boolean('requires_document')->default(false)->change();
            });
        }
    }
};
