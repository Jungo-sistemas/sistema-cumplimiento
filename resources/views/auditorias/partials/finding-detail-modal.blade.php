{{-- Ventana con el detalle de un hallazgo. Se incluye en Hallazgos.
     Cada fila la abre mandando sus datos: @click="$dispatch('abrir-hallazgo', @js($h))" --}}
@php
    // Datos de ejemplo que la lista no muestra. Con la base de datos vendrán del mismo hallazgo.
    $detallesHallazgo = [
        'HAL-001' => [
            'registro' => '11 sep 2026', 'limite' => '18 sep 2026', 'fecha' => '2026-11-25', 'clausula' => '7.5.3', 'cierre' => 'Inicia al aceptar el plan',
            'causa'    => 'El supervisor del turno no sabía que debía firmar la bitácora antes de iniciar las maniobras.',
            'accion'   => 'Agregar la firma del supervisor al formato de la bitácora y capacitar a los supervisores de turno.',
            'evidencia' => null,
            'historial' => ['11 sep 2026 · Daniel Ruiz: Hallazgo registrado.', '16 sep 2026 · Laura Méndez: Causa raíz y acción correctiva enviadas.'],
        ],
        'HAL-002' => [
            'registro' => '11 sep 2026', 'limite' => '18 sep 2026', 'fecha' => '2026-12-20', 'clausula' => '8.1', 'cierre' => 'Inicia al aceptar el plan',
            'causa' => null, 'accion' => null, 'evidencia' => null,
            'historial' => ['11 sep 2026 · Daniel Ruiz: Hallazgo registrado.'],
        ],
        'HAL-003' => [
            'registro' => '14 sep 2026', 'limite' => '21 sep 2026', 'fecha' => '2026-09-30', 'clausula' => '7.5.1', 'cierre' => '20 sep 2026 → 30 sep 2026',
            'causa'    => 'El turno de la noche no tenía asignado quién captura la temperatura.',
            'accion'   => 'Asignar al responsable de recepción de cada turno y agregar la captura al checklist de cierre.',
            'evidencia' => 'registro-temperatura-septiembre.pdf',
            'historial' => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado.', '18 sep 2026 · Jorge Salinas: Causa raíz y acción correctiva enviadas.', '20 sep 2026 · Daniel Ruiz: Causa raíz y acción aceptadas.', '28 sep 2026 · Jorge Salinas: Evidencia de la corrección cargada.'],
        ],
        'HAL-004' => [
            'registro' => '28 ago 2026', 'limite' => '04 sep 2026', 'fecha' => '2026-09-10', 'clausula' => '8.5', 'cierre' => 'Inicia al aceptar el plan',
            'causa'    => 'Faltaban etiquetas en el almacén.',
            'accion'   => 'Comprar etiquetas.',
            'evidencia' => null,
            'historial' => ['28 ago 2026 · Daniel Ruiz: Hallazgo registrado.', '02 sep 2026 · Ana Torres: Causa raíz y acción correctiva enviadas.', '03 sep 2026 · Daniel Ruiz: Solicitó otra corrección: la causa describe el problema, no por qué faltaban las etiquetas.'],
        ],
        'HAL-005' => [
            'registro' => '14 sep 2026', 'limite' => '21 sep 2026', 'fecha' => '2026-10-15', 'clausula' => '7.2', 'cierre' => '22 sep 2026 → 15 oct 2026',
            'causa'    => 'El programa de capacitación no avisaba cuando una certificación estaba por vencer.',
            'accion'   => 'Agregar alertas de vencimiento al programa y reprogramar la capacitación.',
            'evidencia' => null,
            'historial' => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado.', '19 sep 2026 · Jorge Salinas: Causa raíz y acción correctiva enviadas.', '22 sep 2026 · Daniel Ruiz: Causa raíz y acción aceptadas.'],
        ],
        'HAL-006' => [
            'registro' => '20 ago 2026', 'limite' => '27 ago 2026', 'fecha' => '2026-09-05', 'clausula' => '7.5.3', 'cierre' => '25 ago 2026 → 05 sep 2026',
            'causa'    => 'No se retiraban las copias impresas al publicar una nueva versión.',
            'accion'   => 'Recoger las copias anteriores al liberar cada versión y dejarlo registrado.',
            'evidencia' => 'acta-retiro-copias.pdf',
            'historial' => ['20 ago 2026 · Daniel Ruiz: Hallazgo registrado.', '24 ago 2026 · Luis Herrera: Causa raíz y acción correctiva enviadas.', '25 ago 2026 · Daniel Ruiz: Causa raíz y acción aceptadas.', '04 sep 2026 · Luis Herrera: Evidencia de la corrección cargada.', '05 sep 2026 · Daniel Ruiz: Evidencia aceptada. Hallazgo cerrado.'],
        ],
    ];
@endphp

<div x-data="{
        abierto: false,
        h: {},
        severidad: '',
        fecha: '',
        guardado: false,
        comentario: '',
        errorComentario: '',
        mensaje: '',
        auditor: @js(auth()->user()->name),
        get revision() {
            // Qué le toca revisar al auditor en este hallazgo (o nada)
            if (this.h.seguimiento === 'Causa raíz por revisar') return 'causa';
            if (this.h.seguimiento === 'Evidencia por revisar') return 'evidencia';
            return null;
        },
        detalles: @js($detallesHallazgo),
        colores: @js(['severidad' => $severidades, 'seguimiento' => $seguimientos]),
        abrir(fila) {
            // Junta los datos de la fila con los de detalle del mismo código.
            this.h = { ...fila, ...(this.detalles[fila.codigo] ?? {}) };
            this.severidad = this.h.severidad;
            this.fecha = this.h.fecha ?? '';
            this.guardado = false;
            this.comentario = '';
            this.errorComentario = '';
            this.mensaje = '';
            this.abierto = true;
        },
        guardarClasificacion() {
            this.h.severidad = this.severidad;
            this.guardado = true;
            setTimeout(() => this.guardado = false, 3000);
        },
        decidir(acepta) {
            this.errorComentario = '';
            if (! acepta && ! this.comentario.trim()) {
                this.errorComentario = 'Escribe un comentario para explicar qué debe corregir.';
                return;
            }

            const hoy = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
            let texto;
            if (! acepta) {
                texto = 'Solicitó otra corrección: ' + this.comentario.trim();
                // Rechazar el plan lo regresa a escribirse; rechazar la evidencia solo pide otra prueba.
                this.h.seguimiento = this.revision === 'causa' ? 'Rechazada' : 'Sin cargar';
            } else if (this.revision === 'causa') {
                texto = 'Causa raíz y acción aceptadas.';
                this.h.seguimiento = 'Sin cargar';
            } else {
                texto = 'Evidencia aceptada. Hallazgo cerrado.';
                this.h.seguimiento = 'Cerrado';
            }

            this.h.historial = [...(this.h.historial ?? []), hoy + ' · ' + this.auditor + ': ' + texto];
            this.comentario = '';
            this.mensaje = 'Decisión registrada (demo).';
        },
     }"
     @abrir-hallazgo.window="abrir($event.detail)"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-xl">

        {{-- Título y etiquetas --}}
        <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
            <h2 class="text-xl font-bold text-brand-ink" x-text="h.titulo"></h2>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="colores.severidad[h.severidad]" x-text="h.severidad"></span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="colores.seguimiento[h.seguimiento]" x-text="h.seguimiento"></span>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700" x-text="h.empresa"></span>
        </div>
        <p x-show="mensaje" x-transition class="text-sm font-semibold text-green-700" x-text="mensaje"></p>

        {{-- Clasificar no conformidad --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Clasificar no conformidad</h3>
            <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label for="detalle-severidad" class="mb-2 block text-sm font-semibold text-gray-900">Clasificación</label>
                    <select id="detalle-severidad" x-model="severidad"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        @foreach(array_keys($severidades) as $opcion)
                            <option value="{{ $opcion }}">{{ $opcion }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="detalle-fecha" class="mb-2 block text-sm font-semibold text-gray-900">Fecha compromiso</label>
                    <input id="detalle-fecha" type="date" x-model="fecha"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" @click="guardarClasificacion()" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Guardar clasificación</button>
                <span x-show="guardado" x-transition class="text-sm font-semibold text-green-700">Clasificación guardada (demo).</span>
            </div>
        </div>

        {{-- Relojes y responsable --}}
        <div class="grid grid-cols-1 gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2">
            <div>
                <div class="text-sm text-gray-500">Reloj de respuesta</div>
                <div class="mt-1 text-[15px] text-gray-900" x-text="h.registro + ' → ' + h.limite"></div>
            </div>
            <div>
                <div class="text-sm text-gray-500">Reloj de cierre</div>
                <div class="mt-1 text-[15px] text-gray-900" x-text="h.cierre"></div>
            </div>
            <p class="text-sm text-gray-700 sm:col-span-2">
                Responsable: <span class="font-semibold" x-text="h.responsable"></span> · Cláusula <span x-text="h.clausula"></span>
            </p>
        </div>

        {{-- Causa raíz y acción correctiva: solo lectura, las llena el responsable --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Causa raíz y acción correctiva</h3>
            <div x-show="h.causa" class="mt-4 space-y-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Causa raíz</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.causa"></p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Acción correctiva</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.accion"></p>
                </div>
            </div>
            <p x-show="! h.causa" class="mt-2 text-[15px] text-gray-500">El responsable todavía no envía su causa raíz.</p>
        </div>

        {{-- Evidencia de la corrección --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Evidencia de la corrección</h3>
            <p x-show="h.evidencia" class="mt-2 text-[15px] font-semibold text-brand" x-text="h.evidencia"></p>
            <p x-show="! h.evidencia" class="mt-2 text-[15px] text-gray-500">Sin evidencia cargada.</p>
        </div>

        {{-- Decisión del auditor: solo cuando le toca revisar la causa raíz o la evidencia --}}
        <div x-show="revision" class="rounded-2xl border-2 border-brand-100 bg-brand-50 p-5">
            <h3 class="font-semibold text-gray-900" x-text="revision === 'causa' ? 'Decisión del auditor: causa raíz y acción correctiva' : 'Decisión del auditor: evidencia de la corrección'"></h3>

            <label for="detalle-comentario" class="mb-2 mt-4 block text-sm font-semibold text-gray-900">Comentarios</label>
            <textarea id="detalle-comentario" x-model="comentario" rows="3" placeholder="Obligatorio si solicitas otra corrección"
                      class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            <p x-show="errorComentario" x-text="errorComentario" class="mt-1 text-sm text-red-600"></p>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" @click="decidir(false)"
                        class="rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-gray-50">Solicitar otra corrección</button>
                <button type="button" @click="decidir(true)"
                        class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark"
                        x-text="revision === 'causa' ? 'Aceptar causa raíz y acción' : 'Aceptar evidencia'"></button>
            </div>
        </div>

        {{-- Seguimiento --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Seguimiento</h3>
            <ul class="mt-2 space-y-1 text-sm text-gray-700">
                <template x-for="(linea, i) in (h.historial ?? [])" :key="i">
                    <li x-text="linea"></li>
                </template>
            </ul>
        </div>
    </div>
</div>
