<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use App\Models\Group;

class AssignCompaniesToGroupsSeeder extends Seeder
{
    private const INTERA_COMPANIES = [
        'INTERA CAPITAL',
        'JUNGO',
        'PACTO',
        'TRANSPORTES MIGAR',
        'SOLUCIONES TECNOLOGICAS PARA EL TRANSPORTE',
    ];

    private const DAVAL_COMPANIES = [
        'DAVAL',
    ];

    public function run(): void
    {
        $vigia  = Group::where('slug', 'vigia')->firstOrFail();
        $daval  = Group::where('slug', 'daval')->firstOrFail();
        $intera = Group::where('slug', 'intera')->firstOrFail();

        Company::whereIn('name', self::DAVAL_COMPANIES)->update(['group_id' => $daval->id]);
        Company::whereIn('name', self::INTERA_COMPANIES)->update(['group_id' => $intera->id]);

        // Todas las demás sin grupo van a VIGIA
        Company::whereNull('group_id')->update(['group_id' => $vigia->id]);
    }
}