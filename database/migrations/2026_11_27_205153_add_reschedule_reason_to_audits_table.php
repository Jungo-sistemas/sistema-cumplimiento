<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * "Reprogramar" pide el motivo: les llega a los auditados en el aviso del cambio y queda en la
 * auditoría para consulta. Solo se guarda el de la última reprogramación.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->text('reschedule_reason')->nullable()->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('reschedule_reason');
        });
    }
};
