<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Catálogo de tipos de proceso que pidió el cliente (nov 2026). Los tipos son las carpetas de
 * cada empresa en Procesos. Los tipos que ya existían se renombran (sus procesos se quedan en
 * la misma carpeta), los nuevos se crean y los que no están en la lista se ocultan (is_active
 * = false; no se borran para no perder el tipo de los procesos que ya lo tienen).
 * "Dirección" sigue siendo la carpeta confidencial (is_restricted).
 */
return new class extends Migration
{
    /** Orden => [nombre nuevo, nombres anteriores que se renombran a este]. */
    private const CATALOG = [
        1  => ['DIRECCIÓN', ['Dirección']],
        2  => ['LEGAL', []],
        3  => ['AUDITORÍA', []],
        4  => ['FISCAL Y COMPLIANCE', []],
        5  => ['FINANZAS Y TESORERÍA', ['Finanzas']],
        6  => ['CRÉDITO Y COBRANZA', []],
        7  => ['RECURSOS HUMANOS', ['Recursos Humanos']],
        8  => ['COMPRAS', ['Compras']],
        9  => ['ALMACÉN E INVENTARIOS', []],
        10 => ['TI', ['TI e Información']],
        11 => ['COMERCIAL Y MARKETING', ['Comercial']],
        12 => ['OPERACIONES Y LOGÍSTICA', ['Operaciones']],
        13 => ['MANTENIMIENTO', ['Mantenimiento']],
        14 => ['SEGURIDAD', ['Seguridad']],
    ];

    public function up(): void
    {
        $now = now();

        foreach (DB::table('groups')->pluck('id') as $groupId) {
            $types   = DB::table('process_types')->where('group_id', $groupId)->get();
            $keepIds = [];

            foreach (self::CATALOG as $order => [$name, $previous]) {
                $names    = array_map(fn ($n) => mb_strtoupper($n), [$name, ...$previous]);
                $existing = $types->first(fn ($t) => in_array(mb_strtoupper(trim($t->name)), $names, true) && ! in_array($t->id, $keepIds, true));

                $values = [
                    'name'          => $name,
                    'sort_order'    => $order,
                    'is_active'     => true,
                    'is_restricted' => $name === 'DIRECCIÓN',
                    'updated_at'    => $now,
                ];

                if ($existing) {
                    DB::table('process_types')->where('id', $existing->id)->update($values);
                    $keepIds[] = $existing->id;
                } else {
                    $keepIds[] = DB::table('process_types')->insertGetId($values + ['group_id' => $groupId, 'created_at' => $now]);
                }
            }

            DB::table('process_types')
                ->where('group_id', $groupId)
                ->whereNotIn('id', $keepIds)
                ->update(['is_active' => false, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        // Sin vuelta atrás automática: los nombres anteriores se pueden poner a mano si hiciera falta.
    }
};
