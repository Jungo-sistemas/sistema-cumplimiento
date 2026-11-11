<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * DIRECCIÓN ya no es un tipo de proceso: es solo la carpeta confidencial al nivel de las
 * empresas (companies.is_restricted). El tipo se oculta (no se borra) y los demás se renumeran
 * del 01 al 13 para que las carpetas no empiecen en 02.
 */
return new class extends Migration
{
    private const ORDER = [
        'LEGAL', 'AUDITORÍA', 'FISCAL Y COMPLIANCE', 'FINANZAS Y TESORERÍA', 'CRÉDITO Y COBRANZA',
        'RECURSOS HUMANOS', 'COMPRAS', 'ALMACÉN E INVENTARIOS', 'TI', 'COMERCIAL Y MARKETING',
        'OPERACIONES Y LOGÍSTICA', 'MANTENIMIENTO', 'SEGURIDAD',
    ];

    public function up(): void
    {
        DB::table('process_types')->where('name', 'DIRECCIÓN')->update(['is_active' => false, 'updated_at' => now()]);

        foreach (self::ORDER as $i => $name) {
            DB::table('process_types')->where('name', $name)->update(['sort_order' => $i + 1]);
        }
    }

    public function down(): void
    {
        DB::table('process_types')->where('name', 'DIRECCIÓN')->update(['is_active' => true, 'sort_order' => 1]);

        foreach (self::ORDER as $i => $name) {
            DB::table('process_types')->where('name', $name)->update(['sort_order' => $i + 2]);
        }
    }
};
