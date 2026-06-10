<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            // Drop both existing unique indexes on (name, asset_type_id, compliance_scope)
            $table->dropUnique('requirement_templates_name_asset_type_scope_unique');
            $table->dropUnique('requirement_templates_unique_name_asset_scope');

            // Add category: expediente | alta | modificacion | baja
            $table->string('category')->default('expediente')->after('compliance_scope');

            // New unique index includes category
            $table->unique(
                ['name', 'asset_type_id', 'compliance_scope', 'category'],
                'requirement_templates_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('requirement_templates', function (Blueprint $table) {
            $table->dropUnique('requirement_templates_unique');
            $table->dropColumn('category');

            $table->unique(
                ['name', 'asset_type_id', 'compliance_scope'],
                'requirement_templates_unique_name_asset_scope'
            );
            $table->unique(
                ['name', 'asset_type_id', 'compliance_scope'],
                'requirement_templates_name_asset_type_scope_unique'
            );
        });
    }
};
