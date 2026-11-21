{{-- Ventana "Registrar hallazgo". Solo se incluye en el checklist de una auditoría abierta (En captura).
    Cada criterio la abre con: @click="$dispatch('registrar-hallazgo', { criterio: número })"
    El checklist también la abre solo cuando se elige No cumple o Parcial en un criterio sin hallazgo,
    y con los datos de un hallazgo cuando se da "Editar". Al guardar, le manda el hallazgo al checklist (hallazgo-guardado).
    Usa $auditoria y $criterios de esa página: la auditoría y el criterio se muestran, pero no se pueden cambiar.
    De $participantes sale la lista para elegir al responsable. --}}
<div x-data="{
        abierto: false,
        criterios: @js(array_column($criterios, 'texto')),
        criterio: null,
        // null = hallazgo nuevo; un número = el hallazgo de ese criterio que se está editando.
        indice: null,
        titulo: '',
        descripcion: '',
        severidad: '',
        fecha: '',
        responsable: '',
        soportes: [],
        errores: {},
        // Para editar, el checklist manda también indice y hallazgo; para uno nuevo, solo criterio.
        abrir(detalle) {
            const h = detalle.hallazgo ?? {};
            this.abierto = true;
            this.criterio = detalle.criterio;
            this.indice = detalle.indice ?? null;
            this.titulo = h.titulo ?? '';
            this.descripcion = h.descripcion ?? '';
            this.severidad = h.severidad ?? '';
            this.fecha = h.fecha ?? '';
            this.responsable = h.responsable ?? '';
            this.soportes = h.soportes ?? [];
            this.errores = {};
            this.$refs.archivos.value = '';
        },
        guardar() {
            this.errores = {};
            if (! this.titulo.trim()) this.errores.titulo = 'Escribe el título del hallazgo.';
            if (! this.descripcion.trim()) this.errores.descripcion = 'Describe lo que encontraste.';
            if (! this.severidad) this.errores.severidad = 'Elige la clasificación.';
            if (! this.fecha) this.errores.fecha = 'Elige la fecha compromiso.';
            if (! this.responsable) this.errores.responsable = 'Elige al responsable.';

            // Sin errores = todo lleno: el hallazgo se le manda al checklist, que lo agrega a su criterio
            // (o reemplaza al que se editó), y la ventana se cierra.
            if (Object.keys(this.errores).length === 0) {
                this.$dispatch('hallazgo-guardado', {
                    criterio: this.criterio,
                    indice: this.indice,
                    hallazgo: {
                        titulo: this.titulo.trim(),
                        descripcion: this.descripcion.trim(),
                        severidad: this.severidad,
                        fecha: this.fecha,
                        responsable: this.responsable,
                        soportes: this.soportes,
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

    {{-- Un clic fuera del panel blanco cierra la ventana --}}
    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink" x-text="indice === null ? 'Registrar hallazgo' : 'Editar hallazgo'">Registrar hallazgo</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        <form @submit.prevent="guardar()" class="mt-5 space-y-5 rounded-2xl border border-gray-200 bg-gray-50 p-5">
            {{-- Vienen del criterio donde se dio clic: solo se muestran, no hay nada que elegir --}}
            <div class="grid grid-cols-1 gap-4 rounded-xl border border-gray-200 bg-white p-4 sm:grid-cols-2">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Auditoría</div>
                    <p class="mt-1 text-[15px] text-gray-700">{{ $auditoria['procedimiento'] }}</p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Criterio del checklist</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="criterio === null ? '' : (criterio + 1) + '. ' + criterios[criterio]"></p>
                </div>
            </div>

            <div>
                <label for="hallazgo-titulo" class="mb-2 block text-sm font-semibold text-gray-900">Título del hallazgo</label>
                <input id="hallazgo-titulo" type="text" name="titulo" x-model="titulo" placeholder="Describe la desviación observada"
                       class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                <p x-show="errores.titulo" x-text="errores.titulo" class="mt-1 text-sm text-red-600"></p>
            </div>

            <div>
                <label for="hallazgo-descripcion" class="mb-2 block text-sm font-semibold text-gray-900">Descripción y evidencia observada</label>
                <textarea id="hallazgo-descripcion" name="descripcion" x-model="descripcion" rows="3"
                          placeholder="Explica qué encontraste, qué requisito no se cumple y en qué evidencia se sustenta."
                          class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
                <p x-show="errores.descripcion" x-text="errores.descripcion" class="mt-1 text-sm text-red-600"></p>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="hallazgo-severidad" class="mb-2 block text-sm font-semibold text-gray-900">Clasificación</label>
                    <select id="hallazgo-severidad" name="severidad" x-model="severidad"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        <option value="">Selecciona</option>
                        <option value="critico">Crítico</option>
                        <option value="no_critico">No crítico</option>
                    </select>
                    <p x-show="errores.severidad" x-text="errores.severidad" class="mt-1 text-sm text-red-600"></p>
                </div>
                <div>
                    <label for="hallazgo-fecha" class="mb-2 block text-sm font-semibold text-gray-900">Fecha compromiso</label>
                    <input id="hallazgo-fecha" type="date" name="fecha_compromiso" x-model="fecha"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <p x-show="errores.fecha" x-text="errores.fecha" class="mt-1 text-sm text-red-600"></p>
                </div>
            </div>

            {{-- Responsable: uno solo (audit_findings.responsible_id), de los invitados que no son del equipo auditor --}}
            <fieldset>
                <legend class="mb-2 block text-sm font-semibold text-gray-900">Responsable del hallazgo</legend>
                <p class="mb-2 text-sm text-gray-500">Si le toca a varias personas, registra un hallazgo para cada una.</p>
                <div class="space-y-3 rounded-lg border border-gray-200 bg-white p-4">
                    @foreach(collect($participantes)->where('equipo', 'auditado') as $participante)
                        <label class="flex items-start gap-3">
                            <input type="radio" name="responsable" value="{{ $participante['nombre'] }}" x-model="responsable"
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

            {{-- x-ref: abrir() la vacía, porque la misma ventana se usa para todos los hallazgos.
                 Al editar no se puede volver a llenar un campo de archivos: se muestran los que ya tenía. --}}
            <div>
                <label for="hallazgo-soporte" class="mb-2 block text-sm font-semibold text-gray-900">Adjuntar soporte (opcional)</label>
                <input id="hallazgo-soporte" type="file" name="soportes[]" multiple x-ref="archivos"
                       @change="soportes = [...$event.target.files].map(archivo => archivo.name)"
                       class="block w-full rounded-lg border border-gray-200 bg-white p-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand">
                <p x-show="soportes.length" class="mt-1 text-sm text-gray-600">
                    Adjuntos: <span x-text="soportes.join(', ')"></span>.
                    <span x-show="indice !== null">Si eliges otros archivos, reemplazan a estos.</span>
                </p>
            </div>

            <p class="text-sm text-gray-600">El hallazgo se envía a su responsable cuando finalices la auditoría. Hasta entonces puedes editarlo o eliminarlo.</p>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark"
                    x-text="indice === null ? 'Guardar hallazgo' : 'Guardar cambios'">Guardar hallazgo</button>
        </form>
    </div>
</div>
