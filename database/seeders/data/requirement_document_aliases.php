<?php

// Alias de nombres de archivo -> nombre exacto del RequirementTemplate en el catálogo.
// Se usa cuando el documento que entregan no trae el nombre exacto del requerimiento,
// pero sabemos que corresponde a ese mismo requerimiento (confirmado manualmente),
// y no queremos depender de que renombren el archivo cada vez que llega una carpeta nueva.
// Se agrupa por nombre de AssetType (EC, ES, Plantas, Tracto, Semirremolque, ATQ, ...).

return [
    'ES' => [
        'Informe Preventivo' => 'Manifiesto de Impacto Ambiental / Informe Preventivo',
        'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto'
            => 'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto Social, EVIS',
        // Los paréntesis finales se quitan antes de buscar el alias (mismo criterio que con
        // archivos, ver Título de Permiso), así que aquí va sin el "(EVIS)".
        'Resolutivo de Evaluación de Impacto Social'
            => 'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto Social, EVIS',
        // Variante donde el proveedor pone ", EVIS" tras coma en vez de "(EVIS)" entre paréntesis
        // (ese sufijo entre paréntesis sí se recorta antes de buscar alias, la coma no).
        'Resolutivo de Evaluación de Impacto Social, EVIS'
            => 'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto Social, EVIS',
        // Confirmado con negocio: mismo requerimiento, redactado sin "Resolutivo de" (ES Barquito).
        'Evaluación de Impacto Social, EVIS'
            => 'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto Social, EVIS',
        'Evaluación de Impacto Social'
            => 'Resolutivo del Manifestación de Impacto Social en el Sector Energético, MISSE o Evaluación de Impacto Social, EVIS',

        // Nombres del Excel de vigencias (fechas de emisión/vigencia por documento) que no
        // calzan literal con el catálogo — confirmado manualmente que es el mismo requerimiento.
        'Pólizas de Seguros RC y RCA - Determinación de Límites' => 'Pólizas de seguros RC y RCA+ Determinación de los Límites',
        // Confirmado con negocio: mismo requerimiento, redactado distinto en el Excel de vigencias (ES Crucero).
        'Pólizas de seguros RC y RCA - Límites' => 'Pólizas de seguros RC y RCA+ Determinación de los Límites',
        'Vo.Bo. Inicio de Operaciones - Protección Civil' => 'Vobo de Inicio de Operaciones Protección Civil',
        'Pruebas de Integridad Mecánica PH' => 'Pruebas de integridad mecánica',
        'SASISOPA - Implementación' => 'SASISOPA Implementación',

        // El catálogo de producción todavía no lleva el año de la norma en el nombre del
        // requerimiento (confirmado contra producción: "NOM-005-ASEA- Dictamen de Construcción",
        // sin "2016"), mientras que el catálogo local sí lo tiene. Los archivos y el CSV de
        // vigencias siempre traen el año, así que se mapean aquí al nombre sin año. En local,
        // donde el catálogo ya tiene el año, estos alias no se usan (el nombre completo matchea
        // directo) y solo generan un warning de "alias mal configurado" al importar, que es
        // inofensivo.
        'NOM-004-ASEA-2017 Informe de Prueba Periódica del SRV' => 'NOM-004-ASEA- Informe de Prueba Periódica del SRV',
        'NOM-004-ASEA-2017 Informe Inicial del SRV' => 'NOM-004-ASEA- Informe Inicial del SRV',
        'NOM-004-ASEA-2017 Proyecto Ejecutivo SRV' => 'NOM-004-ASEA- Proyecto Ejecutivo SRV',
        'NOM-005-ASEA-2016 Dictamen de Construcción' => 'NOM-005-ASEA- Dictamen de Construcción',
        'NOM-005-ASEA-2016 Dictamen de Diseño' => 'NOM-005-ASEA- Dictamen de Diseño',
        'NOM-005-ASEA-2016 Dictamen de operación y mantenimiento' => 'NOM-005-ASEA- Dictamen de operación y mantenimiento',
        // Confirmado con negocio: mismo requerimiento, al Excel de vigencias le falta el "de" (ES Colonias).
        'NOM-005-ASEA-2016 Dictamen Operación y Mantenimiento' => 'NOM-005-ASEA- Dictamen de operación y mantenimiento',
        'NOM-005-ASEA-2016 Dossier de obra' => 'NOM-005-ASEA- Dossier de obra',
        'NOM-005-ASEA-2016 Proyecto Ejecutivo' => 'NOM-005-ASEA- Proyecto Ejecutivo',
        'NOM-016-CRE-2016 Dictamen de Calidad de Petroliferos' => 'NOM-016-- Dictamen de Calidad de Petroliferos',
        'NOM-016-CRE-2016 Muestreo de laboratorio de Calidad de Petroliferos' => 'NOM-016-- Muestreo de laboratorio de Calidad de Petroliferos',
        'NOM-185-SCFI-2011 Modelo prototipo software de dispensarios' => 'NOM-185-SCFI- Modelo prototipo software de dispensarios',

        // Confirmado con negocio: mismo requerimiento con nombre abreviado en la carpeta
        // entregada por la estación ES Linares 3.
        'Controles volumetricos' => 'ANEXO 21-22 Certificado de Control Volúmetrico',
        'Manifiesto de Impacto Ambiental' => 'Manifiesto de Impacto Ambiental / Informe Preventivo',
        'Pólizas de seguros RC' => 'Pólizas de seguros RC y RCA+ Determinación de los Límites',
        'NOM-016-CRE-2016 Muestreo de Laboratorio' => 'NOM-016-CRE-2016 Muestreo de laboratorio de Calidad de Petroliferos',
        'Escritura de inmueble o Contrato de Arrendamiento'
            => 'Escritura de inmueble o Contrato de Arrendamiento que avale propiedad o posesión del inmueble',
        // Confirmado con negocio: mismo requerimiento, redactado distinto en el Excel de vigencias (ES Barquito).
        'Escritura/Contrato de Arrendamiento'
            => 'Escritura de inmueble o Contrato de Arrendamiento que avale propiedad o posesión del inmueble',
        // Confirmado con negocio: mismo requerimiento, redactado distinto en el Excel de vigencias (ES Crucero).
        'Contrato de arrendamiento'
            => 'Escritura de inmueble o Contrato de Arrendamiento que avale propiedad o posesión del inmueble',

        // Confirmado con negocio: "PH" en el alias de arriba es "Prueba de Hermeticidad", así
        // que la prueba de hermeticidad se archiva bajo el mismo requerimiento.
        'Prueba de hermeticidad' => 'Pruebas de integridad mecánica',
    ],
];
