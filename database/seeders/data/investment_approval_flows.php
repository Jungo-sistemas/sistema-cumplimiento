<?php

/*
|--------------------------------------------------------------------------
| Flujos fijos de aprobación de Anexos 8 (Solicitud de Inversiones)
|--------------------------------------------------------------------------
| Tabla del cliente (nov 2026): Tenedora → Unidad de Negocio → Razón social, con quién aprueba en
| cada puesto. Se carga con `php artisan investments:sync-approval-flows` (se puede volver a correr
| las veces que haga falta: rehace los flujos y liga a las personas que ya tengan usuario).
|
| - 'name': la razón social tal como aparece en la lista "Empresa" al capturar. Esta lista es
|   exactamente la que se muestra, en este orden.
| - 'company': la empresa EN EL SISTEMA a la que pertenece (se compara sin acentos, sin mayúsculas
|   y sin "SA DE CV"). null = todavía no existe: la razón social se muestra igual y la solicitud
|   queda a nivel grupo. Al darla de alta, poner aquí su nombre y volver a correr el comando.
| - Puestos: el orden de aprobación es el de InvestmentApprovalService::POSITIONS
|   (Gerente → DD → CFO → CEO → Consejero). null = ese puesto no aplica ("NA" o vacío).
| - Personas: nombre completo tal como está (o estará) en su usuario, o su correo.
*/

$consejero = 'Miguel Garza Treviño';
$ceo       = 'Francisco Meillon Orozco';
$cfo       = 'Luvia Hernandez';
$federico  = 'Federico Martinez Navarro';
$daniel    = 'Jose Daniel Aguirre Chavarria';
$antonio   = 'Antonio Castillo Galindo';
$carlos    = 'Carlos Alonso Vargas Rojas';
$fabian    = 'Fabian Escudero Gonzalez';
$isett     = 'Isett Rodriguez Guzman';
$victor    = 'Victor Sena Buenrostro';

$row = fn (string $name, string $holding, string $unit, ?string $company, ?string $dd, ?string $gerente = null) => [
    'name'      => $name,
    'holding'   => $holding,
    'unit'      => $unit,
    'company'   => $company,
    'Gerente'   => $gerente,
    'DD'        => $dd,
    'CFO'       => $cfo,
    'CEO'       => $ceo,
    'Consejero' => $consejero,
];

return [
    // ── Vigia Combustibles ──
    $row('Mercantil Distribuidora', 'Vigia Combustibles', 'Gas y Gasolina', 'MERCANTIL DISTRIBUIDORA', $federico),
    $row('Energéticos Cargo Más', 'Vigia Combustibles', 'Gas', null, $federico),
    $row('Gas Villa de Reyes', 'Vigia Combustibles', 'Villa', 'GASERA VILLA DE REYES', $federico),
    $row('Kiwi Gas', 'Vigia Combustibles', 'Kiwi', 'VERITAS ENERGÍA (KIWI GAS)', $federico, 'Eric Mares Benavides'),
    $row('Combustibles TG', 'Vigia Combustibles', 'Gasolina', 'COMBUSTIBLES TG', $federico, $antonio),
    $row('El Ébano', 'Vigia Combustibles', 'Gasolina', 'GASOLINERA EBANO', $federico, $antonio),
    $row('Super Servicios 54', 'Vigia Combustibles', 'Gasolina', 'SUPER SERVICIO 54', $federico, $antonio),
    $row('Servicios Ramírez', 'Vigia Combustibles', 'Gasolina', 'SERVICIO RAMIREZ DE TEPATITLAN', $federico, $antonio),
    $row('Corporación Energía Veritas', 'Vigia Combustibles', 'Gasolina', null, $federico, $antonio),
    $row('Propane Services', 'Vigia Combustibles', 'Propane Services', 'PROPANE SERVICES', $federico),
    $row('Soluciones Petrolíferas', 'Vigia Combustibles', 'Propane Services', null, $federico),
    $row('Alcom', 'Vigia Combustibles', 'Alcom', 'ALCOM', $federico, 'Vicente Saint Martin Ochoa'),
    $row('Videnci de México', 'Vigia Combustibles', 'Videnci', 'VIDENCI', $federico),
    $row('Grupo Vigía', 'Vigia Combustibles', 'Vigia Combustibles', null, null),

    // ── Intera ──
    $row('Asesorías Daval', 'Intera', 'DAVAL', 'ASESORÍAS DAVAL', $daniel, 'Alma Cruz Bustamante'),
    $row('Transportes Migar', 'Intera', 'Transportes Migar', 'TRANSPORTES MIGAR', $daniel, $carlos),
    $row('Propane Logística', 'Intera', 'Transportes Migar', null, $daniel, $carlos),
    $row('Soluciones para el Transporte', 'Intera', 'GPS', 'SOLUCIONES TECNOLOGICAS PARA EL TRANSPORTE', null, $fabian),
    $row('Troncalnet', 'Intera', 'GPS', 'TRONCALNET', null, $fabian),
    $row('Jungo', 'Intera', 'Jungo', 'JUNGO', 'Nicolas Garza Perez Obregon'),
    $row('Pacto', 'Intera', 'Pacto', 'PACTO', 'Miguel Garza Perez Obregon'),
    $row('Intera', 'Intera', 'Intera', 'INTERA CAPITAL', null),

    // ── Biomex ── (en el sistema las dos razones sociales son la empresa BIOMEX)
    $row('Parque Estrella', 'Biomex', 'Biomex', 'BIOMEX', $isett),
    $row('IBPME', 'Biomex', 'Biomex', 'BIOMEX', $isett),

    // ── Terrenos ──
    $row('Terrenos Vigía', 'Terrenos', 'Terrenos', 'Terrenos Vigia', $victor),
    $row('Terrenos Todo Gas', 'Terrenos', 'Terrenos', 'TERRENOS TODOGAS', $victor),
];
