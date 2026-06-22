<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Avoid Doctrine DBAL table-resolution bugs on PostgreSQL; use raw SQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN company_id DROP NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN company_id SET NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('company_id')->nullable(false)->change();
            });
        }
    }
};
