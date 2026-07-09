<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            if (! Schema::hasColumn('assets', 'street_address')) {
                $table->string('street_address')->nullable()->after('location');
            }
            if (! Schema::hasColumn('assets', 'colonia')) {
                $table->string('colonia')->nullable()->after('street_address');
            }
            if (! Schema::hasColumn('assets', 'municipality')) {
                $table->string('municipality')->nullable()->after('colonia');
            }
            if (! Schema::hasColumn('assets', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('municipality');
            }
        });
    }

    public function down(): void
    {
        Schema::table('assets', function (Blueprint $table) {
            $table->dropColumn(['street_address', 'colonia', 'municipality', 'postal_code']);
        });
    }
};
