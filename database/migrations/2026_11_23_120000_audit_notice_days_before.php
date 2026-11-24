<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El auditado ya no se entera por el mes: el auditor fija la fecha y el aviso le llega unos días
 * antes (config auditorias.aviso_dias_antes). month_notified_at (aviso "este mes te toca") se
 * sustituye por notice_sent_at (aviso de la fecha ya enviado). No se copian los valores viejos: ese
 * aviso no decía la fecha, así que las auditorías ya avisadas por mes recibirán el nuevo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->timestamp('notice_sent_at')->nullable()->after('completed_at');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('month_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->timestamp('month_notified_at')->nullable()->after('completed_at');
        });

        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('notice_sent_at');
        });
    }
};
