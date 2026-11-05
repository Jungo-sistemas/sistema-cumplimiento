<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Evidencias de apoyo de la síntesis de cambios (p. ej. capturas de quienes ya aprobaron, para
     * reconfigurar el flujo solo con los que faltan). Máximo Regulation::MAX_CHANGE_EVIDENCES.
     */
    public function up(): void
    {
        Schema::create('regulation_change_evidences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_change_evidences');
    }
};
