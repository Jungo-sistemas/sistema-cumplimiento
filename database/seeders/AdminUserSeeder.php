<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $firstCompany = Company::firstOrFail();

        $superadminRole = Role::where('slug', 'superadmin')->firstOrFail();
        $adminRole      = Role::where('slug', 'admin')->firstOrFail();

        // Super administrador de plataforma
        User::updateOrCreate(
            ['email' => 'dev2.int@vigia.com.mx'],
            [
                'name'       => 'Super Admin',
                'password'   => Hash::make('REEMPLAZA_ESTA_CONTRASEÑA'),
                'company_id' => $firstCompany->id,
                'role_id'    => $superadminRole->id,
            ]
        );

        // Administrador general — cambia el email antes de ejecutar
        User::updateOrCreate(
            ['email' => 'admin@vigia.com.mx'],
            [
                'name'       => 'Administrador',
                'password'   => Hash::make('REEMPLAZA_ESTA_CONTRASEÑA'),
                'company_id' => $firstCompany->id,
                'role_id'    => $adminRole->id,
            ]
        );
    }
}