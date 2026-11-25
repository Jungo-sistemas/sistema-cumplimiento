{{-- Ventana "Programar auditoría" de la lista de Auditorías. Se abre con $dispatch('nueva-auditoria').
     Usa $regulations, $people y $auditors del controlador. --}}
<div x-data="{
        abierto: @js($errors->hasAny(['regulation_id', 'auditees', 'fecha', 'hora', 'auditor_id'])),
        enviando: false,
        // Proceso a auditar: buscador por código o nombre (la lista era muy larga para un select).
        procesos: @js($regulations->map(fn ($r) => [
            'id'      => $r->id,
            'codigo'  => $r->code,
            'nombre'  => $r->name,
            'empresa' => $r->company?->procesos_name ?? $r->company?->name ?? 'Sin empresa',
        ])->sortBy(fn ($p) => mb_strtoupper($p['empresa']) . ' ' . ($p['codigo'] ?? '') . ' ' . $p['nombre'])->values()),
        proceso: @js(old('regulation_id') ? (int) old('regulation_id') : null),
        buscarProceso: '',
        listaProcesos: false,
        get procesosFiltrados() {
            const q = this.buscarProceso.trim().toLowerCase();
            const lista = q
                ? this.procesos.filter(p => ((p.codigo ?? '') + ' ' + p.nombre + ' ' + p.empresa).toLowerCase().includes(q))
                : this.procesos;
            return lista.slice(0, 80);
        },
        etiqueta(p) { return p ? (p.codigo ? p.codigo + ' · ' : '') + p.nombre : ''; },
        get procesoElegido() { return this.procesos.find(p => p.id === this.proceso); },
        elegirProceso(p) { this.proceso = p.id; this.buscarProceso = ''; this.listaProcesos = false; },
        buscar: '',
        elegidos: @js(array_map('intval', old('auditees', []))),
        personas: @js($people->map(fn ($p) => ['id' => $p->id, 'nombre' => $p->name, 'correo' => $p->email])->values()),
        get filtradas() {
            const q = this.buscar.trim().toLowerCase();
            return q ? this.personas.filter(p => (p.nombre + ' ' + p.correo).toLowerCase().includes(q)) : this.personas;
        },
        nombre(id) { return (this.personas.find(p => p.id === id) ?? {}).nombre },
        alternar(id) {
            this.elegidos = this.elegidos.includes(id) ? this.elegidos.filter(e => e !== id) : [...this.elegidos, id];
        },
     }"
     @nueva-auditoria.window="abierto = true"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink">Programar auditoría</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        {{-- Un solo envío: el botón se bloquea al primer clic (si no, varios clics creaban varias auditorías). --}}
        <form method="POST" action="{{ route('auditorias.audits.store') }}" class="mt-5 space-y-5"
              @submit="if (enviando || ! proceso) { $event.preventDefault(); if (! proceso) listaProcesos = true } else { enviando = true }">
            @csrf

            <div class="relative" @click.outside="listaProcesos = false">
                <label for="nueva-proceso" class="mb-2 block text-sm font-semibold text-gray-900">Proceso a auditar</label>
                <input type="hidden" name="regulation_id" :value="proceso ?? ''">

                {{-- Elegido: se muestra con opción de cambiarlo. --}}
                <div x-show="procesoElegido" class="flex items-center justify-between gap-3 rounded-lg border border-brand/40 bg-brand-50 px-3 py-2.5">
                    <div class="min-w-0">
                        <div class="truncate text-[15px] font-semibold text-gray-900" x-text="etiqueta(procesoElegido)"></div>
                        <div class="text-xs text-gray-500" x-text="procesoElegido?.empresa"></div>
                    </div>
                    <button type="button" @click="proceso = null; $nextTick(() => $refs.buscarProceso.focus())"
                            class="shrink-0 text-sm font-semibold text-brand hover:underline">Cambiar</button>
                </div>

                <div x-show="! procesoElegido">
                    <input id="nueva-proceso" type="search" x-ref="buscarProceso" x-model="buscarProceso"
                           @focus="listaProcesos = true" @input="listaProcesos = true" autocomplete="off"
                           placeholder="Buscar por código o nombre (p. ej. P-ALC-002 o carrotanques)..."
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <div x-show="listaProcesos" x-cloak
                         class="absolute z-10 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white py-1 shadow-lg">
                        <template x-for="(p, i) in procesosFiltrados" :key="p.id">
                            <div>
                                <div x-show="i === 0 || procesosFiltrados[i - 1].empresa !== p.empresa"
                                     class="px-3 pb-1 pt-2 text-xs font-bold uppercase tracking-wider text-gray-400" x-text="p.empresa"></div>
                                <button type="button" @click="elegirProceso(p)"
                                        class="block w-full px-3 py-2 text-left text-sm text-gray-800 hover:bg-gray-50" x-text="etiqueta(p)"></button>
                            </div>
                        </template>
                        <p x-show="procesosFiltrados.length === 0" class="px-3 py-2 text-sm text-gray-500">Ningún proceso coincide.</p>
                    </div>
                </div>
            </div>

            @if($auditors->isNotEmpty())
                <div>
                    <label for="nueva-auditor" class="mb-2 block text-sm font-semibold text-gray-900">Auditor</label>
                    <select id="nueva-auditor" name="auditor_id"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        @foreach($auditors as $auditor)
                            <option value="{{ $auditor->id }}" @selected((int) old('auditor_id', auth()->id()) === $auditor->id)>{{ $auditor->name }}{{ $auditor->id === auth()->id() ? ' (yo)' : '' }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <fieldset>
                <legend class="mb-2 block text-sm font-semibold text-gray-900">Auditados</legend>
                <div class="mb-2 flex flex-wrap gap-2" x-show="elegidos.length">
                    <template x-for="id in elegidos" :key="id">
                        <span class="inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-sm font-semibold text-brand">
                            <span x-text="nombre(id)"></span>
                            <button type="button" @click="alternar(id)" class="text-brand hover:text-brand-dark">×</button>
                            <input type="hidden" name="auditees[]" :value="id">
                        </span>
                    </template>
                </div>
                <input type="search" x-model="buscar" placeholder="Buscar persona por nombre o correo..."
                       class="h-11 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                <div class="mt-2 max-h-48 space-y-1 overflow-y-auto rounded-lg border border-gray-200 p-2">
                    <template x-for="p in filtradas" :key="p.id">
                        <label class="flex cursor-pointer items-center gap-3 rounded-md px-2 py-1.5 hover:bg-gray-50">
                            <input type="checkbox" :checked="elegidos.includes(p.id)" @change="alternar(p.id)"
                                   class="h-4 w-4 rounded border-gray-300 text-brand focus:ring-brand">
                            <span class="text-sm"><span class="font-semibold text-gray-900" x-text="p.nombre"></span> <span class="text-gray-500" x-text="p.correo"></span></span>
                        </label>
                    </template>
                </div>
            </fieldset>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="nueva-fecha" class="mb-2 block text-sm font-semibold text-gray-900">Fecha (opcional)</label>
                    <input id="nueva-fecha" type="date" name="fecha" value="{{ old('fecha') }}" min="{{ \App\Models\Audit::localNow()->toDateString() }}"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label for="nueva-hora" class="mb-2 block text-sm font-semibold text-gray-900">Hora</label>
                    <input id="nueva-hora" type="time" name="hora" value="{{ old('hora', '10:00') }}"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>
            <p class="text-sm text-gray-600">Sin fecha, el auditado todavía no se entera. Con fecha, recibe el aviso {{ \App\Models\Audit::noticeDays() }} días antes.</p>

            <button type="submit" :disabled="enviando"
                    class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:cursor-wait disabled:opacity-60">
                <span x-show="! enviando">Programar</span>
                <span x-show="enviando" x-cloak>Programando…</span>
            </button>
        </form>
    </div>
</div>
