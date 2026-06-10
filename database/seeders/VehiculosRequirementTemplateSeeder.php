<?php

namespace Database\Seeders;

use App\Models\AssetType;
use App\Models\RequirementTemplate;
use Illuminate\Database\Seeder;

class VehiculosRequirementTemplateSeeder extends Seeder
{
    // Expediente applies to these vehicle types
    const ASSET_TYPE_NAMES = [
        'Tracto',
        'Semirremolque',
        'ATQ',
        'Carro tanque',
        'Cilindrera',
    ];

    // Alta/Modificación and Baja apply to these vehicle types
    const LIFECYCLE_ASSET_TYPE_NAMES = [
        'Tracto',
        'Semirremolque',
        'ATQ',
        'Carro tanque',
        'Cilindrera',
    ];

    // [name, description, authority, compliance_scope, category]
    const REQUIREMENTS = [
        // --- Identificación del vehículo ---
        // --- Identificación del vehículo ---
        ['Número de permiso',                                          null,                                                       'CRE',   'operation', 'expediente'],
        ['No. Económico',                                              null,                                                       null,    'operation', 'expediente'],
        ['Número de serie del vehículo / NIV',                        'Número de Identificación Vehicular',                       null,    'operation', 'expediente'],
        ['Modelo / Año',                                               null,                                                       null,    'operation', 'expediente'],
        ['Marca del vehículo',                                         null,                                                       null,    'operation', 'expediente'],
        ['Tarjeta de circulación vigente',                             null,                                                       'SICT',  'operation', 'expediente'],
        ['Núm. Permiso SICT / Núm. Certificado',                      'Solo si circula en área federal',                          'SICT',  'operation', 'expediente'],
        ['Número de matrícula (placa)',                                null,                                                       null,    'operation', 'expediente'],

        // --- Recipiente ---
        ['Marca del recipiente',                                       null,                                                       null,    'operation', 'expediente'],
        ['Capacidad en litros',                                        null,                                                       null,    'operation', 'expediente'],
        ['Número de serie del recipiente',                             null,                                                       null,    'operation', 'expediente'],
        ['Fecha de fabricación del recipiente (mes/año)',              null,                                                       null,    'operation', 'expediente'],

        // --- Ubicación ---
        ['Central de guarda',                                          null,                                                       null,    'operation', 'expediente'],
        ['Latitud y longitud de central de guarda (coordenadas)',      null,                                                       null,    'operation', 'expediente'],

        // --- Combustible ---
        ['Carbura con: Gasolina',                                      null,                                                       null,    'operation', 'expediente'],
        ['Carbura con: Diesel',                                        null,                                                       null,    'operation', 'expediente'],
        ['Carbura con: GLP',                                           null,                                                       null,    'operation', 'expediente'],

        // --- Póliza de seguro ---
        ['Póliza de seguro — Institución emisora',                     'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente'],
        ['Póliza de seguro — Número de póliza',                       'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente'],
        ['Póliza de seguro — Fecha de inicio de vigencia',             'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente'],
        ['Póliza de seguro — Fecha de término de vigencia',            'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente'],
        ['Póliza de seguro — Cobertura por responsabilidad civil',     null,                                                       null,    'operation', 'expediente'],
        ['Póliza de seguro — Cobertura por daño ambiental',           null,                                                       null,    'operation', 'expediente'],
        ['Póliza de seguro — Límite suma asegurada resp. civil',       null,                                                       null,    'operation', 'expediente'],
        ['Póliza de seguro — Límite suma asegurada daño ambiental',   null,                                                       null,    'operation', 'expediente'],

        // --- Dictámenes NOMs ---
        ['NOM-EM-007-ASEA-2025',                                       'Dictamen de cumplimiento vigente',                        'ASEA',  'operation', 'expediente'],
        ['NOM-005-SESH-2010',                                          'Dictamen de cumplimiento vigente. Solo si carbura con GLP', 'ASEA', 'operation', 'expediente'],
        ['NOM-013-SEDG-2002',                                          'Dictamen de cumplimiento vigente. Aplica cuando el recipiente tiene más de 10 años', 'ASEA', 'operation', 'expediente'],

        // --- Fotografías ---
        ['Fotografía — Frontal',                                       null,                                                       null,    'operation', 'expediente'],
        ['Fotografía — Trasera',                                       null,                                                       null,    'operation', 'expediente'],
        ['Fotografía — Lateral izquierda',                             null,                                                       null,    'operation', 'expediente'],
        ['Fotografía — Lateral derecha',                               null,                                                       null,    'operation', 'expediente'],
        ['Fotografía — Placa del tanque (recipiente no desmontable)',  null,                                                       null,    'operation', 'expediente'],
        ['Fotografía — Placa del chasis',                              null,                                                       null,    'operation', 'expediente'],

        // --- Cilíndreras ---
        ['Cantidad máxima de recipientes transportables y/o portátiles', 'Solo aplica para cilíndreras',                          null,    'operation', 'expediente'],
    ];

    // [name, description, authority, compliance_scope, category]
    // Modificación usa los mismos requisitos que Alta
    const ALTA_REQUIREMENTS = [
        ['Número de permiso',                                              null,                                                                            'CRE',  'operation', 'alta'],
        ['Número de serie del vehículo / NIV',                            'Número de Identificación Vehicular',                                            null,   'operation', 'alta'],
        ['Número de placa o matrícula y fecha de cambio (SICT)',          'Secretaría de Infraestructura, Comunicaciones y Transportes',                   'SICT', 'operation', 'alta'],
        ['Tarjeta de circulación vigente',                                 null,                                                                            'SICT', 'operation', 'alta'],
        ['Póliza de seguro — Institución emisora',                        'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta'],
        ['Póliza de seguro — Número de póliza',                          'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta'],
        ['Póliza de seguro — Fecha de inicio de vigencia',               'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta'],
        ['Póliza de seguro — Fecha de término de vigencia',              'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta'],
        ['Póliza de seguro — Cobertura por responsabilidad civil',        null,                                                                            null,   'operation', 'alta'],
        ['Póliza de seguro — Cobertura por daño ambiental',              null,                                                                            null,   'operation', 'alta'],
        ['Póliza de seguro — Límite suma asegurada resp. civil',          null,                                                                            null,   'operation', 'alta'],
        ['Póliza de seguro — Límite suma asegurada daño ambiental',      null,                                                                            null,   'operation', 'alta'],
        ['NOM-EM-007-ASEA-2025',                                          'Dictamen de cumplimiento vigente',                                             'ASEA', 'operation', 'alta'],
        ['NOM-005-SESH-2010',                                             'Dictamen de cumplimiento vigente. Solo si carbura con GLP',                    'ASEA', 'operation', 'alta'],
        ['NOM-013-SEDG-2002',                                             'Dictamen de cumplimiento vigente. Aplica cuando el recipiente tiene más de 10 años', 'ASEA', 'operation', 'alta'],
        ['Historial de modificaciones',                                   'Solo aplica si es unidad rehabilitada',                                        null,   'operation', 'alta'],
    ];

    const BAJA_REQUIREMENTS = [
        ['Permiso — Número del permiso',                  null,                                  null, 'operation', 'baja'],
        ['Permiso — Actividad regulada',                  null,                                  null, 'operation', 'baja'],
        ['Parque vehicular — Tipo de vehículo',           null,                                  null, 'operation', 'baja'],
        ['Parque vehicular — ID asignado por la Comisión', null,                                 null, 'operation', 'baja'],
        ['Número de serie del vehículo / NIV',            'Número de Identificación Vehicular',  null, 'operation', 'baja'],
        ['Historial de modificaciones',                   'Solo aplica si es unidad rehabilitada', null, 'operation', 'baja'],
    ];

    public function run(): void
    {
        $total = 0;

        // --- Expediente ---
        foreach (self::ASSET_TYPE_NAMES as $typeName) {
            $total += $this->seedRequirements($typeName, self::REQUIREMENTS);
        }

        // --- Alta / Modificación y Baja (incluye Cilindrera) ---
        foreach (self::LIFECYCLE_ASSET_TYPE_NAMES as $typeName) {
            $total += $this->seedRequirements($typeName, self::ALTA_REQUIREMENTS);
            $total += $this->seedRequirements($typeName, self::BAJA_REQUIREMENTS);
        }

        $this->command?->info("Total: {$total} templates procesados.");
    }

    private function seedRequirements(string $typeName, array $requirements): int
    {
        $assetType = AssetType::where('name', $typeName)->first();

        if (! $assetType) {
            $this->command?->warn("Asset type no encontrado: {$typeName}");
            return 0;
        }

        $category = $requirements[0][4] ?? 'expediente';

        foreach ($requirements as [$name, $description, $authority, $scope, $cat]) {
            RequirementTemplate::updateOrCreate(
                [
                    'asset_type_id'    => $assetType->id,
                    'name'             => $name,
                    'compliance_scope' => $scope,
                    'category'         => $cat,
                ],
                [
                    'description' => $description,
                    'authority'   => $authority,
                ]
            );
        }

        $count = count($requirements);
        $this->command?->info("✓ {$typeName} [{$category}]: {$count} templates.");

        return $count;
    }
}
