<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->unsignedInteger('asset_limit')->nullable()->after('is_active');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->unsignedInteger('asset_limit')->nullable()->after('show_in_processes');
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('asset_limit');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('asset_limit');
        });
    }
};
