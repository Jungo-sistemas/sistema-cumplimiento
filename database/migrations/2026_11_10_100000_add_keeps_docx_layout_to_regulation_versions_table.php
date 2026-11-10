<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            // La versión se guardó parchando el .docx anterior (DocxLayoutPatcher), no
            // reconstruyéndolo desde el HTML: su formato es el del Word, así que "Ver" muestra el
            // .docx real y la siguiente edición vuelve a usarlo como guía.
            $table->boolean('keeps_docx_layout')->default(false)->after('body_html');
        });
    }

    public function down(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            $table->dropColumn('keeps_docx_layout');
        });
    }
};
