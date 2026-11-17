<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Auditorías del plan anual, vistas desde el auditado (Mi espacio > Mis auditorías).
 *
 * - planned_for: primer día del mes que le toca. Al auditado solo se le avisa el MES; la auditoría
 *   no se le muestra antes de que empiece ese mes.
 * - scheduled_at: día y hora que auditor y auditado acuerdan por fuera (la reunión es por Teams);
 *   el auditor la captura y queda el registro aquí.
 * - El checklist no vive aquí: sale de los procesos mismos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('planned_for');
            $table->dateTime('scheduled_at')->nullable();
            $table->string('status', 20)->default('programada'); // programada | realizada | cancelada
            $table->string('result')->nullable();                // calificación que registra el auditor
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('month_notified_at')->nullable();  // aviso "este mes te toca" ya enviado
            $table->timestamps();

            $table->index(['group_id', 'planned_for']);
            $table->index(['status', 'planned_for']);
        });

        Schema::create('audit_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['audit_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_user');
        Schema::dropIfExists('audits');
    }
};
