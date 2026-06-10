<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop old indexes safely — production may only have one of them
        foreach ([
            'requirement_templates_name_asset_type_scope_unique',
            'requirement_templates_unique_name_asset_scope',
        ] as $index) {
            try {
                Schema::table('requirement_templates', fn (Blueprint $t) => $t->dropUnique($index));
            } catch (\Exception $e) {
                // Index didn't exist — skip
            }
        }

        Schema::table('requirement_templates', function (Blueprint $table) {
            // varchar(50) keeps the index within MySQL's 3072-byte limit
            $table->string('category', 50)->default('expediente')->after('compliance_scope');
        });

        // MySQL: name is varchar(500) so we need a prefix index to stay within 3072 bytes
        // PostgreSQL: no length limit on index keys, use standard unique index
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'CREATE UNIQUE INDEX requirement_templates_unique
                 ON requirement_templates (name(80), asset_type_id, compliance_scope, category)'
            );
        } else {
            Schema::table('requirement_templates', function (Blueprint $table) {
                $table->unique(
                    ['name', 'asset_type_id', 'compliance_scope', 'category'],
                    'requirement_templates_unique'
                );
            });
        }
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
