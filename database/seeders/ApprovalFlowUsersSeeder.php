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
    private const USERS = [
        [
            'name'     => 'Dirección General',
            'email'    => 'direccion@vigia.com.mx',
            'position' => 'direccion_general',
        ],
        [
            'name'     => 'Director de Finanzas',
            'email'    => 'finanzas@vigia.com.mx',
            'position' => 'director_finanzas',
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
            'name'     => 'Ejecutivo de Reglamentos',
            'email'    => 'ejecutivo@vigia.com.mx',
            'position' => 'ejecutivo_reglamentos',
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
