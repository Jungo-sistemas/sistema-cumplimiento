<?php

return [

    /*
    | Valor fijo de cada resultado del checklist. El auditor ya no escribe la calificación
    | (así se evitan sesgos): cada criterio vale lo que diga su resultado, y la calificación
    | final es el promedio. "no_aplica" no está en la lista porque no cuenta en el promedio.
    |
    | Valores temporales: falta que la cliente los confirme.
    */
    'ponderaciones' => [
        'cumple'    => 100,
        'parcial'   => 65,
        'no_cumple' => 0,
    ],

];
