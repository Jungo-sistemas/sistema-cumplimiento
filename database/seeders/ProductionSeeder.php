<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * ── 1. Catálogos base ────────────────────────────────────────────────
         * Sin dependencias entre sí; deben correr primero.
         */
        $this->call(RoleSeeder::class);          // superadmin, admin, operative, readonly
        $this->call(AssetTypeSeeder::class);     // Almacenamiento, EC, ES, Transporte, ATQ…
        $this->call(CompanySeeder::class);       // ALCOM, MIGAR, KIWI GAS…
        $this->call(GroupSeeder::class);         // VIGIA, DAVAL, INTERA

        /*
         * ── 2. Relaciones entre catálogos ────────────────────────────────────
         * Requieren que existan companies + groups.
         */
        $this->call(AssignCompaniesToGroupsSeeder::class);

        /*
         * ── 3. Estructura por grupo ──────────────────────────────────────────
         * Requieren que existan groups.
         */
        $this->call(ProcessTypeSeeder::class);           // Tipos de proceso (Comercial, Operaciones…)
        $this->call(JobPositionSeeder::class);            // Puestos jerárquicos (Líder, Jefe, Gerente, Dirección)
        $this->call(GeneralDocumentFoldersSeeder::class); // Carpetas de documentos generales

        /*
         * ── 4. Templates de requerimientos ──────────────────────────────────
         * Requieren que existan los asset types.
         * Los seeders de CSV omiten silenciosamente si no encuentra el archivo.
         */
        $this->call(ComercializacionRequirementTemplateSeeder::class);
        $this->call(ECRequirementTemplateSeeder::class);
        $this->call(EsRequirementTemplateSeeder::class);
        $this->call(VehiculosRequirementTemplateSeeder::class);

        /*
         * ── 5. Usuarios administradores ─────────────────────────────────────
         * Requieren roles y grupos.
         * Editar AdminUserSeeder.php para ajustar contraseñas antes de correr.
         */
        $this->call(AdminUserSeeder::class);
    }
}
