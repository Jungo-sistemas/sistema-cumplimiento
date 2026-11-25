{{-- Ventana "Fecha de la auditoría". Se abre con:
     $dispatch('programar-fecha', { url, proceso, auditados, actual, fecha, hora })
     El auditor captura el día y la hora que acordó con el auditado (la reunión se manda por Teams).
     El auditado recibe el aviso unos días antes (config auditorias.aviso_dias_antes); si ya se le
     había avisado, se le avisa del cambio. --}}
{{-- Si el servidor la rechazó (fecha pasada, etc.) se vuelve a abrir sola con el mensaje y lo que
     se había escrito (session 'reprogramar' + bolsa de errores 'schedule'). --}}
<div x-data="{
        abierto: false,
        enviando: false,
        a: {},
        fecha: '',
        hora: '',
        minima: @js(\App\Models\Audit::localNow()->toDateString()),
        abrir(datos) {
            this.a = datos;
            this.fecha = datos.fecha ?? '';
            this.hora = datos.hora ?? '10:00';
            this.enviando = false;
            this.abierto = true;
        },
     }"
     @if(session('reprogramar')) x-init="abrir(@js(session('reprogramar')))" @endif
     @programar-fecha.window="abrir($event.detail)"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-center justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink" x-text="a.actual ? 'Cambiar fecha' : 'Poner fecha'">Fecha de la auditoría</h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        <form method="POST" :action="a.url" class="mt-5 space-y-5"
              @submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
            @csrf
            @if($errors->schedule->any())
                <div class="rounded-xl border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                    @foreach($errors->schedule->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif
            <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                <div class="font-semibold text-gray-900" x-text="a.proceso"></div>
                <p class="mt-1 text-sm text-gray-600">Fecha actual: <span x-text="a.actual ?? 'sin fecha'"></span> · Auditados: <span x-text="a.auditados"></span></p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="fecha-auditoria" class="mb-2 block text-sm font-semibold text-gray-900">Fecha</label>
                    <input id="fecha-auditoria" type="date" name="fecha" x-model="fecha" :min="minima" required
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
                <div>
                    <label for="hora-auditoria" class="mb-2 block text-sm font-semibold text-gray-900">Hora</label>
                    <input id="hora-auditoria" type="time" name="hora" x-model="hora" required
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>

            <p class="text-sm text-gray-600">
                Manda la reunión por Teams como siempre; aquí solo queda el registro.
                Los auditados reciben el aviso {{ \App\Models\Audit::noticeDays() }} días antes de la fecha (si ya están dentro de ese plazo, les llega en este momento).
                <span x-show="a.actual">Si ya se les había avisado, se les avisa del cambio.</span>
            </p>

            <button type="submit" :disabled="enviando"
                    class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark disabled:cursor-wait disabled:opacity-60">
                <span x-show="! enviando">Guardar fecha</span>
                <span x-show="enviando" x-cloak>Guardando…</span>
            </button>
        </form>
    </div>
</div>
