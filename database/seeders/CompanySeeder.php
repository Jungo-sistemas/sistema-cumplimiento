<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            // VIGIA group
            'ALCOM',
            'BIOMEX',
            'CAPITAL HUMANO',
            'FISCAL',
            'TI',
            'SOM',
            'DAVAL',
            'MERCANTIL DISTRIBUIDORA',
            'PROPANE SERVICES',
            'SOLTRACK',
            'TRONCALNET',
            'Terrenos Vigia',
            'MIGAR CAPITAL',
            'VERITAS ENERGÍA (KIWI GAS)',
            'COMBUSTIBLES TG',
            'GASERA VILLA DE REYES',
            'TOTAL PROPANE',
            'VIDENCI',
            'MINIMA REAL ESTATE',
            'GRUPO AVIDAM',
            // DAVAL group
            'DAVAL',
            // INTERA group
            'INTERA CAPITAL',
            'JUNGO',
            'PACTO',
            'TRANSPORTES MIGAR',
            'SOLUCIONES TECNOLOGICAS PARA EL TRANSPORTE',
        ];

        foreach ($companies as $name) {
            Company::firstOrCreate(
                ['name' => trim($name)]
            );
        }
    }
}