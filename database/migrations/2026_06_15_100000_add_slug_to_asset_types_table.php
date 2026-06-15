<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->string('slug', 100)->nullable()->after('name');
        });

        // Auto-generar slug desde el nombre para registros existentes
        DB::table('asset_types')->get()->each(function ($type) {
            DB::table('asset_types')
                ->where('id', $type->id)
                ->update(['slug' => Str::slug($type->name)]);
        });
    }

    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropColumn('slug');
        });
    }
};
