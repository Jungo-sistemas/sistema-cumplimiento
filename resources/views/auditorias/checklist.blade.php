
<x-layouts.vigia title="Checklist · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index') }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Checklist de auditoría</span>
    </x-slot>

    @php
        // Datos de ejemplo para la demo: las auditorías en ejecución, con el mismo número (id) que usará la lista.
        $auditorias = [
            1 => ['procedimiento' => 'Control de maniobras y seguridad', 'auditado' => 'Laura Méndez',  'estado' => 'En captura', 'fecha' => '11 sep 2026'],
            2 => ['procedimiento' => 'Recepción de materia prima',       'auditado' => 'Jorge Salinas', 'estado' => 'Programada', 'fecha' => '14 sep 2026'],
        ];

        // $audit es el número que viene en la dirección (/auditorias/1/checklist). Si no existe: 404.
        abort_unless(isset($auditorias[$audit]), 404);
        $auditoria = $auditorias[$audit];
        // Solo "En captura" se llena. Una "Programada" se puede consultar, pero todavía no se captura.
        $editable = $auditoria['estado'] === 'En captura';

        // Primera pregunta de todas las auditorías, sin importar el procedimiento (lo pide la minuta).
        $preguntaFija = [
            'texto'       => 'El auditado conoce sus procedimientos y sabe dónde consultarlos.',
            'descripcion' => 'Pídele al auditado que explique con sus palabras cómo realiza la actividad y que te muestre dónde consulta la versión vigente de su procedimiento.',
            'evidencia'   => 'Explicación del auditado y ubicación del procedimiento vigente',
            'fija'        => true,
        ];

        // Criterios propios del procedimiento auditado.
        // "descripcion" explica qué significa el criterio, para que el auditor sepa qué está evaluando.
        $criterios = [
            [
                'texto'       => 'La actividad cuenta con registro trazable.',
                'descripcion' => 'Cada vez que se realiza la actividad queda un registro con fecha, responsable y resultado, de forma que se puede reconstruir qué se hizo y quién lo hizo.',
                'evidencia'   => 'Bitácora fechada',
                'seccion'     => '6.3 Registro de maniobras',
                'fragmento'   => 'Al terminar cada maniobra, el operador anota en la bitácora la fecha, la hora, su nombre y el resultado. El supervisor de turno firma la bitácora al cierre del turno.',
            ],
            [
                'texto'       => 'Las evidencias están completas y actualizadas.',
                'descripcion' => 'Están todos los documentos que respaldan la actividad, corresponden al periodo auditado y tienen las firmas o aprobaciones que pide el procedimiento.',
                'evidencia'   => 'Expediente y revisión firmada',
                'seccion'     => '8. Registros y evidencias',
                'fragmento'   => 'Las bitácoras y los formatos de inspección se guardan en el expediente del área durante 12 meses, con la firma del supervisor y la fecha de revisión.',
            ],
        ];

        // La pregunta fija siempre va primero: array_unshift la mete al inicio de la lista.
        array_unshift($criterios, $preguntaFija);

        // Convocados a la llamada: el equipo del auditor y el del auditado. Durante la llamada se marca quién asistió.
        // "obligatorio": el auditor y el auditado deben estar marcados para poder finalizar.
        $participantes = [
            ['nombre' => auth()->user()->name,  'puesto' => 'Auditor',           'equipo' => 'auditor',  'obligatorio' => true],
            ['nombre' => $auditoria['auditado'], 'puesto' => 'Responsable del proceso', 'equipo' => 'auditado', 'obligatorio' => true],
            ['nombre' => 'Mariana Garza',        'puesto' => 'Supervisor de turno',     'equipo' => 'auditado', 'obligatorio' => false],
        ];
    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index') }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        {{-- ml-auto: empuja el botón a la derecha, junto al estado --}}
        <button type="button" @click="$dispatch('solicitar-reprogramacion')"
                class="ml-auto rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">Solicitar reprogramación</button>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $auditoria['estado'] === 'En captura' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand' }}">{{ $auditoria['estado'] }}</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Checklist de auditoría',
        'subtitle' => $auditoria['procedimiento'] . ' · ' . $auditoria['auditado'],
    ])

    {{-- Todo lo de adentro comparte estos datos "vivos" de Alpine --}}
    <div x-data="{
            respuestas: @js(array_fill(0, count($criterios), ['resultado' => ''])),
            ponderaciones: @js(config('auditorias.ponderaciones')),
            asistencia: @js(array_fill(0, count($participantes), false)),
            obligatorios: @js(array_column($participantes, 'obligatorio')),
            guardado: false,
            confirmando: false,
            finalizada: false,
            noAplica(r) { return r.resultado === 'no_aplica' },
            capturado(r) { return r.resultado !== '' },
            valor(r) {
                if (r.resultado === '') return '—';
                if (this.noAplica(r)) return 'No cuenta';
                return this.ponderaciones[r.resultado] + ' / 100';
            },
            get capturados() { return this.respuestas.filter(r => this.capturado(r)).length },
            get asistieron() { return this.asistencia.filter(a => a).length },
            get faltanObligatorios() { return this.obligatorios.some((obligatorio, i) => obligatorio && ! this.asistencia[i]) },
            get promedio() {
                {{-- "No aplica" no cuenta: el promedio es solo de los criterios evaluados. --}}
                const evaluados = this.respuestas.filter(r => ! this.noAplica(r));
                if (evaluados.length === 0) return '—';
                return Math.round(evaluados.reduce((suma, r) => suma + this.ponderaciones[r.resultado], 0) / evaluados.length);
            },
         }">

        {{-- Sesión de Teams, lista de asistencia y avance --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div class="sm:flex-1">
                <h2 class="text-base font-semibold text-gray-900">Sesión por Microsoft Teams</h2>
                <p class="mt-2 text-sm text-gray-600">{{ $editable ? 'Registra las respuestas y las observaciones durante la llamada.' : 'La captura corresponde al auditor asignado cuando la auditoría esté activa.' }}</p>
            </div>

            <div class="flex flex-col items-start gap-3 sm:items-end">
                {{-- Lista de asistencia: el botón despliega a los convocados y se cierra con un clic afuera --}}
                <div x-data="{ abierta: false }" @click.outside="abierta = false" class="relative">
                    <button type="button" @click="abierta = ! abierta"
                            class="inline-flex items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">
                        <x-icon name="users" class="h-4 w-4" />
                        Lista de asistencia
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand"><span x-text="asistieron">0</span> de {{ count($participantes) }}</span>
                        <x-icon name="chevron" class="h-4 w-4 transition-transform" ::class="{ 'rotate-180': abierta }" />
                    </button>

                    <div x-show="abierta" x-cloak x-transition.opacity
                         class="absolute left-0 z-20 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-lg sm:left-auto sm:right-0 sm:w-80">
                        <p class="text-sm text-gray-600">{{ $editable ? 'Marca a las personas que asistieron a la llamada.' : 'Podrás marcar la asistencia cuando la auditoría esté En captura.' }}</p>

                        <fieldset :disabled="@js(! $editable) || confirmando || finalizada" class="mt-2 divide-y divide-gray-100">
                            @foreach($participantes as $i => $participante)
                                <label class="flex items-start gap-3 py-3">
                                    <input type="checkbox" name="asistentes[]" value="{{ $i }}" x-model="asistencia[{{ $i }}]"
                                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-gray-100">
                                    <span>
                                         <span class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-gray-900">
                                            {{ $participante['nombre'] }}
                                            @if($participante['obligatorio'])
                                                <span class="rounded-full bg-gray-100 px-2 py-0.5 text-xs font-semibold text-gray-600">Obligatorio</span>
                                            @endif
                                        </span>
                                        <span class="block text-xs text-gray-500">{{ $participante['puesto'] }} · Equipo {{ $participante['equipo'] }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </fieldset>
                    </div>
                </div>

                <div class="text-sm font-semibold text-brand"><span x-text="capturados">0</span> de {{ count($criterios) }} criterios capturados</div>
            </div>
        </div>

        {{-- Procedimiento que se audita. Se abre en otra pestaña para no perder lo capturado.
             Demo: busca el proceso por su nombre. Con la base de datos, irá directo a ese proceso (processes.show). --}}
        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Procedimiento auditado</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $auditoria['procedimiento'] }} · Cada criterio muestra el fragmento que se evalúa.</p>
            </div>
            <a href="{{ route('processes.index', ['q' => $auditoria['procedimiento']]) }}" target="_blank" rel="noopener"
               class="rounded-lg border-2 border-brand px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-50">Ver procedimiento completo y anexos</a>
        </div>

        {{-- Un bloque por criterio --}}
        <fieldset :disabled="@js(! $editable) || confirmando || finalizada" class="space-y-6">
            @foreach($criterios as $i => $criterio)
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    {{-- ?? false: los criterios del procedimiento no traen "fija", y sin esto PHP marcaría error --}}
                    @if($criterio['fija'] ?? false)
                        <span class="mb-2 inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand">Pregunta fija · va en todas las auditorías</span>
                    @endif
                    <h3 class="text-lg font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio['texto'] }}</h3>
                    <p class="mt-1 text-[15px] text-gray-700">{{ $criterio['descripcion'] }}</p>
                    {{-- Lo que dice el procedimiento sobre este criterio. La pregunta fija no trae fragmento. --}}
                    @isset($criterio['fragmento'])
                        <div class="mt-3 rounded-lg border-l-4 border-brand bg-gray-50 px-4 py-3">
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Qué dice el procedimiento · {{ $criterio['seccion'] }}</div>
                            <p class="mt-1 text-sm italic text-gray-700">“{{ $criterio['fragmento'] }}”</p>
                        </div>
                    @endisset
                    <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-900">Evidencia a verificar:</span> {{ $criterio['evidencia'] }}</p>

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="resultado-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Resultado del criterio</label>
                            <select id="resultado-{{ $i }}" name="respuestas[{{ $i }}][resultado]" x-model="respuestas[{{ $i }}].resultado"
                                    class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand">
                                <option value="">Selecciona</option>
                                <option value="cumple">Cumple</option>
                                <option value="no_cumple">No cumple</option>
                                <option value="parcial">Parcial</option>
                                <option value="no_aplica">No aplica</option>
                            </select>
                        </div>
                        {{-- PENDIENTE: falta confirmar con la cliente si el auditor debe ver el valor de cada criterio.
                             Para mostrarlo, quita este comentario. El valor sale solo del resultado elegido.
                        <div>
                            <span class="mb-2 block text-sm font-semibold text-gray-900">Valor del criterio</span>
                            <div class="flex h-12 items-center rounded-lg border border-gray-200 bg-gray-100 px-4 text-[15px] text-gray-700"
                                 x-text="valor(respuestas[{{ $i }}])">—</div>
                        </div>
                        --}}
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
                <span x-show="! guardado && capturados < {{ count($criterios) }}" class="mr-auto text-sm text-gray-600">Para finalizar, captura el resultado de cada criterio.</span>
                <span x-show="! guardado && capturados === {{ count($criterios) }} && faltanObligatorios" x-cloak class="mr-auto text-sm text-gray-600">Para finalizar, marca en la lista de asistencia al auditor y al auditado.</span>

                <button type="button" @click="guardado = true; setTimeout(() => guardado = false, 3000)"
                        class="rounded-lg border-2 border-brand px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-50">Guardar avance</button>

                <button type="button" :disabled="capturados < {{ count($criterios) }} || faltanObligatorios"
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
    @include('auditorias.partials.reschedule-modal')
</x-layouts.vigia>
