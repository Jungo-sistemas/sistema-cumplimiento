<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->morphs('licensable'); // Company o Group
            $table->boolean('includes_procesos')->default(false);
            $table->decimal('price', 10, 2);
            $table->enum('status', ['active', 'expired', 'cancelled'])->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('expires_at');
            $table->timestamp('reminder_7_sent_at')->nullable();
            $table->timestamp('reminder_3_sent_at')->nullable();
            $table->timestamp('reminder_1_sent_at')->nullable();
            $table->foreignId('activated_by')->constrained('users');
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
