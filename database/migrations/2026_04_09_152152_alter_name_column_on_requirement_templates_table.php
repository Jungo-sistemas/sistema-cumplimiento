<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE requirement_templates MODIFY COLUMN name VARCHAR(500)');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE requirement_templates MODIFY COLUMN name VARCHAR(255)');
    }
};