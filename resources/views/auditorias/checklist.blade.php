<x-layouts.vigia title="Checklist · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index') }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Checklist de auditoría</span>
    </x-slot>

    @php
        // Datos de ejemplo para la demo: las auditorías en ejecución, con el mismo número (id) que usará la lista.
        $auditorias = [
            1 => ['procedimiento' => 'Control de maniobras y seguridad', 'auditado' => 'Laura Méndez',  'estado' => 'En captura'],
            2 => ['procedimiento' => 'Recepción de materia prima',       'auditado' => 'Jorge Salinas', 'estado' => 'Programada'],
        ];

        // $audit es el número que viene en la dirección (/auditorias/1/checklist). Si no existe: 404.
        abort_unless(isset($auditorias[$audit]), 404);
        $auditoria = $auditorias[$audit];

        // Criterios del checklist (datos de ejemplo). Más adelante cada auditoría trae los suyos de la base de datos.
        $criterios = [
            ['texto' => 'La actividad cuenta con registro trazable.',     'evidencia' => 'Bitácora fechada'],
            ['texto' => 'Los responsables conocen el procedimiento.',     'evidencia' => 'Registro de capacitación'],
            ['texto' => 'Las evidencias están completas y actualizadas.', 'evidencia' => 'Expediente y revisión firmada'],
        ];

    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index') }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $auditoria['estado'] === 'En captura' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand' }}">{{ $auditoria['estado'] }}</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Checklist de auditoría',
        'subtitle' => $auditoria['procedimiento'] . ' · ' . $auditoria['auditado'],
    ])

    {{-- Sesión de Teams y avance --}}
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Sesión por Microsoft Teams</h2>
            <p class="mt-2 text-sm text-gray-600">La captura corresponde al auditor asignado cuando la auditoría esté activa.</p>
        </div>
        {{-- En la parte 2d este número se actualiza solo mientras capturas --}}
        <div class="text-sm font-semibold text-brand">0 de {{ count($criterios) }} criterios capturados</div>
    </div>

    {{-- Un bloque por criterio --}}
    <div class="space-y-6">
        @foreach($criterios as $i => $criterio)
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <h3 class="text-lg font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio['texto'] }}</h3>
                <p class="mt-1 text-sm text-gray-600"><span class="font-semibold text-gray-900">Evidencia a verificar:</span> {{ $criterio['evidencia'] }}</p>

                <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                    <div>
                        <label for="resultado-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Resultado del criterio</label>
                        <select id="resultado-{{ $i }}" name="respuestas[{{ $i }}][resultado]"
                                class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand">
                            <option value="">Selecciona</option>
                            <option value="cumple">Cumple</option>
                            <option value="no_cumple">No cumple</option>
                        </select>
                    </div>
                    <div>
                        <label for="calificacion-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Calificación · 0 a 100</label>
                        <input id="calificacion-{{ $i }}" type="number" min="0" max="100" name="respuestas[{{ $i }}][calificacion]"
                               class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand">
                    </div>
                </div>

                <div class="mt-5">
                    <label for="observaciones-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Observaciones del auditor</label>
                    <textarea id="observaciones-{{ $i }}" name="respuestas[{{ $i }}][observaciones]" rows="3"
                              placeholder="Describe lo que verificaste y, si hay un incumplimiento, explica qué se hizo incorrectamente."
                              class="w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand"></textarea>
                </div>
            </div>
        @endforeach
    </div>
</x-layouts.vigia>
