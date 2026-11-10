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
        // Solo "En captura" se llena. Una "Programada" se puede consultar, pero todavía no se captura.
        $editable = $auditoria['estado'] === 'En captura';

        // "descripcion" explica qué significa el criterio, para que el auditor sepa qué está evaluando.
        $criterios = [
            [
                'texto'       => 'La actividad cuenta con registro trazable.',
                'descripcion' => 'Cada vez que se realiza la actividad queda un registro con fecha, responsable y resultado, de forma que se puede reconstruir qué se hizo y quién lo hizo.',
                'evidencia'   => 'Bitácora fechada',
            ],
            [
                'texto'       => 'Los responsables conocen el procedimiento.',
                'descripcion' => 'Las personas que realizan la actividad saben qué pasos seguir y fueron capacitadas en la versión vigente del procedimiento. Puedes confirmarlo pidiéndoles que expliquen cómo la hacen.',
                'evidencia'   => 'Registro de capacitación',
            ],
            [
                'texto'       => 'Las evidencias están completas y actualizadas.',
                'descripcion' => 'Están todos los documentos que respaldan la actividad, corresponden al periodo auditado y tienen las firmas o aprobaciones que pide el procedimiento.',
                'evidencia'   => 'Expediente y revisión firmada',
            ],
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
            confirmando: false,
            finalizada: false,
            tieneCalificacion(r) { return r.calificacion !== '' && r.calificacion !== null },
            fueraDeRango(r) { return this.tieneCalificacion(r) && (r.calificacion < 0 || r.calificacion > 100) },
            noAplica(r) { return r.resultado === 'no_aplica' },
            capturado(r) { return r.resultado !== '' && (this.noAplica(r) || (this.tieneCalificacion(r) && ! this.fueraDeRango(r))) },
            get capturados() { return this.respuestas.filter(r => this.capturado(r)).length },
            get promedio() {
                {{-- "No aplica" no cuenta: el promedio es solo de los criterios evaluados. --}}
                const evaluados = this.respuestas.filter(r => ! this.noAplica(r));
                if (evaluados.length === 0) return '—';
                return Math.round(evaluados.reduce((suma, r) => suma + r.calificacion, 0) / evaluados.length);
            },
         }">

        {{-- Sesión de Teams y avance --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Sesión por Microsoft Teams</h2>
                <p class="mt-2 text-sm text-gray-600">{{ $editable ? 'Registra las respuestas, las observaciones y la calificación durante la llamada.' : 'La captura corresponde al auditor asignado cuando la auditoría esté activa.' }}</p>
            </div>
            <div class="text-sm font-semibold text-brand"><span x-text="capturados">0</span> de {{ count($criterios) }} criterios capturados</div>
        </div>

        {{-- Un bloque por criterio --}}
        <fieldset :disabled="@js(! $editable) || confirmando || finalizada" class="space-y-6">
            @foreach($criterios as $i => $criterio)
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <h3 class="text-lg font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio['texto'] }}</h3>
                    <p class="mt-1 text-[15px] text-gray-700">{{ $criterio['descripcion'] }}</p>
                    <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-900">Evidencia a verificar:</span> {{ $criterio['evidencia'] }}</p>

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="resultado-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Resultado del criterio</label>
                            <select id="resultado-{{ $i }}" name="respuestas[{{ $i }}][resultado]" x-model="respuestas[{{ $i }}].resultado"
                                    @change="if ($event.target.value === 'no_aplica') respuestas[{{ $i }}].calificacion = ''"
                                    class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand">
                                <option value="">Selecciona</option>
                                <option value="cumple">Cumple</option>
                                <option value="no_cumple">No cumple</option>
                                <option value="parcial">Parcial</option>
                                <option value="no_aplica">No aplica</option>
                            </select>
                        </div>
                        <div>
                            <label for="calificacion-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Calificación · 0 a 100</label>
                            <input id="calificacion-{{ $i }}" type="number" min="0" max="100" name="respuestas[{{ $i }}][calificacion]"
                               x-model.number="respuestas[{{ $i }}].calificacion"
                               :disabled="noAplica(respuestas[{{ $i }}])"
                               :placeholder="noAplica(respuestas[{{ $i }}]) ? 'No aplica' : ''"
                               class="h-12 w-full rounded-lg bg-gray-50 text-[15px] focus:border-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400"
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

                    @if($editable)
                        <div class="mt-5">
                            <label for="soporte-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Soporte de la revisión (opcional)</label>
                            <input id="soporte-{{ $i }}" type="file" name="respuestas[{{ $i }}][soportes][]" multiple
                                   class="block w-full rounded-lg border border-gray-200 bg-gray-50 p-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand">
                        </div>

                        {{-- El hallazgo queda ligado a este criterio: la ventana lo recibe y no deja cambiarlo --}}
                        <div class="mt-5 flex flex-wrap items-center justify-between gap-4 border-t border-gray-200 pt-5">
                            <p class="text-sm text-gray-600">Registra los hallazgos que identifiques en este criterio.</p>
                            <button type="button" @click="$dispatch('registrar-hallazgo', { criterio: {{ $i }} })"
                                    class="rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">Registrar hallazgo</button>
                        </div>
                    @endif
                </div>
            @endforeach

            {{-- Conclusiones generales de toda la auditoría, no de un criterio --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <label for="conclusiones" class="mb-2 block text-sm font-semibold text-gray-900">Conclusiones de la auditoría</label>
                <textarea id="conclusiones" name="conclusiones" rows="4"
                          placeholder="Resume lo más importante: qué se cumple, qué hay que corregir y qué se acordó con el auditado."
                          class="w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            </div>
        </fieldset>

        {{-- Botones. En la demo todavía no guardan en la base de datos. --}}
        @if($editable)
            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-6" x-show="! confirmando && ! finalizada">
                <span x-show="guardado" x-cloak x-transition class="mr-auto text-sm font-semibold text-green-700">Avance guardado (demo: todavía no se guarda en la base de datos).</span>
                <span x-show="! guardado && capturados < {{ count($criterios) }}" class="mr-auto text-sm text-gray-600">Para finalizar, captura el resultado de cada criterio y su calificación (excepto los que no aplican).</span>

                <button type="button" @click="guardado = true; setTimeout(() => guardado = false, 3000)"
                        class="rounded-lg border-2 border-brand px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-50">Guardar avance</button>

                <button type="button" :disabled="capturados < {{ count($criterios) }}"
                        class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50" @click="confirmando = true">Finalizar</button>
            </div>
        @endif

        {{-- Pregunta antes de finalizar --}}
        <div x-show="confirmando" x-cloak x-transition class="mt-6 rounded-2xl border-2 border-brand-100 bg-brand-50 p-6">
            <p class="font-semibold text-brand-ink">¿Finalizar la auditoría?</p>
            <p class="mt-1 text-sm text-gray-600">Ya no podrás cambiar las respuestas. La calificación final será de <span class="font-semibold text-gray-900" x-text="promedio"></span> / 100.</p>
            <div class="mt-4 flex justify-end gap-3">
                <button type="button" @click="confirmando = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" @click="finalizada = true; confirmando = false" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Sí, finalizar</button>
            </div>
        </div>

        {{-- Ya finalizada (demo): con la base de datos, aquí ya se habrá guardado --}}
        <div x-show="finalizada" x-cloak class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-6">
            <p class="font-semibold text-green-800">Auditoría finalizada (demo). Calificación final: <span x-text="promedio"></span> / 100.</p>
            <p class="mt-1 text-sm text-green-700">Cuando exista la base de datos, se guardará y aparecerá en la pestaña Finalizadas.</p>
            <a href="{{ route('auditorias.audits.index', ['tab' => 'finalizadas']) }}" class="mt-4 inline-flex rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Ir a Finalizadas</a>
        </div>
    </div>

    @if($editable)
        @include('auditorias.partials.register-finding-modal')
    @endif
</x-layouts.vigia>
