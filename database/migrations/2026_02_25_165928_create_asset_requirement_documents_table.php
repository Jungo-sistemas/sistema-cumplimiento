<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('asset_requirement_documents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('asset_requirement_id')
                ->constrained('asset_requirements')
                ->cascadeOnDelete();

            // para reforzar multiempresa y consultas rápidas
            $table->foreignId('company_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->string('file_path');
            $table->string('original_name')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();

            $table->foreignId('uploaded_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->index(['asset_requirement_id', 'company_id'], 'ard_requirement_company_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_requirement_documents');
    }
};