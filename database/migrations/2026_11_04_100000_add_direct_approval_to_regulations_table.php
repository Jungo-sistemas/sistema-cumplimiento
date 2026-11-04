<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            // Último "Aprobado (sin flujo)" que hizo un admin: quién y cuándo.
            $table->foreignId('direct_approved_by')->nullable()->after('approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('direct_approved_at')->nullable()->after('direct_approved_by');
        });
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('direct_approved_by');
            $table->dropColumn('direct_approved_at');
        });
    }
};
