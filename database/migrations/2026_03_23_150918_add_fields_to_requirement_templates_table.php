<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->foreignId('asset_type_id')
                ->nullable()
                ->constrained('asset_types')
                ->nullOnDelete();

            $table->string('compliance_scope')
                ->default('project')
                ->after('description');
        });
    }

    public function down()
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->dropForeign(['asset_type_id']);
            $table->dropColumn(['asset_type_id', 'compliance_scope']);
        });
    }
};