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
            DB::statement('ALTER TABLE regulations ALTER COLUMN approval_status DROP NOT NULL');
        } else {
            Schema::table('regulations', function (Blueprint $table) {
                $table->string('approval_status')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE regulations ALTER COLUMN approval_status SET NOT NULL');
        } else {
            Schema::table('regulations', function (Blueprint $table) {
                $table->string('approval_status')->nullable(false)->change();
            });
        }
    }
};
