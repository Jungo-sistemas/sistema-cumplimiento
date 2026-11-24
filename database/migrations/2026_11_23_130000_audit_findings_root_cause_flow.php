<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Flujo de hallazgos de la lámina del cliente (nov 2026):
 *   auditor documenta → auditado manda causa raíz y acciones → auditor valida (o devuelve) →
 *   auditado ejecuta y sube evidencia (seguimiento) → auditor verifica y cierra (o la rechaza).
 *
 * - Solo dos severidades: critico / no_critico (antes critica / mayor / menor).
 * - due_at pasa a ser el plazo de la etapa actual, en días hábiles (config auditorias.plazos_hallazgos).
 * - audit_finding_submissions guarda las dos entregas: kind = causa (con root_cause y actions) o evidencia.
 * - audit_finding_events: bitácora de cada hallazgo; junto con responsible_id es el historial por
 *   usuario que el auditor consulta en la siguiente auditoría.
 * - users.receives_audit_alerts: quién recibe los avisos de hallazgos críticos y plazos vencidos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->string('status', 30)->default('causa_pendiente')->change();
            $table->timestamp('overdue_notified_at')->nullable()->after('due_at');
        });

        DB::table('audit_findings')->where('severity', 'critica')->update(['severity' => 'critico']);
        DB::table('audit_findings')->where(fn ($q) => $q->whereNull('severity')->orWhereIn('severity', ['mayor', 'menor']))
            ->update(['severity' => 'no_critico']);

        // Los que estaban esperando evidencia todavía no tienen causa raíz: arrancan por ahí.
        foreach ([
            'pendiente'   => 'causa_pendiente',
            'en_revision' => 'evidencia_en_revision',
            'rechazada'   => 'evidencia_rechazada',
        ] as $old => $new) {
            DB::table('audit_findings')->where('status', $old)->update(['status' => $new]);
        }

        Schema::table('audit_finding_submissions', function (Blueprint $table) {
            $table->string('kind', 20)->default('evidencia')->after('user_id'); // causa | evidencia
            $table->text('root_cause')->nullable()->after('kind');
            $table->text('actions')->nullable()->after('root_cause');
        });

        Schema::create('audit_finding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 40);
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->index(['audit_finding_id', 'id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('receives_audit_alerts')->default(false)->after('notification_modules');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('receives_audit_alerts');
        });

        Schema::dropIfExists('audit_finding_events');

        Schema::table('audit_finding_submissions', function (Blueprint $table) {
            $table->dropColumn(['kind', 'root_cause', 'actions']);
        });

        foreach ([
            'causa_pendiente'       => 'pendiente',
            'causa_rechazada'       => 'pendiente',
            'causa_en_revision'     => 'pendiente',
            'evidencia_pendiente'   => 'pendiente',
            'evidencia_en_revision' => 'en_revision',
            'evidencia_rechazada'   => 'rechazada',
        ] as $new => $old) {
            DB::table('audit_findings')->where('status', $new)->update(['status' => $old]);
        }
        DB::table('audit_findings')->where('severity', 'critico')->update(['severity' => 'critica']);
        DB::table('audit_findings')->where('severity', 'no_critico')->update(['severity' => 'mayor']);

        Schema::table('audit_findings', function (Blueprint $table) {
            $table->dropColumn('overdue_notified_at');
            $table->string('status', 20)->default('pendiente')->change();
        });
    }
};
