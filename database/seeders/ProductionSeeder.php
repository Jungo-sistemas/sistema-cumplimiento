<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // Core structure — must run first and in this order
        $this->call(RoleSeeder::class);
        $this->call(AssetTypeSeeder::class);
        $this->call(CompanySeeder::class);
        $this->call(GroupSeeder::class);
        $this->call(AssignCompaniesToGroupsSeeder::class);
        $this->call(ProcessTypeSeeder::class);

        // Requirement templates loaded from CSV files in database/seeders/data/
        $this->call(ComercializacionRequirementTemplateSeeder::class);
        $this->call(ECRequirementTemplateSeeder::class);
        $this->call(EsRequirementTemplateSeeder::class);
        $this->call(VehiculosRequirementTemplateSeeder::class);

        // General document folders (5 categories, group-scoped, no company-specific)
        $this->call(GeneralDocumentFoldersSeeder::class);

        // Admin users — edit AdminUserSeeder.php to set passwords before running
        $this->call(AdminUserSeeder::class);

        // Asset examples from CSV files (requires admin user and asset types to exist)
        $this->call(EsSeeder::class);
        $this->call(PlantasSeeder::class);
    }
}