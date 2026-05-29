<?php

namespace Database\Seeders;

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

        // Super administrador de plataforma — acceso global, sin empresa
        User::updateOrCreate(
            ['email' => 'dev2.int@vigia.com.mx'],
            [
                'name'        => 'Super Admin',
                'password'    => Hash::make('REEMPLAZA_ESTA_CONTRASEÑA'),
                'company_id'  => null,
                'group_id'    => null,
                'scope_level' => 'global',
                'role_id'     => $superadminRole->id,
                'status'      => 'active',
            ]
        );

        // Administrador general — acceso global, asignar empresa/grupo después si se requiere
        User::updateOrCreate(
            ['email' => 'admin@vigia.com.mx'],
            [
                'name'        => 'Administrador',
                'password'    => Hash::make('REEMPLAZA_ESTA_CONTRASEÑA'),
                'company_id'  => null,
                'group_id'    => null,
                'scope_level' => 'global',
                'role_id'     => $adminRole->id,
                'status'      => 'active',
            ]
        );
    }
}