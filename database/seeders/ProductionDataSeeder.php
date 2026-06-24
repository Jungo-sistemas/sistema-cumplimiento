<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Carga masiva de activos reales desde archivos CSV en database/seeders/examples/.
 *
 * Requisito previo: ProductionSeeder debe haberse ejecutado primero
 * (roles, tipos de activo, empresas, grupos, templates de requerimientos, usuarios admin).
 *
 * Ejecutar con:
 *   php artisan db:seed --class=ProductionDataSeeder
 */
class ProductionDataSeeder extends Seeder
{
    public function run(): void
    {
        /*
         * ── Estaciones de Servicio (ES) ──────────────────────────────────────
         * CSV: database/seeders/examples/ES_ejemplos.csv
         * Empresa: MDI (y "otras" para razones sociales distintas)
         */
        $this->call(EsSeeder::class);

        /*
         * ── Estaciones de Carburación (EC) ───────────────────────────────────
         * CSV: database/seeders/examples/EC_ejemplos.csv (o similar)
         * Empresa: según el CSV
         */
        $this->call(EC_Seeder::class);

        /*
         * ── Plantas ──────────────────────────────────────────────────────────
         * CSV: database/seeders/examples/Plantas_ejemplos.csv
         * Empresa: MDI
         */
        $this->call(PlantasSeeder::class);

        /*
         * ── Vehículos: ATQ ───────────────────────────────────────────────────
         * CSV: database/seeders/examples/ATQ_KIWI.csv → KIWI GAS
         * CSV: database/seeders/examples/ATQ_MDI.csv  → MDI (o la empresa que use)
         */
        $this->call(AtqKiwiSeeder::class);
        $this->call(AtqMdiSeeder::class);

        /*
         * ── Vehículos: Semirremolques ────────────────────────────────────────
         * CSV: database/seeders/examples/Semirremolque_MIGAR.csv   → MIGAR
         * CSV: database/seeders/examples/Semirremolque_Propane.csv → PROPANE SERVICES
         */
        $this->call(SemirremolqueMigarSeeder::class);
        $this->call(SemirremolquePropaneSeeder::class);
    }
}
