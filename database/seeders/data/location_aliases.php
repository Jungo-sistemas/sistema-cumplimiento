<?php

// Alias de "location" (ubicación de un activo) -> nombre canónico a mostrar y usar para
// agrupar. Las diferencias de mayúsculas/acentos/espacios ya se normalizan automáticamente
// (ver AssetController::normalizeLocationKey), así que esto es solo para abreviaturas o
// nombres realmente distintos que representan el mismo lugar.

return [
    'SLP' => 'San Luis Potosí',
    'S.L.P.' => 'San Luis Potosí',
];
