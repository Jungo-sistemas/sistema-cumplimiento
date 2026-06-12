<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\JobPosition;
use Illuminate\Database\Seeder;

class JobPositionSeeder extends Seeder
{
    private const POSITIONS = [
        ['slug' => 'direccion_general',    'name' => 'Dirección General',    'sort_order' => 1],
        ['slug' => 'director_finanzas',    'name' => 'Director de Finanzas', 'sort_order' => 2],
        ['slug' => 'lider',                'name' => 'Líder',                'sort_order' => 3],
        ['slug' => 'gerente',              'name' => 'Gerente',              'sort_order' => 4],
        ['slug' => 'ejecutivo_reglamentos','name' => 'Ejecutivo de Reglamentos', 'sort_order' => 5],
    ];

    public function run(): void
    {
        foreach (Group::all() as $group) {
            foreach (self::POSITIONS as $position) {
                JobPosition::firstOrCreate(
                    ['group_id' => $group->id, 'slug' => $position['slug']],
                    ['name' => $position['name'], 'sort_order' => $position['sort_order'], 'is_active' => true]
                );
            }
        }
    }
}
