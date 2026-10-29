<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            // Documento con el resumen de cambios que un administrador sube desde "Mis aprobaciones"
            // para guiar a los aprobadores; uno por proceso, se reemplaza al subir otro.
            $table->string('change_summary_path')->nullable()->after('public_share_token');
            $table->string('change_summary_original_name')->nullable()->after('change_summary_path');
            $table->string('change_summary_mime_type')->nullable()->after('change_summary_original_name');
            $table->foreignId('change_summary_uploaded_by')
                  ->nullable()
                  ->after('change_summary_mime_type')
                  ->constrained('users')
                  ->nullOnDelete();
            $table->timestamp('change_summary_uploaded_at')->nullable()->after('change_summary_uploaded_by');
        });
    }

    public function down(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            $table->dropForeign(['change_summary_uploaded_by']);
            $table->dropColumn([
                'change_summary_path',
                'change_summary_original_name',
                'change_summary_mime_type',
                'change_summary_uploaded_by',
                'change_summary_uploaded_at',
            ]);
        });
    }
};
