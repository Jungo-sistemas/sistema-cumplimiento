<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $superadminRole = Role::where('slug', 'superadmin')->firstOrFail();
        $adminRole      = Role::where('slug', 'admin')->firstOrFail();
        $vigiaGroup     = Group::where('slug', 'vigia')->first();

        // Super administrador de plataforma — acceso global, sin empresa
        User::updateOrCreate(
            ['email' => 'dev2.int@vigia.com.mx'],
            [
                'name'        => 'Andre Galindo',
                'password'    => Hash::make('ASgp+200802'),
                'company_id'  => null,
                'group_id'    => null,
                'scope_level' => 'global',
                'role_id'     => $superadminRole->id,
                'status'      => 'active',
            ]
        );

        // Administrador del grupo VIGIA
        User::updateOrCreate(
            ['email' => 'admin@vigia.com.mx'],
            [
                'name'        => 'Administrador',
                'password'    => Hash::make('ASgalP*4007*'),
                'company_id'  => null,
                'group_id'    => $vigiaGroup?->id,
                'scope_level' => $vigiaGroup ? 'group' : 'global',
                'role_id'     => $adminRole->id,
                'status'      => 'active',
            ]
        );
    }
}