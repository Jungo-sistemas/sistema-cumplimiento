<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->string('impact_level')->nullable()->after('is_active');
            $table->string('approval_status')->default('pending_review')->after('impact_level');
        });
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropColumn(['impact_level', 'approval_status']);
        });
    }
};
