<x-layouts.vigia title="Resultado · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index', ['tab' => 'finalizadas']) }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Resultado de auditoría</span>
    </x-slot>

    @php
        // Datos de ejemplo para la demo: las auditorías finalizadas, con el mismo número (id) que usa la lista.
        $auditorias = [
            3 => [
                'procedimiento' => 'Manejo de residuos peligrosos', 'auditado' => 'Ana Torres', 'fecha' => '28 ago 2026',
                'criterios' => [
                    ['texto' => 'Los contenedores están identificados con su etiqueta.',     'resultado' => 'no_cumple', 'calificacion' => 65, 'observacion' => 'Dos contenedores del almacén no tienen etiqueta de identificación.'],
                    ['texto' => 'El almacén temporal cuenta con bitácora de entradas.',      'resultado' => 'cumple',    'calificacion' => 95, 'observacion' => 'La bitácora mostrada durante la llamada está al día.'],
                    ['texto' => 'El manifiesto de entrega está firmado por el transportista.', 'resultado' => 'cumple',  'calificacion' => 95, 'observacion' => 'El manifiesto mostrado está completo y firmado.'],
                ],
            ],
            4 => [
                'procedimiento' => 'Control de documentos', 'auditado' => 'Luis Herrera', 'fecha' => '20 ago 2026',
                'criterios' => [
                    ['texto' => 'Los documentos en planta están en su versión vigente.', 'resultado' => 'cumple', 'calificacion' => 95, 'observacion' => 'Las versiones en planta coinciden con el sistema.'],
                    ['texto' => 'Los cambios tienen registro de aprobación.',            'resultado' => 'cumple', 'calificacion' => 90, 'observacion' => 'Cada cambio revisado tiene su aprobación.'],
                    ['texto' => 'Los documentos obsoletos están retirados del área.',    'resultado' => 'cumple', 'calificacion' => 91, 'observacion' => 'No se encontraron copias obsoletas en el área.'],
                ],
            ],
        ];

        // $audit es el número que viene en la dirección (/auditorias/3/resultado). Si no existe: 404.
        abort_unless(isset($auditorias[$audit]), 404);
        $auditoria = $auditorias[$audit];

        // La calificación final es el promedio de los criterios; los incumplimientos son los "No cumple".
        $criterios       = collect($auditoria['criterios']);
        $calificacion    = round($criterios->avg('calificacion'));
        $incumplimientos = $criterios->where('resultado', 'no_cumple');
    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index', ['tab' => 'finalizadas']) }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">Finalizada</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Resultado de auditoría',
        'subtitle' => $auditoria['procedimiento'] . ' · ' . $auditoria['auditado'] . ' · ' . $auditoria['fecha'],
    ])

    {{-- Tarjetas resumen --}}
    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Calificación final</div>
            <div class="mt-2 text-3xl font-extrabold text-brand-ink">{{ $calificacion }} / 100</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Criterios con incumplimientos</div>
            <div class="mt-2 text-3xl font-extrabold text-brand-ink">{{ $incumplimientos->count() }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Auditor</div>
            <div class="mt-2 text-xl font-bold text-brand-ink">{{ auth()->user()->name }}</div>
        </div>
    </div>
</x-layouts.vigia>
