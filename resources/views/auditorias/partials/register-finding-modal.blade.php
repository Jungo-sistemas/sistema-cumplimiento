{{-- Ventana "Registrar hallazgo". Solo se incluye en el checklist de una auditoría En captura.
    Cada criterio la abre con: @click="$dispatch('registrar-hallazgo', { criterio: número })"
    También se abre sola al elegir No cumple o Parcial en un criterio sin hallazgo, y con los datos de
    un hallazgo al dar "Editar". Al guardar, le manda el hallazgo al checklist (hallazgo-guardado),
    que lo guarda como borrador; se envía a su responsable al finalizar la auditoría.
    Usa $procedimiento, $items y $auditees de esa página. --}}
<div x-data="{
        abierto: false,
        criterios: @js($items->pluck('text')->values()),
        criterio: null,
        indice: null,
        titulo: '',
        descripcion: '',
        severidad: '',
        responsable: null,
        errores: {},
        abrir(detalle) {
            const h = detalle.hallazgo ?? {};
            this.abierto = true;
            this.criterio = detalle.criterio;
            this.indice = detalle.indice ?? null;
            this.titulo = h.titulo ?? '';
            this.descripcion = h.descripcion ?? '';
            this.severidad = h.severidad ?? '';
            this.responsable = h.responsable_id ?? @js($auditees->count() === 1 ? $auditees->first()['id'] : null);
            this.errores = {};
        },
        guardar() {
            this.errores = {};
            if (! this.titulo.trim()) this.errores.titulo = 'Escribe el título del hallazgo.';
            if (! this.descripcion.trim()) this.errores.descripcion = 'Describe lo que encontraste.';
            if (! this.severidad) this.errores.severidad = 'Elige la clasificación.';
            if (! this.responsable) this.errores.responsable = 'Elige al responsable.';

            if (Object.keys(this.errores).length === 0) {
                this.$dispatch('hallazgo-guardado', {
                    criterio: this.criterio,
                    indice: this.indice,
                    hallazgo: {
                        titulo: this.titulo.trim(),
                        descripcion: this.descripcion.trim(),
                        severidad: this.severidad,
                        responsable_id: Number(this.responsable),
                    },
                });
                this.abierto = false;
            }
        },
     }"
     @registrar-hallazgo.window="abrir($event.detail)"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink" x-text="indice === null ? 'Registrar hallazgo' : 'Editar hallazgo'">Registrar hallazgo</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        <form @submit.prevent="guardar()" class="mt-5 space-y-5 rounded-2xl border border-gray-200 bg-gray-50 p-5">
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Auditoría</div>
                    <p class="mt-1 text-[15px] text-gray-700">{{ $procedimiento }}</p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Criterio del checklist</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="criterio === null ? '' : (criterio + 1) + '. ' + criterios[criterio]"></p>
                </div>
            </div>

            <div>
                <label for="hallazgo-titulo" class="mb-2 block text-sm font-semibold text-gray-900">Título del hallazgo</label>
                <input id="hallazgo-titulo" type="text" x-model="titulo" maxlength="255" placeholder="Describe la desviación observada"
                       class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                <p x-show="errores.titulo" x-text="errores.titulo" class="mt-1 text-sm text-red-600"></p>
            </div>

            <div>
                <label for="hallazgo-descripcion" class="mb-2 block text-sm font-semibold text-gray-900">Descripción y evidencia observada</label>
                <textarea id="hallazgo-descripcion" x-model="descripcion" rows="3"
                          placeholder="Explica qué encontraste, qué requisito no se cumple y en qué evidencia se sustenta."
                          class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
                <p x-show="errores.descripcion" x-text="errores.descripcion" class="mt-1 text-sm text-red-600"></p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="hallazgo-severidad" class="mb-2 block text-sm font-semibold text-gray-900">Clasificación</label>
                    <select id="hallazgo-severidad" x-model="severidad"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        <option value="">Selecciona</option>
                        <option value="critico">Crítico</option>
                        <option value="no_critico">No crítico</option>
                    </select>
                    <p x-show="errores.severidad" x-text="errores.severidad" class="mt-1 text-sm text-red-600"></p>
                </div>
                <div>
                    <span class="mb-2 block text-sm font-semibold text-gray-900">Plazo</span>
                    <p class="rounded-lg border border-gray-200 bg-gray-50 px-3 py-3 text-sm text-gray-700"
                       x-text="severidad === 'critico'
                           ? @js('Contención en ' . config('auditorias.contencion_critico_horas', 24) . ' h y cierre en ' . \App\Models\AuditFinding::totalDays('critico') . ' días hábiles')
                           : (severidad === 'no_critico'
                               ? @js('Cierre en ' . \App\Models\AuditFinding::totalDays('no_critico') . ' días hábiles')
                               : 'Elige la clasificación')"></p>
                </div>
            </div>

            <fieldset>
                <legend class="mb-2 block text-sm font-semibold text-gray-900">Responsable del hallazgo</legend>
                <p class="mb-2 text-sm text-gray-500">Él escribe la causa raíz y las acciones. Si le toca a varias personas, registra un hallazgo para cada una.</p>
                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                    @foreach($auditees as $participante)
                        <label class="flex items-start gap-3">
                            <input type="radio" value="{{ $participante['id'] }}" x-model.number="responsable"
                                   class="mt-0.5 h-4 w-4 border-gray-300 text-brand focus:ring-brand">
                            <span>
                                <span class="block text-sm font-semibold text-gray-900">{{ $participante['nombre'] }}</span>
                                <span class="block text-xs text-gray-500">{{ $participante['puesto'] }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <p x-show="errores.responsable" x-text="errores.responsable" class="mt-1 text-sm text-red-600"></p>
            </fieldset>

            <p class="text-sm text-gray-600">El hallazgo se envía a su responsable (y a los responsables del procedimiento) cuando finalices la auditoría. Hasta entonces puedes editarlo o eliminarlo. Recuerda dar "Guardar avance".</p>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark"
                    x-text="indice === null ? 'Guardar hallazgo' : 'Guardar cambios'">Guardar hallazgo</button>
        </form>
    </div>
</div>
