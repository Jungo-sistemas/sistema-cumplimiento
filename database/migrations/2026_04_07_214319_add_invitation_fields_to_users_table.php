<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable()->change();
            });
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('status')->default('invited')->after('role_id');
            $table->string('invite_token', 64)->nullable()->unique()->after('status');
            $table->timestamp('invite_expires_at')->nullable()->after('invite_token');
            $table->timestamp('invitation_accepted_at')->nullable()->after('invite_expires_at');
            $table->foreignId('invited_by')->nullable()->after('invitation_accepted_at')
                ->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('invited_by');
            $table->dropColumn([
                'status',
                'invite_token',
                'invite_expires_at',
                'invitation_accepted_at',
            ]);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password SET NOT NULL');
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->string('password')->nullable(false)->change();
            });
        }
    }
};
