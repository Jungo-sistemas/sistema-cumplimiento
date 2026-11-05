{{-- Ventana "Registrar hallazgo". Solo se incluye en el checklist de una auditoría abierta (En captura).
     Cada criterio la abre con: @click="$dispatch('registrar-hallazgo', { criterio: número })"
     Usa $auditoria y $criterios de esa página: la auditoría y el criterio se muestran, pero no se pueden cambiar. --}}
<div x-data="{
        abierto: false,
        criterios: @js(array_column($criterios, 'texto')),
        criterio: null,
        titulo: '',
        descripcion: '',
        severidad: '',
        fecha: '',
        errores: {},
        registrado: false,
        abrir(detalle) {
            this.abierto = true;
            this.criterio = detalle.criterio;
            this.titulo = '';
            this.descripcion = '';
            this.severidad = '';
            this.fecha = '';
            this.errores = {};
            this.registrado = false;
        },
        registrar() {
            this.errores = {};
            if (! this.titulo.trim()) this.errores.titulo = 'Escribe el título del hallazgo.';
            if (! this.descripcion.trim()) this.errores.descripcion = 'Describe lo que encontraste.';
            if (! this.severidad) this.errores.severidad = 'Elige la severidad.';
            if (! this.fecha) this.errores.fecha = 'Elige la fecha compromiso.';

            // Sin errores = todo lleno. En la demo solo mostramos la confirmación.
            if (Object.keys(this.errores).length === 0) this.registrado = true;
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
            <h2 class="text-xl font-bold text-brand-ink">Registrar hallazgo</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        <form @submit.prevent="registrar()" x-show="! registrado" class="mt-5 space-y-5 rounded-2xl border border-gray-200 bg-gray-50 p-5">
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
                    <label for="hallazgo-severidad" class="mb-2 block text-sm font-semibold text-gray-900">Severidad</label>
                    <select id="hallazgo-severidad" name="severidad" x-model="severidad"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        <option value="">Selecciona</option>
                        <option value="critico">Crítico</option>
                        <option value="mayor">Mayor</option>
                        <option value="menor">Menor</option>
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

            <p class="text-sm text-gray-600">Responsable: <span class="font-semibold text-gray-900">{{ $auditoria['auditado'] }}</span></p>

            <div>
                <label for="hallazgo-soporte" class="mb-2 block text-sm font-semibold text-gray-900">Adjuntar soporte (opcional)</label>
                <input id="hallazgo-soporte" type="file" name="soportes[]" multiple
                       class="block w-full rounded-lg border border-gray-200 bg-white p-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand">
            </div>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Registrar y notificar hallazgo</button>
        </form>

        {{-- Confirmación (demo): con la base de datos, aquí ya se habrá guardado y notificado --}}
        <div x-show="registrado" class="mt-5 rounded-2xl border border-green-200 bg-green-50 p-5">
            <p class="font-semibold text-green-800">Hallazgo registrado (demo).</p>
            <p class="mt-1 text-sm text-green-700">
                Cuando exista la base de datos, aquí se guardará, se le avisará a
                <span class="font-semibold">{{ $auditoria['auditado'] }}</span>
                y aparecerá en la lista como "Por enviar".
            </p>
            <button type="button" @click="abierto = false" class="mt-4 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Listo</button>
        </div>
    </div>
</div>
