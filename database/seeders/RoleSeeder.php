<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::updateOrCreate(
            ['slug' => 'admin'],
            ['name' => 'Administrador']
        );

        Role::updateOrCreate(
            ['slug' => 'operative'],
            ['name' => 'Operativo']
        );

        Role::updateOrCreate(
            ['slug' => 'readonly'],
            ['name' => 'Solo Vista']
        );
    }
}
