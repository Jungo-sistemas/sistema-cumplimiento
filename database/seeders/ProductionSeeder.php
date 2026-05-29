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

        // Document catalog for ALCOM (requires company + group to exist)
        $this->call(DocumentCatalogSeeder::class);

        // Admin users — edit AdminUserSeeder.php to set passwords before running
        $this->call(AdminUserSeeder::class);
    }
}