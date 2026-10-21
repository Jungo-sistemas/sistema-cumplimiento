@props(['mode'])

@php
    $isAdmin = auth()->user()->isAdmin();
    $isOperative = auth()->user()->isOperative();

    $steps = [
        [
            'title'  => 'Bienvenido a Procesos',
            'body'   => 'Aquí se gestionan los reglamentos y procedimientos de la empresa: su redacción, aprobación y vigencia. Esta guía te va mostrando cada parte, resaltando justo lo que puedes usar según tu rol.',
            'url'    => null,
            'target' => null,
        ],
        [
            'title'  => 'Tablero',
            'body'   => 'Es la pantalla de inicio del módulo.',
            'url'    => route('processes.dashboard'),
            'target' => 'nav-tablero',
        ],
        [
            'title'  => 'Resumen',
            'body'   => 'Aquí ves cuántos procedimientos hay, cuáles están vencidos o por vencer, y la actividad reciente.',
            'url'    => route('processes.dashboard'),
            'target' => 'dashboard-summary',
        ],
        [
            'title'  => 'Procedimientos',
            'body'   => 'La lista principal, con pestañas para ver los Procedimientos y sus Anexos por separado.',
            'url'    => route('processes.index'),
            'target' => 'nav-procedimientos',
        ],
        [
            'title'  => 'La lista',
            'body'   => 'Aquí aparecen todos — haz clic en cualquiera para ver su versión vigente, el histórico, y quién lo aprobó.',
            'url'    => route('processes.index'),
            'target' => 'processes-table',
        ],
    ];

    if ($isAdmin || $isOperative) {
        $steps[] = [
            'title'  => 'Nuevo procedimiento',
            'body'   => 'Abre un asistente que te ayuda a redactarlo desde cero, con apoyo de IA para el primer borrador.',
            'url'    => route('processes.index'),
            'target' => 'btn-nuevo-procedimiento',
        ];
    }

    if ($isAdmin) {
        $steps[] = [
            'title'  => 'Cargar proceso',
            'body'   => 'Si ya tienes un documento existente, lo subes directo desde aquí en vez de redactarlo en el asistente.',
            'url'    => route('processes.index'),
            'target' => 'btn-cargar',
        ];
        $steps[] = [
            'title'  => 'Obsoleto',
            'body'   => 'Aquí se consultan los reglamentos ya marcados como obsoletos, sin que se mezclen con la lista principal.',
            'url'    => route('processes.index'),
            'target' => 'btn-obsoleto',
        ];
        $steps[] = [
            'title'  => 'Papelera',
            'body'   => 'Los procedimientos eliminados quedan aquí antes de borrarse para siempre — puedes recuperarlos o eliminarlos de forma definitiva.',
            'url'    => route('processes.index'),
            'target' => 'btn-papelera',
        ];
    }

    $steps[] = [
        'title'  => 'Mis aprobaciones',
        'body'   => 'Si tienes un procedimiento pendiente de tu aprobación, aparece aquí — con un contador en el menú que te avisa cuántos tienes pendientes.',
        'url'    => route('my-approvals.index'),
        'target' => 'mis-aprobaciones-content',
    ];
@endphp

<script>
document.addEventListener('DOMContentLoaded', function () {
    window.initProcesosGuide(
        @json($steps),
        @json($mode),
        @json(route('processes.guide.complete'))
    );
});
</script>
