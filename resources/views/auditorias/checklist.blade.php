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

    {{-- Todo lo de adentro comparte estos datos "vivos" de Alpine --}}
    <div x-data="{
            respuestas: @js(array_fill(0, count($criterios), ['resultado' => '', 'calificacion' => ''])),
            guardado: false,
            tieneCalificacion(r) { return r.calificacion !== '' && r.calificacion !== null },
            fueraDeRango(r) { return this.tieneCalificacion(r) && (r.calificacion < 0 || r.calificacion > 100) },
            capturado(r) { return r.resultado !== '' && this.tieneCalificacion(r) && ! this.fueraDeRango(r) },
            get capturados() { return this.respuestas.filter(r => this.capturado(r)).length },
         }">

        {{-- Sesión de Teams y avance --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Sesión por Microsoft Teams</h2>
                <p class="mt-2 text-sm text-gray-600">La captura corresponde al auditor asignado cuando la auditoría esté activa.</p>
            </div>
            <div class="text-sm font-semibold text-brand"><span x-text="capturados">0</span> de {{ count($criterios) }} criterios capturados</div>
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
                            <select id="resultado-{{ $i }}" name="respuestas[{{ $i }}][resultado]" x-model="respuestas[{{ $i }}].resultado"
                                    class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand">
                                <option value="">Selecciona</option>
                                <option value="cumple">Cumple</option>
                                <option value="no_cumple">No cumple</option>
                            </select>
                        </div>
                        <div>
                            <label for="calificacion-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Calificación · 0 a 100</label>
                            <input id="calificacion-{{ $i }}" type="number" min="0" max="100" name="respuestas[{{ $i }}][calificacion]"
                               x-model.number="respuestas[{{ $i }}].calificacion"
                               class="h-12 w-full rounded-lg bg-gray-50 text-[15px] focus:border-brand focus:ring-brand"
                               :class="fueraDeRango(respuestas[{{ $i }}]) ? 'border-red-500' : 'border-gray-200'">
                        <p x-show="fueraDeRango(respuestas[{{ $i }}])" x-cloak class="mt-1 text-sm text-red-600">Debe ser un número de 0 a 100.</p>
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
        {{-- Botones. En la demo todavía no guardan en la base de datos. --}}
        <div class="mt-6 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-6">
            <span x-show="guardado" x-cloak x-transition class="mr-auto text-sm font-semibold text-green-700">Avance guardado (demo: todavía no se guarda en la base de datos).</span>
            <span x-show="! guardado && capturados < {{ count($criterios) }}" class="mr-auto text-sm text-gray-600">Para finalizar, captura resultado y calificación en todos los criterios.</span>

            <button type="button" @click="guardado = true; setTimeout(() => guardado = false, 3000)"
                    class="rounded-lg border-2 border-brand px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-50">Guardar avance</button>

            <button type="button" :disabled="capturados < {{ count($criterios) }}"
                    class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50">Finalizar</button>
        </div>
    </div>
</x-layouts.vigia>
