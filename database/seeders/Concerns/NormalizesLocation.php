<?php

namespace Database\Seeders\Concerns;

trait NormalizesLocation
{
    private const ESTADOS_MAP = [
        // Abreviaciones comunes
        'AGS'             => 'Aguascalientes',
        'BC'              => 'Baja California',
        'BCS'             => 'Baja California Sur',
        'CAMP'            => 'Campeche',
        'CHIS'            => 'Chiapas',
        'CHIH'            => 'Chihuahua',
        'COAH'            => 'Coahuila',
        'COL'             => 'Colima',
        'CDMX'            => 'Ciudad de México',
        'DF'              => 'Ciudad de México',
        'D.F.'            => 'Ciudad de México',
        'DGO'             => 'Durango',
        'GTO'             => 'Guanajuato',
        'GRO'             => 'Guerrero',
        'HGO'             => 'Hidalgo',
        'JAL'             => 'Jalisco',
        'MEX'             => 'Estado de México',
        'EDOMEX'          => 'Estado de México',
        'MICH'            => 'Michoacán',
        'MOR'             => 'Morelos',
        'NAY'             => 'Nayarit',
        'NL'              => 'Nuevo León',
        'OAX'             => 'Oaxaca',
        'PUE'             => 'Puebla',
        'QRO'             => 'Querétaro',
        'QROO'            => 'Quintana Roo',
        'SLP'             => 'San Luis Potosí',
        'S.L.P.'          => 'San Luis Potosí',
        'SIN'             => 'Sinaloa',
        'SON'             => 'Sonora',
        'TAB'             => 'Tabasco',
        'TAMPS'           => 'Tamaulipas',
        'TLAX'            => 'Tlaxcala',
        'VER'             => 'Veracruz',
        'YUC'             => 'Yucatán',
        'ZAC'             => 'Zacatecas',

        // Nombres completos en mayúsculas (con y sin acento)
        'AGUASCALIENTES'      => 'Aguascalientes',
        'BAJA CALIFORNIA'     => 'Baja California',
        'BAJA CALIFORNIA SUR' => 'Baja California Sur',
        'CAMPECHE'            => 'Campeche',
        'CHIAPAS'             => 'Chiapas',
        'CHIHUAHUA'           => 'Chihuahua',
        'CIUDAD DE MEXICO'    => 'Ciudad de México',
        'CIUDAD DE MÉXICO'    => 'Ciudad de México',
        'COAHUILA'            => 'Coahuila',
        'COLIMA'              => 'Colima',
        'DURANGO'             => 'Durango',
        'ESTADO DE MEXICO'    => 'Estado de México',
        'ESTADO DE MÉXICO'    => 'Estado de México',
        'GUANAJUATO'          => 'Guanajuato',
        'GUERRERO'            => 'Guerrero',
        'HIDALGO'             => 'Hidalgo',
        'JALISCO'             => 'Jalisco',
        'MEXICO'              => 'Estado de México',
        'MICHOACAN'           => 'Michoacán',
        'MICHOACÁN'           => 'Michoacán',
        'MORELOS'             => 'Morelos',
        'NAYARIT'             => 'Nayarit',
        'NUEVO LEON'          => 'Nuevo León',
        'NUEVO LEÓN'          => 'Nuevo León',
        'OAXACA'              => 'Oaxaca',
        'PUEBLA'              => 'Puebla',
        'QUERETARO'           => 'Querétaro',
        'QUERÉTARO'           => 'Querétaro',
        'QUINTANA ROO'        => 'Quintana Roo',
        'SAN LUIS POTOSI'     => 'San Luis Potosí',
        'SAN LUIS POTOSÍ'     => 'San Luis Potosí',
        'SINALOA'             => 'Sinaloa',
        'SONORA'              => 'Sonora',
        'TABASCO'             => 'Tabasco',
        'TAMAULIPAS'          => 'Tamaulipas',
        'TLAXCALA'            => 'Tlaxcala',
        'VERACRUZ'            => 'Veracruz',
        'YUCATAN'             => 'Yucatán',
        'YUCATÁN'             => 'Yucatán',
        'ZACATECAS'           => 'Zacatecas',
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
