<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_job_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('job_position_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'job_position_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_job_positions');
    }
};
