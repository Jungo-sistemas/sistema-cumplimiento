<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->foreignId('deleted_by')
                  ->nullable()
                  ->after('uploaded_by')
                  ->constrained('users')
                  ->nullOnDelete();

            $table->timestamp('permanently_delete_at')
                  ->nullable()
                  ->after('deleted_by');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropForeign(['deleted_by']);
            $table->dropColumn(['deleted_by', 'permanently_delete_at']);
        });
    }
};
