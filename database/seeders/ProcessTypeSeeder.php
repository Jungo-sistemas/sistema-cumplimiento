<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\ProcessType;
use Illuminate\Database\Seeder;

class ProcessTypeSeeder extends Seeder
{
    public function run(): void
    {
        $group = Group::first();

        if (! $group) {
            return;
        }

        $types = [
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

        foreach ($types as $i => $name) {
            ProcessType::firstOrCreate(
                ['group_id' => $group->id, 'name' => $name],
                ['sort_order' => $i + 1, 'is_active' => true]
            );
        }
    }
}
