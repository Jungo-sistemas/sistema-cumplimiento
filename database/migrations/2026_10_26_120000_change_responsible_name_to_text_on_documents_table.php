<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un documento general puede tener varios responsables (se guardan separados por coma),
     * así que el campo deja de estar limitado a 255 caracteres.
     */
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->text('responsible_name')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('responsible_name')->nullable()->change();
        });
    }
};
