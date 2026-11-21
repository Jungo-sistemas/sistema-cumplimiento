{{-- Ventana "Criterios a evaluar". Se incluye en el checklist.
     Cada criterio la abre con: @click.prevent="$dispatch('ver-criterios', { actividad: número })"
     Usa $auditoria y $actividades de esa página: muestra la actividad del procedimiento con la que se evalúa el criterio,
     con los mismos apartados que el documento (Responsable, Quién, Qué, Cómo, Cuándo, Dónde y Evidencia). --}}
<div x-data="{
        abierto: false,
        actividades: @js($actividades),
        numero: null,
        // La actividad elegida. ?? {} evita errores mientras la ventana está cerrada.
        get a() { return this.actividades[this.numero] ?? {} },
        abrir(detalle) {
            this.numero = detalle.actividad;
            this.abierto = true;
        },
     }"
     @ver-criterios.window="abrir($event.detail)"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    {{-- Un clic fuera del panel blanco cierra la ventana --}}
    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-3xl space-y-5 rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl font-bold text-brand-ink">Criterios a evaluar</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $auditoria['procedimiento'] }} · Descripción del proceso / actividades</p>
            </div>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>

        {{-- Número, título y responsables, como vienen en el procedimiento --}}
        <div>
            <h3 class="text-lg font-semibold text-brand" x-text="numero + '. ' + a.titulo"></h3>
            <p class="mt-1 text-sm italic text-gray-500">Responsable: <span x-text="a.responsable"></span></p>
        </div>

        <div class="space-y-3 text-[15px] text-gray-700">
            <p><span class="font-semibold text-gray-900">Quién:</span> <span x-text="a.quien"></span></p>
            <p><span class="font-semibold text-gray-900">Qué:</span> <span x-text="a.que"></span></p>
            <div>
                <span class="font-semibold text-gray-900">Cómo:</span>
                {{-- Un renglón por paso. El número ya viene en el texto, así que no usamos <ol> (que volvería a numerar). --}}
                <div class="mt-2 space-y-2 border-l-2 border-gray-200 pl-4">
                    <template x-for="paso in (a.como ?? [])">
                        <p x-text="paso"></p>
                    </template>
                </div>
            </div>
            <p><span class="font-semibold text-gray-900">Cuándo:</span> <span x-text="a.cuando"></span></p>
            <p><span class="font-semibold text-gray-900">Dónde:</span> <span x-text="a.donde"></span></p>
        </div>

        {{-- La evidencia es lo que el auditor pide ver: va resaltada --}}
        <div class="rounded-lg border-l-4 border-brand bg-brand-50 px-4 py-3">
            <div class="text-xs font-semibold uppercase tracking-wider text-brand">Evidencia</div>
            <p class="mt-1 text-[15px] text-gray-900" x-text="a.evidencia"></p>
        </div>
    </div>
</div>
