<?php

namespace App\Console\Commands;

use App\Models\RequirementTemplate;
use Illuminate\Console\Command;

class FixNomRequirementNames extends Command
{
    protected $signature = 'requirements:fix-nom-names
        {--dry-run : Muestra lo que se haría sin escribir en base de datos}';

    protected $description = 'Corrige nombres de requerimientos NOM a los que el seeder les quitó el año por error, restaurando el nombre completo de la norma (p. ej. "NOM-005-ASEA- Dictamen de Construcción" -> "NOM-005-ASEA-2016 Dictamen de Construcción").';

    /**
     * Nombre recortado (como quedó en el catálogo antes del fix) => nombre completo con año.
     */
    private const NAME_FIXES = [
        'NOM-004-ASEA- Informe de Prueba Periódica del SRV' => 'NOM-004-ASEA-2017 Informe de Prueba Periódica del SRV',
        'NOM-004-ASEA- Informe Inicial del SRV' => 'NOM-004-ASEA-2017 Informe Inicial del SRV',
        'NOM-004-ASEA- Proyecto Ejecutivo SRV' => 'NOM-004-ASEA-2017 Proyecto Ejecutivo SRV',
        'NOM-005-ASEA- Dictamen de Construcción' => 'NOM-005-ASEA-2016 Dictamen de Construcción',
        'NOM-005-ASEA- Dictamen de Diseño' => 'NOM-005-ASEA-2016 Dictamen de Diseño',
        'NOM-005-ASEA- Dictamen de operación y mantenimiento' => 'NOM-005-ASEA-2016 Dictamen de operación y mantenimiento',
        'NOM-005-ASEA- Dossier de obra' => 'NOM-005-ASEA-2016 Dossier de obra',
        'NOM-005-ASEA- Proyecto Ejecutivo' => 'NOM-005-ASEA-2016 Proyecto Ejecutivo',
        'NOM-016-- Dictamen de Calidad de Petroliferos' => 'NOM-016-CRE-2016 Dictamen de Calidad de Petroliferos',
        'NOM-016-- Muestreo de laboratorio de Calidad de Petroliferos' => 'NOM-016-CRE-2016 Muestreo de laboratorio de Calidad de Petroliferos',
        'NOM-185-SCFI- Modelo prototipo software de dispensarios' => 'NOM-185-SCFI-2011 Modelo prototipo software de dispensarios',

        // Mismo bug, catálogo EC (NOM-016-CRE-2016 ya está cubierto arriba: es el mismo
        // requerimiento global compartido con ES).
        'NOM-003-SEDG- Proyecto Ejecutivo' => 'NOM-003-SEDG-2004 Proyecto Ejecutivo',
        'NOM-003-SEDG- Dictamen de Diseño' => 'NOM-003-SEDG-2004 Dictamen de Diseño',
        'NOM-003-SEDG- Dictamen de Construcción (EC)' => 'NOM-003-SEDG-2004 Dictamen de Construcción (EC)',
        'NOM-003-SEDG- Dictamen de operación y mantenimiento' => 'NOM-003-SEDG-2004 Dictamen de operación y mantenimiento',
        'NOM-008-ASEA- Dictamen de Diseño (muelles)' => 'NOM-008-ASEA-2019 Dictamen de Diseño (muelles)',
        'NOM-008-ASEA- Dictamen de Construcción (muelles)' => 'NOM-008-ASEA-2019 Dictamen de Construcción (muelles)',
        'NOM-008-ASEA- Dictamen de operación y mantenimiento (muelles)' => 'NOM-008-ASEA-2019 Dictamen de operación y mantenimiento (muelles)',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        foreach (self::NAME_FIXES as $oldName => $newName) {
            $template = RequirementTemplate::where('name', $oldName)->first();

            if (! $template) {
                $this->line("Sin cambios (no existe): \"{$oldName}\"");

                continue;
            }

            if (RequirementTemplate::where('name', $newName)->where('id', '!=', $template->id)->exists()) {
                $this->error("Omitido: ya existe otro requerimiento con el nombre \"{$newName}\", revisar manualmente.");

                continue;
            }

            $this->info(($dryRun ? '[dry-run] ' : '')."\"{$oldName}\" -> \"{$newName}\"");

            if (! $dryRun) {
                $template->update(['name' => $newName]);
            }
        }

        return self::SUCCESS;
    }
}
