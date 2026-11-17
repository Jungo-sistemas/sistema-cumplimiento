<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('regulations', function (Blueprint $table) {
            // Desactualizado que se reemplazó al cargar su versión nueva: se va a Obsoleto y apunta
            // al documento que lo sustituye (y ya no se puede regresar a Procedimientos).
            $table->foreignId('replaced_by_id')->nullable()->after('obsoleted_by')
                ->constrained('regulations')->nullOnDelete();
        });

        // Códigos sin espacios sobrantes antes de compararlos.
        DB::statement("UPDATE regulations SET code = NULLIF(UPPER(TRIM(code)), '') WHERE code IS NOT NULL");

        // El código es único dentro del grupo entre los documentos "vivos": los de la papelera y los
        // de Obsoleto no cuentan (un reemplazado conserva el mismo código que su versión nueva).
        $live = fn () => DB::table('regulations')
            ->whereNotNull('code')->whereNull('deleted_at')->whereNull('obsoleted_at');

        $duplicates = $live()->select('group_id', 'code')
            ->groupBy('group_id', 'code')->havingRaw('COUNT(*) > 1')->get();

        if ($duplicates->isNotEmpty()) {
            $list = $duplicates->map(fn ($d) => "{$d->code} (ids " . $live()
                ->where('group_id', $d->group_id)->where('code', $d->code)->orderBy('id')->pluck('id')->implode(', ') . ')')
                ->implode('; ');

            throw new RuntimeException("Hay códigos de procesos repetidos; corrígelos antes de migrar: {$list}");
        }

        DB::statement('
            CREATE UNIQUE INDEX regulations_group_code_unique
            ON regulations (group_id, code)
            WHERE code IS NOT NULL AND deleted_at IS NULL AND obsoleted_at IS NULL
        ');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS regulations_group_code_unique');

        Schema::table('regulations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('replaced_by_id');
        });
    }
};
