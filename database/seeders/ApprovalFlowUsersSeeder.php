<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\JobPosition;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class ApprovalFlowUsersSeeder extends Seeder
{
    // Slugs alineados con JobPositionSeeder::POSITIONS (lider/jefe/gerente/direccion) — antes de
    // ese rename estos usuarios se creaban con los slugs viejos (ejecutivo_reglamentos,
    // direccion_general, director_finanzas), que ya no existen como JobPosition: en una base de
    // datos nueva (tests, ambiente local recién sembrado) el lookup por slug fallaba en silencio
    // y estos 3 usuarios quedaban sin ningún puesto asignado — nunca entraban a ningún flujo de
    // aprobación pese a que el seeder "los creó correctamente".
    private const USERS = [
        [
            'name'     => 'Dirección General',
            'email'    => 'direccion@vigia.com.mx',
            'position' => 'direccion',
        ],
        [
            'name'     => 'Líder',
            'email'    => 'lider@vigia.com.mx',
            'position' => 'lider',
        ],
        [
            'name'     => 'Gerente',
            'email'    => 'gerente@vigia.com.mx',
            'position' => 'gerente',
        ],
        [
            'name'     => 'Jefe',
            'email'    => 'ejecutivo@vigia.com.mx',
            'position' => 'jefe',
        ],
    ];

    public function run(): void
    {
        $adminRole  = Role::where('slug', 'admin')->firstOrFail();
        $group      = Group::where('slug', 'vigia')->firstOrFail();
        $password   = Hash::make('123456789');

        foreach (self::USERS as $data) {
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                [
                    'name'         => $data['name'],
                    'password'     => $password,
                    'role_id'      => $adminRole->id,
                    'group_id'     => $group->id,
                    'company_id'   => null,
                    'scope_level'  => 'group',
                    'module_access'=> 'all',
                    'status'       => 'active',
                ]
            );

            $position = JobPosition::where('group_id', $group->id)
                ->where('slug', $data['position'])
                ->first();

            if ($position && ! $user->jobPositions()->where('job_position_id', $position->id)->exists()) {
                $user->jobPositions()->attach($position->id);
            }
        }
    }
}
