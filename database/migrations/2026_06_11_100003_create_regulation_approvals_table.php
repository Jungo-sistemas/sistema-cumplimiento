<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->foreignId('job_position_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // false = OR logic: en cuanto uno aprueba en el paso, los demás se cancelan
            $table->boolean('requires_all')->default(true);
            $table->string('status')->default('pending'); // pending, approved, rejected, cancelled
            $table->text('comments')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->index(['regulation_id', 'step_number']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_approvals');
    }
};
