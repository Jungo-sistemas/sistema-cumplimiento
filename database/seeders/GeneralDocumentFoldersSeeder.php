<?php

namespace Database\Seeders;

use App\Models\DocumentFolder;
use App\Models\Group;
use Illuminate\Database\Seeder;

class GeneralDocumentFoldersSeeder extends Seeder
{
    const FOLDERS = [
        'Corporativos / societarios',
        'Inmobiliarios',
        'Comerciales / contractuales',
        'Operativos críticos',
    ];

    public function run(): void
    {
        $groups = Group::all();

        foreach ($groups as $group) {
            foreach (self::FOLDERS as $sort => $name) {
                DocumentFolder::firstOrCreate(
                    [
                        'group_id'   => $group->id,
                        'company_id' => null,
                        'parent_id'  => null,
                        'name'       => $name,
                    ],
                    [
                        'level'      => 'folder',
                        'sort_order' => $sort + 1,
                        'is_active'  => true,
                    ]
                );
            }
        }
    }
}
