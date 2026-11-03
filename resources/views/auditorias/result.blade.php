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

    {{-- Qué necesitas corregir: solo los criterios "No cumple" --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Qué necesitas corregir</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($incumplimientos as $i => $criterio)
                <div class="flex flex-wrap items-start justify-between gap-4 py-5">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio['texto'] }}</div>
                        <p class="mt-1 text-[15px] text-gray-700">{{ $criterio['observacion'] }}</p>
                        <p class="mt-3 text-sm text-gray-500">Calificación del criterio: {{ $criterio['calificacion'] }} / 100</p>
                    </div>
                    <span class="rounded-full bg-red-50 px-3 py-1 text-xs font-semibold text-red-700">No cumple</span>
                </div>
            @empty
                <p class="py-5 text-[15px] text-gray-600">Todos los criterios se cumplieron. No hay nada que corregir.</p>
            @endforelse
        </div>
    </div>

    {{-- Evaluación completa: se abre y se cierra con un clic --}}
    <details class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <summary class="cursor-pointer text-base font-semibold text-brand">Consultar evaluación completa</summary>

        <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-4">Criterio</th>
                        <th class="px-4 py-4">Resultado</th>
                        <th class="px-4 py-4 text-right">Calificación</th>
                        <th class="px-4 py-4">Observación del auditor</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @foreach($criterios as $criterio)
                        <tr>
                            <td class="px-4 py-4 text-gray-900">{{ $criterio['texto'] }}</td>
                            <td class="px-4 py-4">
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $criterio['resultado'] === 'cumple' ? 'bg-gray-100 text-gray-700' : 'bg-red-50 text-red-700' }}">{{ $criterio['resultado'] === 'cumple' ? 'Cumple' : 'No cumple' }}</span>
                            </td>
                            <td class="px-4 py-4 text-right text-gray-900">{{ $criterio['calificacion'] }}</td>
                            <td class="px-4 py-4 text-gray-700">{{ $criterio['observacion'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    {{-- Al construir la ventana de "Registrar hallazgo", este botón la abrirá con esta auditoría ya elegida --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <button type="button" class="rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">Registrar hallazgo</button>
    </div>
</x-layouts.vigia>
