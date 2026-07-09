<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('requirement_templates', 'subtype')) {
            Schema::table('requirement_templates', function (Blueprint $table) {
                $table->string('subtype', 30)->nullable()->after('category');
            });
        }
    }

    public function down(): void
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->dropColumn('subtype');
        });
    }
};
