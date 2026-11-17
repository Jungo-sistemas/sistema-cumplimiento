<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hallazgos de auditoría, vistos desde el auditado (Mi espacio > Mis hallazgos).
 *
 * El auditor los registra durante la auditoría en vivo, en los puntos del proceso donde algo falla
 * o está incompleto, y elige a un responsable entre los auditados. Para el responsable es una
 * tarea: sube evidencia (hasta 5 archivos) y el auditor la acepta (se cierra) o la rechaza con
 * motivo (vuelve a subir). No hay plan de acción: solo evidencia.
 *
 * - due_at: fecha límite. Saldrá de la severidad, pero esos plazos se definen del lado del
 *   auditor — por ahora puede venir vacía.
 * - audit_finding_submissions: cada entrega de evidencia con su revisión — es también el
 *   historial que ve el auditado (motivos de rechazo incluidos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_findings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('point');                        // punto del proceso / checklist
            $table->text('description');                    // lo que anotó el auditor
            $table->string('severity', 20)->nullable();     // critica | mayor | menor (plazos por definir)
            $table->date('due_at')->nullable();
            $table->string('status', 20)->default('pendiente'); // pendiente | en_revision | rechazada | cerrada
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();

            $table->index(['responsible_id', 'status']);
        });

        Schema::create('audit_finding_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_finding_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('comment')->nullable();
            $table->string('status', 20)->default('en_revision'); // en_revision | aceptada | rechazada
            $table->text('review_comment')->nullable();          // motivo del rechazo
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_finding_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_finding_submission_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_finding_files');
        Schema::dropIfExists('audit_finding_submissions');
        Schema::dropIfExists('audit_findings');
    }
};
