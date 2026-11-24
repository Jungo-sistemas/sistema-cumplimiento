<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lado del auditor (nov 2026): el checklist que captura durante la llamada de Teams.
 *
 * - audit_checklist_items: los criterios de UNA auditoría. Al abrir el checklist se copian del
 *   proceso (pregunta fija + un criterio por cada "Paso N." de la versión vigente); el auditor puede
 *   agregar los suyos. Cada uno guarda su resultado, observaciones y los hallazgos en borrador
 *   (draft_findings), que se vuelven hallazgos reales al finalizar.
 * - audit_checklist_files: soporte de la revisión de cada criterio (archivos o fotos).
 * - audits: calificación numérica, conclusiones y quién asistió (ids de usuario).
 * - audit_findings.audit_checklist_item_id: criterio donde se registró el hallazgo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audits', function (Blueprint $table) {
            $table->unsignedTinyInteger('score')->nullable()->after('result');
            $table->text('conclusions')->nullable()->after('score');
            $table->json('attendance')->nullable()->after('conclusions');
        });

        Schema::create('audit_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('source', 20)->default('auditor'); // fija | proceso | auditor
            $table->text('text');
            $table->text('description')->nullable();
            $table->text('evidence')->nullable();
            $table->json('activity')->nullable();               // paso del procedimiento con el que se evalúa
            $table->string('result', 20)->nullable();           // cumple | parcial | no_cumple | no_aplica
            $table->text('observations')->nullable();
            $table->json('draft_findings')->nullable();
            $table->timestamps();

            $table->index(['audit_id', 'position']);
        });

        Schema::create('audit_checklist_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('audit_checklist_item_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->timestamps();
        });

        Schema::table('audit_findings', function (Blueprint $table) {
            $table->foreignId('audit_checklist_item_id')->nullable()->after('audit_id')
                ->constrained('audit_checklist_items')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('audit_findings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('audit_checklist_item_id');
        });
        Schema::dropIfExists('audit_checklist_files');
        Schema::dropIfExists('audit_checklist_items');
        Schema::table('audits', function (Blueprint $table) {
            $table->dropColumn(['score', 'conclusions', 'attendance']);
        });
    }
};
