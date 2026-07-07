<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            $table->unsignedBigInteger('editing_by')->nullable()->after('uploaded_by');
            $table->timestamp('editing_expires_at')->nullable()->after('editing_by');
            $table->longText('draft_html')->nullable()->after('editing_expires_at');
            $table->timestamp('draft_saved_at')->nullable()->after('draft_html');

            $table->foreign('editing_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            $table->dropForeign(['editing_by']);
            $table->dropColumn(['editing_by', 'editing_expires_at', 'draft_html', 'draft_saved_at']);
        });
    }
};
