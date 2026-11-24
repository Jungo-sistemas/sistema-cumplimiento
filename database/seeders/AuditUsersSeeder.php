<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AuditUsersSeeder extends Seeder
{
    // Usuarios de prueba del módulo de Auditorías. El auditor y el Jefe de Auditoría quedan en
    // 'procesos', igual que cuando se crean desde la pantalla de usuarios (UserController lo fuerza).
    private const USERS = [
        [
            'name'  => 'Daniel Ruiz',
            'email' => 'auditor@vigia.com.mx',
            'role'  => 'auditor',
        ],
        [
            'name'  => 'Patricia Vega',
            'email' => 'jefe.auditoria@vigia.com.mx',
            'role'  => 'jefe_auditoria',
        ],
    ];

    public function run(): void
    {
        $group    = Group::where('slug', 'vigia')->firstOrFail();
        $password = Hash::make('123456789');

        foreach (self::USERS as $data) {
            $role = Role::where('slug', $data['role'])->firstOrFail();

            User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'          => $data['name'],
                    'password'      => $password,
                    'role_id'       => $role->id,
                    'group_id'      => $group->id,
                    'company_id'    => null,
                    'scope_level'   => 'group',
                    'module_access' => 'procesos',
                    'status'        => 'active',
                ]
            );
        }
    }
}
