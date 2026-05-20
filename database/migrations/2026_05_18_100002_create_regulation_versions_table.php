<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regulation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('regulation_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->text('change_description')->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('disk')->default('private');
            $table->string('mime_type')->nullable();
            $table->string('responsible_name')->nullable();
            $table->date('issued_at')->nullable();
            $table->date('valid_until')->nullable();
            $table->boolean('is_current')->default(false);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('regulation_versions');
    }
};
