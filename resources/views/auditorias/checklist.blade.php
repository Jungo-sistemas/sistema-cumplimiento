@use('App\Models\Audit')
@use('App\Models\AuditFinding')
<x-layouts.vigia title="Checklist · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index') }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Checklist de auditoría</span>
    </x-slot>

    @php
        $stage = $audit->auditorStage();
        $procedimiento = trim(($audit->regulation?->code ? $audit->regulation->code . ' · ' : '') . ($audit->regulation?->name ?? ''));
        $auditados = $audit->auditees->pluck('name')->join(', ');
        $auditees = collect($people)->where('equipo', 'auditado')->values();

        // Lo que necesita Alpine de cada criterio.
        $respuestas = $items->map(fn ($item) => [
            'id' => $item->id, 'resultado' => $item->result ?? '', 'observaciones' => $item->observations ?? '',
        ])->values();
        $hallazgos = $items->map(fn ($item) => $item->draft_findings ?? [])->values();
        $soportes = $items->map(fn ($item) => $item->files->map(fn ($f) => [
            'id' => $f->id, 'nombre' => $f->original_name, 'url' => route('auditorias.support.download', $f),
        ])->values())->values();
        $asistencia = collect($people)->mapWithKeys(fn ($p) => [$p['id'] => in_array($p['id'], $audit->attendeeIds(), true)]);
        $obligatorios = array_filter([$audit->auditor_id]);
        $actividades = $items->map(fn ($item) => $item->activity)->values();
    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index') }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        @if($canRun && ! $audit->started_at)
            <button type="button"
                    @click="$dispatch('programar-fecha', @js(['url' => route('auditorias.audits.schedule', $audit), 'proceso' => $procedimiento, 'auditados' => $auditados, 'actual' => $audit->scheduledLabel(), 'fecha' => $audit->scheduled_at?->toDateString(), 'hora' => $audit->scheduled_at?->format('H:i')]))"
                    class="ml-auto rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">{{ $audit->scheduled_at ? 'Cambiar fecha' : 'Poner fecha' }}</button>
        @endif
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ match ($stage) { Audit::STAGE_CAPTURE => 'bg-amber-50 text-amber-700', Audit::STAGE_READY => 'bg-blue-50 text-blue-700', default => 'bg-brand-50 text-brand' } }}">{{ $stage }}</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Checklist de auditoría',
        'subtitle' => $procedimiento . ' · ' . $auditados . ($audit->scheduled_at ? ' · ' . $audit->scheduledLabel() : '')
            . ($audit->started_at ? ' · Iniciada: ' . $audit->startedLabel() : ''),
    ])

    @if(! $editable)
        <div class="mb-6 rounded-2xl border border-brand-100 bg-brand-50 p-5 text-[15px] text-brand-ink">
            @if(! $canRun)
                Solo el auditor asignado ({{ $audit->auditor?->name ?? 'sin asignar' }}) captura este checklist. Aquí puedes consultarlo.
            @elseif(! $audit->scheduled_at)
                Esta auditoría todavía no tiene fecha. Ponle fecha cuando la acuerdes con el auditado; se inicia ese día.
            @elseif($audit->canStart())
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <span>Hoy es la auditoría. Cuando empiece la reunión, iníciala para capturar el checklist; queda registrada la hora de inicio.</span>
                    @include('auditorias.partials.start-button', ['audit' => $audit, 'size' => 'lg'])
                </div>
            @else
                La auditoría se inicia el día programado ({{ $audit->scheduledLabel() }}). Mientras tanto puedes revisar los criterios que se van a evaluar.
            @endif
        </div>
    @endif

    <div x-data="{
            respuestas: @js($respuestas),
            ponderaciones: @js(config('auditorias.ponderaciones')),
            asistencia: @js($asistencia),
            obligatorios: @js($obligatorios),
            auditados: @js($auditees->pluck('id')),
            personas: @js(collect($people)->pluck('nombre', 'id')),
            hallazgos: @js($hallazgos),
            clasificaciones: @js(AuditFinding::SEVERITIES),
            soportes: @js($soportes),
            conclusiones: @js($audit->conclusions ?? ''),
            urls: {
                guardar: @js(route('auditorias.audits.checklist.save', $audit)),
                finalizar: @js(route('auditorias.audits.finalize', $audit)),
                criterios: @js(route('auditorias.audits.items.store', $audit)),
            },
            csrf: @js(csrf_token()),
            guardando: false,
            guardado: '',
            errores: [],
            confirmando: false,
            subiendo: 0,
            nuevoCriterio: { texto: '', evidencia: '', error: '' },
            noAplica(r) { return r.resultado === 'no_aplica' },
            capturado(r) { return r.resultado !== '' },
            necesitaHallazgo(resultado) { return resultado === 'no_cumple' || resultado === 'parcial' },
            faltaHallazgo(i) { return this.necesitaHallazgo(this.respuestas[i].resultado) && this.hallazgos[i].length === 0 },
            alCambiarResultado(i, resultado) {
                if (this.necesitaHallazgo(resultado) && this.hallazgos[i].length === 0) {
                    setTimeout(() => this.$dispatch('registrar-hallazgo', { criterio: i }));
                }
            },
            guardarHallazgo(detalle) {
                if (detalle.indice === null) this.hallazgos[detalle.criterio].push(detalle.hallazgo);
                else this.hallazgos[detalle.criterio][detalle.indice] = detalle.hallazgo;
            },
            editar(i, n) { this.$dispatch('registrar-hallazgo', { criterio: i, indice: n, hallazgo: { ...this.hallazgos[i][n] } }) },
            eliminar(i, n) {
                if (confirm('¿Eliminar el hallazgo «' + this.hallazgos[i][n].titulo + '»?')) this.hallazgos[i].splice(n, 1);
            },
            async pedir(url, opciones = {}) {
                const respuesta = await fetch(url, {
                    ...opciones,
                    headers: { 'X-CSRF-TOKEN': this.csrf, 'Accept': 'application/json', ...(opciones.headers ?? {}) },
                });
                const datos = await respuesta.json().catch(() => ({}));
                if (! respuesta.ok) {
                    const lista = datos.errors ? Object.values(datos.errors).flat() : [datos.message || 'No se pudo guardar. Intenta de nuevo.'];
                    throw lista;
                }
                return datos;
            },
            datos() {
                return {
                    items: this.respuestas.map((r, i) => ({ id: r.id, result: r.resultado || null, observations: r.observaciones, findings: this.hallazgos[i] })),
                    attendance: Object.keys(this.asistencia).filter(id => this.asistencia[id]).map(Number),
                    conclusions: this.conclusiones,
                };
            },
            async guardar() {
                this.guardando = true; this.errores = [];
                try {
                    const r = await this.pedir(this.urls.guardar, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.datos()) });
                    this.guardado = 'Avance guardado a las ' + r.saved_at + '.';
                    setTimeout(() => this.guardado = '', 4000);
                    return true;
                } catch (lista) { this.errores = lista; return false; }
                finally { this.guardando = false; }
            },
            async finalizar() {
                this.guardando = true; this.errores = [];
                try {
                    const r = await this.pedir(this.urls.finalizar, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(this.datos()) });
                    window.location = r.redirect;
                } catch (lista) { this.errores = lista; this.confirmando = false; this.guardando = false; }
            },
            async agregarCriterio() {
                this.nuevoCriterio.error = '';
                if (! this.nuevoCriterio.texto.trim()) { this.nuevoCriterio.error = 'Escribe el criterio.'; return; }
                if (! await this.guardar()) return;
                try {
                    await this.pedir(this.urls.criterios, { method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ text: this.nuevoCriterio.texto, evidence: this.nuevoCriterio.evidencia }) });
                    window.location.reload();
                } catch (lista) { this.nuevoCriterio.error = lista.join(' '); }
            },
            async quitarCriterio(url, texto) {
                if (! confirm('¿Quitar el criterio «' + texto + '»? Se pierde lo capturado en él.')) return;
                if (! await this.guardar()) return;
                try { await this.pedir(url, { method: 'DELETE' }); window.location.reload(); }
                catch (lista) { this.errores = lista; }
            },
            // Cada archivo o foto se sube en cuanto se elige.
            async agregarSoportes(i, url, campo, esFoto) {
                for (const archivo of campo.files) {
                    const datos = new FormData();
                    datos.append('file', archivo);
                    if (esFoto) datos.append('name', 'Foto de las ' + new Date().toLocaleTimeString('es-MX').replaceAll(':', '.') + '.' + (archivo.name.split('.').pop() || 'jpg'));
                    this.subiendo++;
                    try {
                        const r = await this.pedir(url, { method: 'POST', body: datos });
                        this.soportes[i].push({ id: r.id, nombre: r.name, url: r.url });
                    } catch (lista) { this.errores = lista; }
                    finally { this.subiendo--; }
                }
                campo.value = '';
            },
            async quitarSoporte(i, n) {
                const soporte = this.soportes[i][n];
                try {
                    await this.pedir(@js(url('auditorias/soportes')) + '/' + soporte.id, { method: 'DELETE' });
                    this.soportes[i].splice(n, 1);
                } catch (lista) { this.errores = lista; }
            },
            get faltanHallazgos() { return this.respuestas.some((r, i) => this.faltaHallazgo(i)) },
            get totalHallazgos() { return this.hallazgos.flat().length },
            get capturados() { return this.respuestas.filter(r => this.capturado(r)).length },
            get asistieron() { return Object.values(this.asistencia).filter(a => a).length },
            get faltanObligatorios() {
                return this.obligatorios.some(id => ! this.asistencia[id]) || ! this.auditados.some(id => this.asistencia[id]);
            },
            get promedio() {
                const evaluados = this.respuestas.filter(r => this.capturado(r) && ! this.noAplica(r));
                if (evaluados.length === 0) return '—';
                return Math.round(evaluados.reduce((suma, r) => suma + this.ponderaciones[r.resultado], 0) / evaluados.length);
            },
        }"
        @hallazgo-guardado.window="guardarHallazgo($event.detail)">

        {{-- Sesión de Teams, lista de asistencia y avance --}}
        <div class="mb-6 flex flex-wrap items-start justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div class="sm:flex-1">
                <h2 class="text-base font-semibold text-gray-900">Sesión por Microsoft Teams</h2>
                <p class="mt-2 text-sm text-gray-600">{{ $editable ? 'Registra las respuestas y las observaciones durante la llamada. Guarda el avance de vez en cuando.' : 'La captura corresponde al auditor asignado, una vez que inicia la auditoría.' }}</p>
            </div>

            <div class="flex flex-col items-start gap-3 sm:items-end">
                <div x-data="{ abierta: false }" @click.outside="abierta = false" class="relative">
                    <button type="button" @click="abierta = ! abierta"
                            class="inline-flex items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink hover:bg-gray-50">
                        <x-icon name="users" class="h-4 w-4" />
                        Lista de asistencia
                        <span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs text-brand"><span x-text="asistieron">0</span> de {{ count($people) }}</span>
                        <x-icon name="chevron" class="h-4 w-4 transition-transform" ::class="{ 'rotate-180': abierta }" />
                    </button>

                    <div x-show="abierta" x-cloak x-transition.opacity
                         class="absolute left-0 z-20 mt-2 w-72 rounded-xl border border-gray-200 bg-white p-4 shadow-lg sm:left-auto sm:right-0 sm:w-80">
                        <p class="text-sm text-gray-600">{{ $editable ? 'Marca a las personas que asistieron. El auditor y al menos un auditado son obligatorios.' : 'Se marca durante la captura.' }}</p>

                        <fieldset :disabled="@js(! $editable) || confirmando" class="mt-2 divide-y divide-gray-100">
                            @foreach($people as $participante)
                                <label class="flex items-start gap-3 py-3">
                                    <input type="checkbox" x-model="asistencia[{{ $participante['id'] }}]"
                                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand disabled:cursor-not-allowed disabled:bg-gray-100">
                                    <span>
                                        <span class="flex flex-wrap items-center gap-x-2 text-sm font-semibold text-gray-900">
                                            {{ $participante['nombre'] }}
                                            @if($participante['equipo'] === 'auditor')
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

                <div class="text-sm font-semibold text-brand"><span x-text="capturados">0</span> de {{ $items->count() }} criterios capturados</div>
            </div>
        </div>

        <div class="mb-6 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white p-6">
            <div>
                <h2 class="text-base font-semibold text-gray-900">Procedimiento auditado</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $procedimiento }}{{ $items->contains('source', 'proceso') ? ' · Los criterios de cada paso abren la actividad del procedimiento con la que se evalúa.' : ' · Este proceso no trae pasos estructurados: agrega los criterios que vas a evaluar.' }}</p>
            </div>
            {{-- Abre directo el documento vigente, igual que el botón "Ver" de Procesos. --}}
            @if($audit->regulation?->currentVersion)
                <a href="{{ route('regulation-versions.preview', $audit->regulation->currentVersion) }}" target="_blank" rel="noopener"
                   class="rounded-lg border-2 border-brand px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-50">Ver procedimiento completo</a>
            @endif
        </div>

        <fieldset :disabled="@js(! $editable) || confirmando" class="space-y-6">
            @foreach($items as $i => $criterio)
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            @if($criterio->isFixed())
                                <span class="mb-2 inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand">Pregunta fija · va en todas las auditorías</span>
                            @elseif($criterio->source === 'auditor')
                                <span class="mb-2 inline-flex rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700">Criterio agregado por el auditor</span>
                            @endif
                            <h3 class="text-lg font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio->text }}</h3>
                        </div>
                        @if($editable && $criterio->source === 'auditor')
                            <button type="button" @click="quitarCriterio(@js(route('auditorias.audits.items.destroy', [$audit, $criterio])), @js($criterio->text))"
                                    class="rounded-lg border-2 border-red-200 px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">Quitar criterio</button>
                        @endif
                    </div>
                    @if($criterio->description)
                        <p class="mt-1 text-[15px] text-gray-700">{{ $criterio->description }}</p>
                    @endif
                    @if($criterio->activity)
                        {{-- relative: la lista de "Ver anexos" se acomoda respecto a este recuadro (a lo ancho en celular). --}}
                        <div class="relative mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg border-l-4 border-brand bg-gray-50 px-4 py-3">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Se evalúa con la actividad</div>
                                <p class="mt-1 text-sm font-semibold text-gray-900">Paso {{ $criterio->activity['numero'] }}. {{ $criterio->activity['titulo'] }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                @if($anexos[$i] ?? [])
                                    {{-- Los anexos que menciona este paso; cada uno abre su documento, como "Ver". Son
                                         enlaces (no botones) para que sirvan aunque el fieldset esté apagado. --}}
                                    <div x-data="{ abierto: false }" @click.outside="abierto = false" @keydown.escape.window="abierto = false">
                                        <a href="#" role="button" @click.prevent="abierto = ! abierto"
                                           class="inline-flex items-center gap-1.5 rounded-lg border-2 px-3 py-1.5 text-sm font-semibold"
                                           :class="abierto ? 'border-brand bg-brand-50 text-brand' : 'border-gray-300 text-gray-700 hover:border-brand hover:text-brand'">
                                            <x-icon name="paperclip" class="h-4 w-4" />
                                            <span>Ver anexos</span>
                                        </a>
                                        <div x-show="abierto" x-cloak x-transition
                                             class="absolute inset-x-0 top-full z-20 mt-2 rounded-lg border border-gray-200 bg-white p-3 shadow-xl sm:left-auto sm:w-80">
                                            <div class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-500">Anexos de este paso</div>
                                            <ul class="space-y-1">
                                                @foreach($anexos[$i] as $anexo)
                                                    <li>
                                                        <a href="{{ $anexo['url'] }}" target="_blank" rel="noopener" class="flex gap-2 rounded px-2 py-1.5 text-sm hover:bg-brand-50">
                                                            <span class="shrink-0 font-mono font-semibold text-brand">{{ $anexo['code'] }}</span>
                                                            <span class="text-gray-700">{{ $anexo['name'] }}</span>
                                                        </a>
                                                    </li>
                                                @endforeach
                                            </ul>
                                        </div>
                                    </div>
                                @endif
                                <a href="#" role="button" @click.prevent="$dispatch('ver-criterios', { indice: {{ $i }} })"
                                   class="rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">Criterios a evaluar</a>
                            </div>
                        </div>
                    @endif
                    @if($criterio->evidence)
                        <p class="mt-2 text-sm text-gray-600"><span class="font-semibold text-gray-900">Evidencia a verificar:</span> {{ $criterio->evidence }}</p>
                    @endif

                    <div class="mt-5 grid grid-cols-1 gap-5 md:grid-cols-2">
                        <div>
                            <label for="resultado-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Resultado del criterio</label>
                            <select id="resultado-{{ $i }}" x-model="respuestas[{{ $i }}].resultado"
                                    class="h-12 w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand" @change="alCambiarResultado({{ $i }}, $event.target.value)">
                                <option value="">Selecciona</option>
                                @foreach(\App\Models\AuditChecklistItem::RESULTS as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="mt-5">
                        <label for="observaciones-{{ $i }}" class="mb-2 block text-sm font-semibold text-gray-900">Observaciones del auditor</label>
                        <textarea id="observaciones-{{ $i }}" x-model="respuestas[{{ $i }}].observaciones" rows="3"
                                placeholder="Describe lo que verificaste y, si hay un incumplimiento, explica qué se hizo incorrectamente."
                                class="w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand"></textarea>
                    </div>

                    {{-- Los botones siempre se ven; el fieldset de arriba los bloquea a quien solo consulta. --}}
                    <div class="mt-5">
                        <span class="mb-2 block text-sm font-semibold text-gray-900">Soporte de la revisión (opcional)</span>
                        <div class="flex flex-wrap gap-3">
                            <button type="button" @click="$refs.archivos{{ $i }}.click()"
                                    class="inline-flex items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-icon name="paperclip" class="h-4 w-4" />
                                <span>Elegir archivos</span>
                            </button>
                            <button type="button" @click="$refs.foto{{ $i }}.click()"
                                    class="inline-flex items-center gap-2 rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">
                                <x-icon name="camera" class="h-4 w-4" />
                                <span>Tomar foto</span>
                            </button>
                        </div>
                        @if($editable)
                            <input type="file" multiple class="hidden" x-ref="archivos{{ $i }}"
                                   @change="agregarSoportes({{ $i }}, @js(route('auditorias.audits.items.support', [$audit, $criterio])), $event.target, false)">
                            <input type="file" accept="image/*" capture="environment" class="hidden" x-ref="foto{{ $i }}"
                                   @change="agregarSoportes({{ $i }}, @js(route('auditorias.audits.items.support', [$audit, $criterio])), $event.target, true)">
                        @endif
                    </div>

                    <ul x-show="soportes[{{ $i }}].length" class="mt-3 space-y-2">
                        <template x-for="(soporte, n) in soportes[{{ $i }}]" :key="soporte.id">
                            <li class="flex items-center justify-between gap-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                <a :href="soporte.url" class="font-semibold text-brand hover:underline" x-text="soporte.nombre"></a>
                                @if($editable)
                                    <button type="button" @click="quitarSoporte({{ $i }}, n)" class="font-semibold text-red-700 hover:underline">Quitar</button>
                                @endif
                            </li>
                        </template>
                    </ul>

                    {{-- Quien solo consulta ve los hallazgos registrados, sin Editar ni Eliminar. --}}
                    <div class="mt-5 border-t border-gray-200 pt-5">
                        <div class="flex flex-wrap items-center justify-between gap-4">
                            <p class="text-sm" :class="faltaHallazgo({{ $i }}) ? 'font-semibold text-red-700' : 'text-gray-600'"
                               x-text="faltaHallazgo({{ $i }}) ? 'No cumple y Parcial necesitan un hallazgo: regístralo aquí.' : 'Registra los hallazgos que identifiques en este criterio.'">Registra los hallazgos que identifiques en este criterio.</p>
                            <button type="button" @click="$dispatch('registrar-hallazgo', { criterio: {{ $i }} })"
                                    class="rounded-lg border-2 border-gray-300 px-4 py-2 text-sm font-semibold text-brand-ink enabled:hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-50">Registrar hallazgo</button>
                        </div>

                        <ul x-show="hallazgos[{{ $i }}].length" class="mt-4 space-y-3">
                            <template x-for="(hallazgo, n) in hallazgos[{{ $i }}]" :key="n">
                                <li class="flex flex-wrap items-start justify-between gap-3 rounded-xl border border-gray-200 bg-gray-50 p-4">
                                    <div>
                                        <p class="font-semibold text-gray-900" x-text="hallazgo.titulo"></p>
                                        <p class="mt-1 text-sm text-gray-600" x-text="clasificaciones[hallazgo.severidad] + ' · Responsable: ' + personas[hallazgo.responsable_id] + ' · Cierre en ' + (hallazgo.severidad === 'critico' ? {{ AuditFinding::totalDays('critico') }} : {{ AuditFinding::totalDays('no_critico') }}) + ' días hábiles'"></p>
                                    </div>
                                    @if($editable)
                                        <div class="flex gap-2">
                                            <button type="button" @click="editar({{ $i }}, n)"
                                                    class="rounded-lg border-2 border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-gray-50">Editar</button>
                                            <button type="button" @click="eliminar({{ $i }}, n)"
                                                    class="rounded-lg border-2 border-red-200 bg-white px-3 py-1.5 text-sm font-semibold text-red-700 hover:bg-red-50">Eliminar</button>
                                        </div>
                                    @endif
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
            @endforeach

            {{-- Criterios propios del auditor --}}
            @if($editable)
                <div class="rounded-2xl border-2 border-dashed border-gray-300 bg-white p-6">
                    <h3 class="text-base font-semibold text-gray-900">Agregar criterio</h3>
                    <p class="mt-1 text-sm text-gray-600">Agrega lo que vas a evaluar y no está en la lista. Se guarda el avance antes de agregarlo.</p>
                    <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                        <input type="text" x-model="nuevoCriterio.texto" placeholder="Ej. La bitácora de descarga está firmada por el supervisor."
                               class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        <input type="text" x-model="nuevoCriterio.evidencia" placeholder="Evidencia a verificar (opcional)"
                               class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    </div>
                    <p x-show="nuevoCriterio.error" x-text="nuevoCriterio.error" class="mt-1 text-sm text-red-600"></p>
                    <button type="button" @click="agregarCriterio()" :disabled="guardando"
                            class="mt-4 rounded-lg border-2 border-brand px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-50 disabled:opacity-50">Agregar criterio</button>
                </div>
            @endif

            <div class="rounded-2xl border border-gray-200 bg-white p-6">
                <label for="conclusiones" class="mb-2 block text-sm font-semibold text-gray-900">Conclusiones de la auditoría</label>
                <textarea id="conclusiones" x-model="conclusiones" rows="4"
                          placeholder="Resume lo más importante: qué se cumple, qué hay que corregir y qué se acordó con el auditado."
                          class="w-full rounded-lg border-gray-200 bg-gray-50 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            </div>
        </fieldset>

        <div x-show="errores.length" x-cloak class="mt-6 rounded-2xl border border-red-200 bg-red-50 p-5 text-sm text-red-700">
            <template x-for="error in errores"><p x-text="error"></p></template>
        </div>

        @if($editable)
            <div class="mt-6 flex flex-wrap items-center justify-end gap-3 rounded-2xl border border-gray-200 bg-white p-6" x-show="! confirmando">
                <span x-show="guardado" x-cloak x-transition class="mr-auto text-sm font-semibold text-green-700" x-text="guardado"></span>
                <span x-show="subiendo" x-cloak class="mr-auto text-sm text-gray-600">Subiendo archivos...</span>
                <span x-show="! guardado && ! subiendo && capturados < {{ $items->count() }}" class="mr-auto text-sm text-gray-600">Para finalizar, captura el resultado de cada criterio.</span>
                <span x-show="! guardado && ! subiendo && capturados === {{ $items->count() }} && faltanObligatorios" x-cloak class="mr-auto text-sm text-gray-600">Para finalizar, marca en la lista de asistencia al auditor y al menos a un auditado.</span>
                <span x-show="! guardado && ! subiendo && capturados === {{ $items->count() }} && ! faltanObligatorios && faltanHallazgos" x-cloak class="mr-auto text-sm text-gray-600">Para finalizar, registra un hallazgo en cada criterio con No cumple o Parcial.</span>

                <button type="button" @click="guardar()" :disabled="guardando"
                        class="rounded-lg border-2 border-brand px-5 py-2.5 text-sm font-semibold text-brand hover:bg-brand-50 disabled:opacity-50">Guardar avance</button>

                <button type="button" :disabled="guardando || subiendo || capturados < {{ $items->count() }} || faltanObligatorios || faltanHallazgos"
                        class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50" @click="confirmando = true">Finalizar</button>
            </div>

            <div x-show="confirmando" x-cloak x-transition class="mt-6 rounded-2xl border-2 border-brand-100 bg-brand-50 p-6">
                <p class="font-semibold text-brand-ink">¿Finalizar la auditoría?</p>
                <p class="mt-1 text-sm text-gray-600">Ya no podrás cambiar las respuestas. La calificación final será de <span class="font-semibold text-gray-900" x-text="promedio"></span> / 100 y se les avisará a los auditados.</p>
                <p x-show="totalHallazgos > 0" class="mt-1 text-sm text-gray-600"
                   x-text="totalHallazgos === 1 ? 'Se enviará 1 hallazgo a su responsable, que deberá mandar la causa raíz y las acciones.' : 'Se enviarán ' + totalHallazgos + ' hallazgos a sus responsables, que deberán mandar la causa raíz y las acciones.'"></p>
                <div class="mt-4 flex justify-end gap-3">
                    <button type="button" @click="confirmando = false" class="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50">Cancelar</button>
                    <button type="button" @click="finalizar()" :disabled="guardando" class="rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark disabled:opacity-50">Sí, finalizar</button>
                </div>
            </div>
        @endif
    </div>

    @if($editable)
        @include('auditorias.partials.register-finding-modal')
    @endif
    @if($canRun)
        @include('auditorias.partials.reschedule-modal')
    @endif
    @include('auditorias.partials.criteria-modal')
</x-layouts.vigia>
