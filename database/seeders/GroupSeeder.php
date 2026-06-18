<?php

namespace Database\Seeders;

use App\Models\Group;
use Illuminate\Database\Seeder;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        Group::updateOrCreate(
            ['slug' => 'vigia'],
            ['name' => 'VIGIA', 'is_active' => true]
        );

        Group::updateOrCreate(
            ['slug' => 'daval'],
            ['name' => 'DAVAL', 'is_active' => true]
        );

        Group::updateOrCreate(
            ['slug' => 'intera'],
            ['name' => 'INTERA', 'is_active' => true]
        );
    }
}