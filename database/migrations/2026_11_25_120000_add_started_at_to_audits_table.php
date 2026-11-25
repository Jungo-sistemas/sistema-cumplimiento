<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Iniciar auditoría": el auditor la arranca con un botón (queda la hora) y desde entonces captura
 * el checklist. Las que ya tenían algo capturado se dan por iniciadas para no bloquearlas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->timestamp('started_at')->nullable()->after('scheduled_at');
        });

        $withCapture = DB::table('audit_checklist_items')->whereNotNull('result')->distinct()->pluck('audit_id');
        DB::table('audits')
            ->where('status', 'programada')
            ->whereIn('id', $withCapture)
            ->update(['started_at' => DB::raw('updated_at')]);

        // Las ya finalizadas también empezaron (para el registro).
        DB::table('audits')->where('status', 'realizada')->whereNull('started_at')
            ->update(['started_at' => DB::raw('COALESCE(scheduled_at, completed_at)')]);
    }

    public function down(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn('started_at');
        });
    }
};
