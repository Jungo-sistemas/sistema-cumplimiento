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
                    ['texto' => 'El almacén temporal cuenta con bitácora de entradas.',      'resultado' => 'parcial',   'calificacion' => 90,  'observacion' => 'La bitácora existe, pero le faltan las entradas del fin de semana.'],
                    ['texto' => 'El manifiesto de entrega está firmado por el transportista.', 'resultado' => 'cumple',  'calificacion' => 100, 'observacion' => 'El manifiesto mostrado está completo y firmado.'],
                ],
                'hallazgos' => [
                    ['codigo' => 'HAL-004', 'titulo' => 'Contenedores de residuos sin etiqueta', 'empresa' => 'Asesorías Daval', 'responsable' => 'Ana Torres', 'severidad' => 'Mayor', 'seguimiento' => 'Rechazada', 'compromiso' => '2026-09-10'],
                ],
            ],
            4 => [
                'procedimiento' => 'Control de documentos', 'auditado' => 'Luis Herrera', 'fecha' => '20 ago 2026',
                'criterios' => [
                    ['texto' => 'Los documentos en planta están en su versión vigente.', 'resultado' => 'cumple', 'calificacion' => 95, 'observacion' => 'Las versiones en planta coinciden con el sistema.'],
                    ['texto' => 'Los cambios tienen registro de aprobación.',            'resultado' => 'cumple',    'calificacion' => 89,   'observacion' => 'Cada cambio revisado tiene su aprobación.'],
                    ['texto' => 'Los documentos obsoletos están retirados del área.',    'resultado' => 'no_aplica', 'calificacion' => null, 'observacion' => 'El área ya no maneja copias impresas.'],
                ],
                'hallazgos' => [],
            ],
        ];

        // $audit es el número que viene en la dirección (/auditorias/3/resultado). Si no existe: 404.
        abort_unless(isset($auditorias[$audit]), 404);
        $auditoria = $auditorias[$audit];

        // La calificación final es el promedio de los criterios evaluados ("No aplica" no cuenta);
        // lo que hay que corregir son los "No cumple" y los "Parcial".
        $criterios       = collect($auditoria['criterios']);
        $calificacion    = round($criterios->where('resultado', '!=', 'no_aplica')->avg('calificacion'));
        $incumplimientos = $criterios->whereIn('resultado', ['no_cumple', 'parcial']);

        // Etiqueta y color de cada resultado del checklist.
        $resultados = [
            'cumple'    => ['texto' => 'Cumple',    'color' => 'bg-gray-100 text-gray-700'],
            'parcial'   => ['texto' => 'Parcial',   'color' => 'bg-amber-50 text-amber-700'],
            'no_cumple' => ['texto' => 'No cumple', 'color' => 'bg-red-50 text-red-700'],
            'no_aplica' => ['texto' => 'No aplica', 'color' => 'border border-gray-200 bg-white text-gray-500'],
        ];

        // Mismos colores de severidad que la lista de Hallazgos.
        $severidades = [
            'Crítico' => 'bg-red-50 text-red-700',
            'Mayor'   => 'bg-amber-50 text-amber-700',
            'Menor'   => 'bg-brand-50 text-brand',
        ];

        // La ventana de detalle usa los dos para pintar sus etiquetas (los mismos de la lista de Hallazgos).
        $seguimientos = [
            'Por enviar'             => 'bg-gray-100 text-gray-700',
            'Causa raíz por revisar' => 'bg-brand-50 text-brand',
            'Rechazada'              => 'bg-red-50 text-red-700',
            'Sin cargar'             => 'bg-amber-50 text-amber-700',
            'Evidencia por revisar'  => 'bg-brand-50 text-brand',
            'Cerrado'                => 'bg-green-50 text-green-700',
        ];
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
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $resultados[$criterio['resultado']]['color'] }}">{{ $resultados[$criterio['resultado']]['texto'] }}</span>
                </div>
            @empty
                <p class="py-5 text-[15px] text-gray-600">El auditor no marcó criterios como Parcial o No cumple.</p>
            @endforelse
        </div>
    </div>

    {{-- Hallazgos que el auditor registró en esta auditoría --}}
    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Hallazgos registrados por el auditor</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($auditoria['hallazgos'] as $hallazgo)
                <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $hallazgo['titulo'] }}</div>
                        <p class="mt-1 text-sm text-gray-500">{{ $hallazgo['codigo'] }} · {{ $hallazgo['seguimiento'] }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $severidades[$hallazgo['severidad']] }}">{{ $hallazgo['severidad'] }}</span>
                        <button type="button" @click="$dispatch('abrir-hallazgo', @js($hallazgo))"
                            class="whitespace-nowrap rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">Ver hallazgo</button>
                    </div>
                </div>
            @empty
                <p class="py-5 text-[15px] text-gray-600">No se registraron hallazgos en esta auditoría.</p>
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
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $resultados[$criterio['resultado']]['color'] }}">{{ $resultados[$criterio['resultado']]['texto'] }}</span>
                            </td>
                            <td class="px-4 py-4 text-right text-gray-900">{{ $criterio['calificacion'] ?? '—' }}</td>
                            <td class="px-4 py-4 text-gray-700">{{ $criterio['observacion'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>

    @include('auditorias.partials.finding-detail-modal')
</x-layouts.vigia>
