<?php

namespace Database\Seeders\Concerns;

trait NormalizesLocation
{
    // All keys must be in mb_strtoupper form so they match any casing variant
    private const ESTADOS_MAP = [
        // Abreviaciones
        'NL'              => 'Nuevo León',
        'SLP'             => 'San Luis Potosí',
        'S.L.P.'          => 'San Luis Potosí',
        // Nombres completos en mayúsculas (con o sin acento)
        'CHIAPAS'         => 'Chiapas',
        'COAHUILA'        => 'Coahuila',
        'COLIMA'          => 'Colima',
        'JALISCO'         => 'Jalisco',
        'NUEVO LEON'      => 'Nuevo León',
        'NUEVO LEÓN'      => 'Nuevo León',
        'SAN LUIS POTOSI' => 'San Luis Potosí',
        'SAN LUIS POTOSÍ' => 'San Luis Potosí',
        'TAMAULIPAS'      => 'Tamaulipas',
        'TLAXCALA'        => 'Tlaxcala',
        'VERACRUZ'        => 'Veracruz',
        'YUCATAN'         => 'Yucatán',
        'YUCATÁN'         => 'Yucatán',
    ];

    protected function normalizeLocation(?string $location): ?string
    {
        if (! $location || trim($location) === '') {
            return null;
        }

        $key = mb_strtoupper(trim($location), 'UTF-8');

        return self::ESTADOS_MAP[$key] ?? trim($location);
    }
}
