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
