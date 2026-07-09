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

    // [name, description, authority, compliance_scope, category, subtype]
    const REQUIREMENTS = [
        // --- Identificación del vehículo ---
        ['Número de permiso',                                          null,                                                       'CRE',   'operation', 'expediente', 'permiso'],
        ['No. Económico',                                              null,                                                       null,    'operation', 'expediente', 'identificacion'],
        ['Número de serie del vehículo / NIV',                        'Número de Identificación Vehicular',                       null,    'operation', 'expediente', 'identificacion'],
        ['Modelo / Año',                                               null,                                                       null,    'operation', 'expediente', 'identificacion'],
        ['Marca del vehículo',                                         null,                                                       null,    'operation', 'expediente', 'identificacion'],
        ['Tarjeta de circulación vigente',                             null,                                                       'SICT',  'operation', 'expediente', 'identificacion'],
        ['Núm. Permiso SICT / Núm. Certificado',                      'Solo si circula en área federal',                          'SICT',  'operation', 'expediente', 'permiso'],
        ['Número de matrícula (placa)',                                null,                                                       null,    'operation', 'expediente', 'identificacion'],

        // --- Recipiente ---
        ['Marca del recipiente',                                       null,                                                       null,    'operation', 'expediente', 'recipiente'],
        ['Capacidad en litros',                                        null,                                                       null,    'operation', 'expediente', 'recipiente'],
        ['Número de serie del recipiente',                             null,                                                       null,    'operation', 'expediente', 'recipiente'],
        ['Fecha de fabricación del recipiente (mes/año)',              null,                                                       null,    'operation', 'expediente', 'recipiente'],

        // --- Ubicación ---
        ['Central de guarda',                                          null,                                                       null,    'operation', 'expediente', 'ubicacion'],
        ['Latitud y longitud de central de guarda (coordenadas)',      null,                                                       null,    'operation', 'expediente', 'ubicacion'],

        // --- Combustible ---
        ['Carbura con: Gasolina',                                      null,                                                       null,    'operation', 'expediente', 'combustible'],
        ['Carbura con: Diesel',                                        null,                                                       null,    'operation', 'expediente', 'combustible'],
        ['Carbura con: GLP',                                           null,                                                       null,    'operation', 'expediente', 'combustible'],

        // --- Póliza de seguro ---
        ['Póliza de seguro — Institución emisora',                     'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Número de póliza',                       'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Fecha de inicio de vigencia',             'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Fecha de término de vigencia',            'Documento íntegro de la póliza de seguro vigente',         null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Cobertura por responsabilidad civil',     null,                                                       null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Cobertura por daño ambiental',           null,                                                       null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Límite suma asegurada resp. civil',       null,                                                       null,    'operation', 'expediente', 'poliza'],
        ['Póliza de seguro — Límite suma asegurada daño ambiental',   null,                                                       null,    'operation', 'expediente', 'poliza'],

        // --- Dictámenes NOMs ---
        ['NOM-EM-007-ASEA-2025',                                       'Dictamen de cumplimiento vigente',                        'ASEA',  'operation', 'expediente', 'nom'],
        ['NOM-005-SESH-2010',                                          'Dictamen de cumplimiento vigente. Solo si carbura con GLP', 'ASEA', 'operation', 'expediente', 'nom'],
        ['NOM-013-SEDG-2002',                                          'Dictamen de cumplimiento vigente. Aplica cuando el recipiente tiene más de 10 años', 'ASEA', 'operation', 'expediente', 'nom'],

        // --- Fotografías ---
        ['Fotografía — Frontal',                                       null,                                                       null,    'operation', 'expediente', 'fotografia'],
        ['Fotografía — Trasera',                                       null,                                                       null,    'operation', 'expediente', 'fotografia'],
        ['Fotografía — Lateral izquierda',                             null,                                                       null,    'operation', 'expediente', 'fotografia'],
        ['Fotografía — Lateral derecha',                               null,                                                       null,    'operation', 'expediente', 'fotografia'],
        ['Fotografía — Placa del tanque (recipiente no desmontable)',  null,                                                       null,    'operation', 'expediente', 'fotografia'],
        ['Fotografía — Placa del chasis',                              null,                                                       null,    'operation', 'expediente', 'fotografia'],

        // --- Cilíndreras ---
        ['Cantidad máxima de recipientes transportables y/o portátiles', 'Solo aplica para cilíndreras',                          null,    'operation', 'expediente', 'otro'],
    ];

    // [name, description, authority, compliance_scope, category, subtype]
    // Modificación usa los mismos requisitos que Alta
    const ALTA_REQUIREMENTS = [
        ['Número de permiso',                                              null,                                                                            'CRE',  'operation', 'alta', 'permiso'],
        ['Número de serie del vehículo / NIV',                            'Número de Identificación Vehicular',                                            null,   'operation', 'alta', 'identificacion'],
        ['Número de placa o matrícula y fecha de cambio (SICT)',          'Secretaría de Infraestructura, Comunicaciones y Transportes',                   'SICT', 'operation', 'alta', 'identificacion'],
        ['Tarjeta de circulación vigente',                                 null,                                                                            'SICT', 'operation', 'alta', 'identificacion'],
        ['Póliza de seguro — Institución emisora',                        'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Número de póliza',                          'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Fecha de inicio de vigencia',               'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Fecha de término de vigencia',              'Documento íntegro de la póliza de seguro vigente',                              null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Cobertura por responsabilidad civil',        null,                                                                            null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Cobertura por daño ambiental',              null,                                                                            null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Límite suma asegurada resp. civil',          null,                                                                            null,   'operation', 'alta', 'poliza'],
        ['Póliza de seguro — Límite suma asegurada daño ambiental',      null,                                                                            null,   'operation', 'alta', 'poliza'],
        ['NOM-EM-007-ASEA-2025',                                          'Dictamen de cumplimiento vigente',                                             'ASEA', 'operation', 'alta', 'nom'],
        ['NOM-005-SESH-2010',                                             'Dictamen de cumplimiento vigente. Solo si carbura con GLP',                    'ASEA', 'operation', 'alta', 'nom'],
        ['NOM-013-SEDG-2002',                                             'Dictamen de cumplimiento vigente. Aplica cuando el recipiente tiene más de 10 años', 'ASEA', 'operation', 'alta', 'nom'],
        ['Historial de modificaciones',                                   'Solo aplica si es unidad rehabilitada',                                        null,   'operation', 'alta', 'otro'],
    ];

    const BAJA_REQUIREMENTS = [
        ['Permiso — Número del permiso',                  null,                                  null, 'operation', 'baja', 'permiso'],
        ['Permiso — Actividad regulada',                  null,                                  null, 'operation', 'baja', 'permiso'],
        ['Parque vehicular — Tipo de vehículo',           null,                                  null, 'operation', 'baja', 'identificacion'],
        ['Parque vehicular — ID asignado por la Comisión', null,                                 null, 'operation', 'baja', 'identificacion'],
        ['Número de serie del vehículo / NIV',            'Número de Identificación Vehicular',  null, 'operation', 'baja', 'identificacion'],
        ['Historial de modificaciones',                   'Solo aplica si es unidad rehabilitada', null, 'operation', 'baja', 'otro'],
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

        foreach ($requirements as [$name, $description, $authority, $scope, $cat, $subtype]) {
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
                    'subtype'     => $subtype,
                ]
            );
        }

        $count = count($requirements);
        $this->command?->info("✓ {$typeName} [{$category}]: {$count} templates.");

        return $count;
    }
}
