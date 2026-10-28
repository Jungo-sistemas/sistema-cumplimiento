<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flujo de aprobación de una solicitud de inversión: las personas que se eligieron al crearla,
     * en orden. Solo una está "pending" a la vez; las siguientes esperan su turno en "waiting".
     */
    public function up(): void
    {
        Schema::create('investment_request_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investment_request_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence_order');

            // waiting | pending | approved | rejected | cancelled
            $table->string('status')->default('waiting');
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['investment_request_id', 'sequence_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investment_request_approvals');
    }
};
