<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\ProcessType;
use Illuminate\Database\Seeder;

class ProcessTypeSeeder extends Seeder
{
    private const TYPES = [
        'Comercial',
        'Clientes',
        'Operaciones',
        'Mantenimiento',
        'Finanzas',
        'TI e Información',
        'Recursos Humanos',
        'Seguridad',
        'Compras',
    ];

    // Tipos confidenciales: solo Consejo y los admins de solo Procesos ven sus procesos.
    private const RESTRICTED_TYPES = ['Dirección'];

    public function run(): void
    {
        foreach (Group::all() as $group) {
            foreach ([...self::TYPES, ...self::RESTRICTED_TYPES] as $i => $name) {
                ProcessType::firstOrCreate(
                    ['group_id' => $group->id, 'name' => $name],
                    ['sort_order' => $i + 1, 'is_active' => true, 'is_restricted' => in_array($name, self::RESTRICTED_TYPES, true)]
                );
            }
        }
    }
}
