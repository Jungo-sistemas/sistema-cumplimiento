<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_annexes', function (Blueprint $table) {
            $table->unsignedBigInteger('regulation_id');
            $table->unsignedBigInteger('annexed_regulation_id');

            $table->primary(['regulation_id', 'annexed_regulation_id']);

            $table->foreign('regulation_id')
                ->references('id')->on('regulations')
                ->onDelete('cascade');

            $table->foreign('annexed_regulation_id')
                ->references('id')->on('regulations')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_annexes');
    }
};
