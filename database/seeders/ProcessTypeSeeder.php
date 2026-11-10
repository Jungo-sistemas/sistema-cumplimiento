<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\ProcessType;
use Illuminate\Database\Seeder;

class ProcessTypeSeeder extends Seeder
{
    // Las carpetas de cada empresa en Procesos, en el orden en que se muestran (nov 2026).
    // DIRECCIÓN no va aquí: es la carpeta confidencial al nivel de las empresas.
    private const TYPES = [
        'LEGAL',
        'AUDITORÍA',
        'FISCAL Y COMPLIANCE',
        'FINANZAS Y TESORERÍA',
        'CRÉDITO Y COBRANZA',
        'RECURSOS HUMANOS',
        'COMPRAS',
        'ALMACÉN E INVENTARIOS',
        'TI',
        'COMERCIAL Y MARKETING',
        'OPERACIONES Y LOGÍSTICA',
        'MANTENIMIENTO',
        'SEGURIDAD',
    ];

    public function run(): void
    {
        foreach (Group::all() as $group) {
            foreach (self::TYPES as $i => $name) {
                ProcessType::firstOrCreate(
                    ['group_id' => $group->id, 'name' => $name],
                    ['sort_order' => $i + 1, 'is_active' => true]
                );
            }
        }
    }
}
