<?php

return [

    /*
    | Días naturales antes de la fecha que fija el auditor en que el auditado recibe el aviso de su
    | auditoría. Antes de eso no la ve en Mis auditorías.
    */
    'aviso_dias_antes' => (int) env('AUDITORIAS_AVISO_DIAS_ANTES', 3),

    /*
    | Valor fijo de cada resultado del checklist. El auditor ya no escribe la calificación
    | (así se evitan sesgos): cada criterio vale lo que diga su resultado, y la calificación
    | final es el promedio. "no_aplica" no está en la lista porque no cuenta en el promedio.
    |
    | Valores confirmados por la cliente (17 nov 2026).
    */
    'ponderaciones' => [
        'cumple'    => 100,
        'parcial'   => 50,
        'no_cumple' => 0,
    ],

    /*
    | Plazos de cada etapa de un hallazgo, en días hábiles (lunes a viernes sin días de descanso
    | obligatorio de la LFT ni los de 'dias_inhabiles'). Cada etapa arranca cuando el hallazgo
    | llega a ella; si el auditor lo devuelve, la etapa vuelve a empezar con su plazo completo.
    |
    |   causa      Auditado: causa raíz y plan de acciones
    |   validar    Auditor: visto bueno o devolverla con observaciones
    |   corregir   Auditado: ejecuta las acciones y sube la evidencia (seguimiento)
    |   verificar  Auditor: confirma que la acción se implementó y cierra
    |
    | Lámina del cliente (nov 2026): crítico 6 días hábiles en total (más la contención en 24 h);
    | no crítico 20.
    */
    'plazos_hallazgos' => [
        'critico'    => ['causa' => 1, 'validar' => 1, 'corregir' => 2, 'verificar' => 1],
        'no_critico' => ['causa' => 3, 'validar' => 2, 'corregir' => 10, 'verificar' => 5],
    ],

    // Cierre total que se le comunica al auditado (lámina): el crítico suma el día de la contención.
    'cierre_hallazgos' => [
        'critico'    => 6,
        'no_critico' => 20,
    ],

    // Un hallazgo crítico pide una contención inmediata: se le recuerda al auditado.
    'contencion_critico_horas' => 24,

    // Días inhábiles adicionales a los de la LFT (AAAA-MM-DD), p. ej. un puente que da la empresa.
    'dias_inhabiles' => [],

];
