<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Flujos fijos de aprobación de Anexos 8 por razón social (Gerente → DD → CFO → CEO → Consejero).
     * Los carga `investments:sync-approval-flows` desde database/seeders/data/investment_approval_flows.php.
     *
     * - investment_entities: la lista de razones sociales que se elige al capturar. Se liga a la
     *   empresa del sistema cuando existe (varias razones sociales pueden ser la misma empresa,
     *   p. ej. Parque Estrella e IBPME → BIOMEX); sin empresa, la solicitud queda a nivel grupo.
     * - investment_approval_steps: quién aprueba en cada puesto. user_id queda vacío mientras la
     *   persona no tenga usuario; approver_name guarda el nombre para ligarla después.
     */
    public function up(): void
    {
        Schema::create('investment_entities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('holding', 100)->nullable();   // Tenedora
            $table->string('unit', 100)->nullable();      // Unidad de negocio
            $table->string('name');                       // Razón social
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['group_id', 'name']);
        });

        Schema::create('investment_approval_steps', function (Blueprint $table) {
            $table->id();

            $table->foreignId('investment_entity_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedSmallInteger('sequence_order');
            $table->string('position', 50);
            $table->string('approver_name')->nullable();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->timestamps();

            $table->unique(['investment_entity_id', 'sequence_order']);
        });

        Schema::table('investment_requests', function (Blueprint $table) {
            $table->foreignId('investment_entity_id')->nullable()->after('company_id')
                ->constrained()->nullOnDelete();
        });

        // Puesto con el que aprobó cada persona ("DD", "CFO"…), para mostrarlo en el flujo.
        Schema::table('investment_request_approvals', function (Blueprint $table) {
            $table->string('position', 50)->nullable()->after('sequence_order');
        });
    }

    public function down(): void
    {
        Schema::table('investment_request_approvals', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('investment_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('investment_entity_id');
        });

        Schema::dropIfExists('investment_approval_steps');
        Schema::dropIfExists('investment_entities');
    }
};
