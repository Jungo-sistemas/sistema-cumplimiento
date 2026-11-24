{{-- Ventana "Programar auditoría" de la lista de Auditorías. Se abre con $dispatch('nueva-auditoria').
     Usa $regulations, $people y $auditors del controlador. --}}
<div x-data="{
        abierto: @js($errors->hasAny(['regulation_id', 'auditees', 'fecha', 'hora', 'auditor_id'])),
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

        <form method="POST" action="{{ route('auditorias.audits.store') }}" class="mt-5 space-y-5">
            @csrf

            <div>
                <label for="nueva-proceso" class="mb-2 block text-sm font-semibold text-gray-900">Proceso a auditar</label>
                <select id="nueva-proceso" name="regulation_id" required
                        class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <option value="">Selecciona</option>
                    @foreach($regulations->groupBy(fn ($r) => $r->company?->procesos_name ?? $r->company?->name ?? 'Sin empresa') as $company => $list)
                        <optgroup label="{{ $company }}">
                            @foreach($list as $regulation)
                                <option value="{{ $regulation->id }}" @selected((int) old('regulation_id') === $regulation->id)>{{ $regulation->code ? $regulation->code . ' · ' : '' }}{{ $regulation->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
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
                    <input id="nueva-fecha" type="date" name="fecha" value="{{ old('fecha') }}" min="{{ today()->toDateString() }}"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label for="nueva-hora" class="mb-2 block text-sm font-semibold text-gray-900">Hora</label>
                    <input id="nueva-hora" type="time" name="hora" value="{{ old('hora', '10:00') }}"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>
            <p class="text-sm text-gray-600">Sin fecha, el auditado todavía no se entera. Con fecha, recibe el aviso {{ \App\Models\Audit::noticeDays() }} días antes.</p>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Programar</button>
        </form>
    </div>
</div>
