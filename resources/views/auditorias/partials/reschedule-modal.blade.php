{{-- Ventana "Solicitar reprogramación". Se incluye en el checklist de una auditoría que todavía no se finaliza.
     Se abre con: @click="$dispatch('solicitar-reprogramacion')"
     Usa $auditoria de esa página. El auditor explica el motivo y propone una nueva fecha;
     el auditor líder la aprueba o la rechaza. --}}
<div x-data="{
        abierto: false,
        motivo: '',
        fecha: '',
        // La fecha más próxima que se puede proponer: mañana, con la hora de México (la app trabaja en UTC).
        minima: @js(today('America/Mexico_City')->addDay()->toDateString()),
        errores: {},
        enviada: false,
        abrir() {
            this.abierto = true;
            this.motivo = '';
            this.fecha = '';
            this.errores = {};
            this.enviada = false;
        },
        enviar() {
            this.errores = {};
            if (! this.motivo.trim()) this.errores.motivo = 'Explica por qué hay que reprogramarla.';
            if (! this.fecha) this.errores.fecha = 'Elige la fecha que propones.';
            // Las fechas vienen como 2026-11-20: comparadas como texto quedan en orden de calendario.
            else if (this.fecha < this.minima) this.errores.fecha = 'La fecha propuesta debe ser a partir de mañana.';

            // Sin errores = todo bien. En la demo solo mostramos la confirmación.
            if (Object.keys(this.errores).length === 0) this.enviada = true;
        },
        // La fecha propuesta escrita como en el resto del módulo: 25 nov 2026.
        get fechaTexto() {
            if (! this.fecha) return '';
            return new Date(this.fecha + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        },
     }"
     @solicitar-reprogramacion.window="abrir()"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    {{-- Un clic fuera del panel blanco cierra la ventana --}}
    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink">Solicitar reprogramación</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        <form @submit.prevent="enviar()" x-show="! enviada" class="mt-5 space-y-5">
            {{-- La auditoría que se quiere mover: solo se muestra --}}
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="font-semibold text-gray-900">{{ $auditoria['procedimiento'] }}</div>
                <p class="mt-1 text-sm text-gray-600">Fecha actual: {{ $auditoria['fecha'] }} · Auditado: {{ $auditoria['auditado'] }}</p>
            </div>

            <div>
                <label for="reprogramacion-motivo" class="mb-2 block text-sm font-semibold text-gray-900">Motivo</label>
                <textarea id="reprogramacion-motivo" name="motivo" x-model="motivo" rows="3"
                          placeholder="Por ejemplo: el auditado no se presentó a la llamada."
                          class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
                <p x-show="errores.motivo" x-text="errores.motivo" class="mt-1 text-sm text-red-600"></p>
            </div>

            {{-- :min hace que el calendario no deje elegir días anteriores; enviar() lo revisa también,
                 porque la fecha se puede escribir a mano. --}}
            <div>
                <label for="reprogramacion-fecha" class="mb-2 block text-sm font-semibold text-gray-900">Fecha propuesta</label>
                <input id="reprogramacion-fecha" type="date" name="fecha_propuesta" x-model="fecha" :min="minima"
                       class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand sm:w-60">
                <p x-show="errores.fecha" x-text="errores.fecha" class="mt-1 text-sm text-red-600"></p>
            </div>

            <p class="text-sm text-gray-600">El auditor líder aprueba o rechaza la fecha propuesta. Mientras tanto, la auditoría conserva su fecha actual.</p>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Enviar solicitud</button>
        </form>

        {{-- Confirmación (demo): con la base de datos, aquí ya se habrá guardado y avisado --}}
        <div x-show="enviada" class="mt-5 rounded-2xl border border-green-200 bg-green-50 p-5">
            <p class="font-semibold text-green-800">Solicitud enviada (demo).</p>
            <p class="mt-1 text-sm text-green-700">
                Cuando exista la base de datos, se guardará y se le avisará al auditor líder, que aprobará o rechazará
                la fecha propuesta: <span class="font-semibold" x-text="fechaTexto"></span>.
                Mientras responde, la auditoría aparecerá como "Reprogramación solicitada".
            </p>
            <button type="button" @click="abierto = false" class="mt-4 rounded-lg bg-brand px-4 py-2 text-sm font-semibold text-white hover:bg-brand-dark">Listo</button>
        </div>
    </div>
</div>
