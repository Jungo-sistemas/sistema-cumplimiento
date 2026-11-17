{{-- Ventana con el detalle de un hallazgo. Se incluye en Hallazgos.
     Cada fila la abre mandando sus datos: @click="$dispatch('abrir-hallazgo', @js($h))" --}}
@php
    // Datos de ejemplo que la lista no muestra. Con la base de datos vendrán del mismo hallazgo.
    // "evidencias" es una lista porque el auditado puede subir varios archivos.
    $detallesHallazgo = [
        'HAL-001' => [
            'clausula'   => '7.5.3',
            'causa'      => 'El supervisor del turno no sabía que debía firmar la bitácora antes de iniciar las maniobras.',
            'acciones'   => 'Agregar la firma del supervisor al formato de la bitácora y capacitar a los supervisores de turno.',
            'evidencias' => ['formato-bitacora-con-firma.pdf', 'lista-capacitacion-supervisores.pdf'],
            'historial'  => ['11 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '16 sep 2026 · Laura Méndez: Envió causa raíz, acciones y evidencia.'],
        ],
        'HAL-002' => [
            'clausula'   => '8.1',
            'causa'      => null, 'acciones' => null, 'evidencias' => [],
            'historial'  => ['11 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.'],
        ],
        'HAL-003' => [
            'clausula'   => '7.5.1',
            'causa'      => 'El turno de la noche no tenía asignado quién captura la temperatura.',
            'acciones'   => 'Asignar al responsable de recepción de cada turno y agregar la captura al checklist de cierre.',
            'evidencias' => ['registro-temperatura-septiembre.pdf'],
            'historial'  => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '28 sep 2026 · Jorge Salinas: Envió causa raíz, acciones y evidencia.'],
        ],
        'HAL-004' => [
            'clausula'   => '8.5',
            'causa'      => 'Faltaban etiquetas en el almacén.',
            'acciones'   => 'Comprar etiquetas.',
            'evidencias' => ['foto-almacen.jpg'],
            'historial'  => ['28 ago 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '02 sep 2026 · Ana Torres: Envió causa raíz, acciones y evidencia.', '03 sep 2026 · Daniel Ruiz: Rechazó la respuesta: la causa describe el problema, no por qué faltaban las etiquetas.'],
        ],
        'HAL-005' => [
            'clausula'   => '7.2',
            'causa'      => null, 'acciones' => null, 'evidencias' => [],
            'historial'  => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.'],
        ],
        'HAL-006' => [
            'clausula'   => '7.5.3',
            'causa'      => 'No se retiraban las copias impresas al publicar una nueva versión.',
            'acciones'   => 'Recoger las copias anteriores al liberar cada versión y dejarlo registrado.',
            'evidencias' => ['acta-retiro-copias.pdf'],
            'historial'  => ['20 ago 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '04 sep 2026 · Luis Herrera: Envió causa raíz, acciones y evidencia.', '05 sep 2026 · Daniel Ruiz: Aprobó la respuesta. Hallazgo cerrado.'],
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
        // Hoy en el mismo formato que la fecha compromiso (2026-11-17), para compararlas.
        hoy: @js(today()->toDateString()),
        // Al auditor solo le toca decidir cuando el auditado ya envió su respuesta.
        get porRevisar() { return this.h.seguimiento === 'Por revisar' },
        // Vencido: sigue abierto y su fecha compromiso ya pasó (igual que en la lista).
        get vencido() { return this.h.seguimiento !== 'Cerrado' && this.fecha !== '' && this.fecha < this.hoy },
        detalles: @js($detallesHallazgo),
        colores: @js(['severidad' => $severidades, 'seguimiento' => $seguimientos]),
        abrir(fila) {
            // Junta los datos de la fila con los de detalle del mismo código.
            this.h = { ...fila, ...(this.detalles[fila.codigo] ?? {}) };
            this.severidad = this.h.severidad;
            this.fecha = this.h.compromiso ?? '';
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
         decidir(aprueba) {
            this.errorComentario = '';
            if (! aprueba && ! this.comentario.trim()) {
                this.errorComentario = 'Escribe el motivo del rechazo.';
                return;
            }

            const dia = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
            // Aprobar cierra el hallazgo; rechazar se lo regresa al auditado para que vuelva a enviar su respuesta.
            const texto = aprueba ? 'Aprobó la respuesta. Hallazgo cerrado.' : 'Rechazó la respuesta: ' + this.comentario.trim();
            this.h.seguimiento = aprueba ? 'Cerrado' : 'Rechazada';

            this.h.historial = [...(this.h.historial ?? []), dia + ' · ' + this.auditor + ': ' + texto];
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
                    <label for="detalle-fecha" class="mb-2 flex items-center gap-2 text-sm font-semibold text-gray-900">
                        Fecha compromiso
                        <span x-show="vencido" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Vencido</span>
                    </label>
                    <input id="detalle-fecha" type="date" x-model="fecha"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>
            <div class="mt-4 flex flex-wrap items-center gap-3">
                <button type="button" @click="guardarClasificacion()" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Guardar clasificación</button>
                <span x-show="guardado" x-transition class="text-sm font-semibold text-green-700">Clasificación guardada (demo).</span>
            </div>
        </div>

        {{-- Responsables --}}
        <p class="rounded-2xl border border-gray-200 p-5 text-sm text-gray-700">
            Responsables: <span class="font-semibold" x-text="(h.responsables ?? []).join(', ')"></span> · Cláusula <span x-text="h.clausula"></span>
        </p>

        {{-- Respuesta del auditado: causa raíz, acciones y evidencia llegan juntas, en una sola vuelta --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Respuesta del auditado</h3>
            <div x-show="h.causa" class="mt-4 space-y-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Causa raíz</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.causa"></p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Acciones</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.acciones"></p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Evidencia</div>
                    <ul class="mt-2 flex flex-wrap gap-2">
                        <template x-for="archivo in (h.evidencias ?? [])" :key="archivo">
                            <li class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-brand" x-text="archivo"></li>
                        </template>
                    </ul>
                </div>
                <p x-show="h.seguimiento === 'Rechazada'" class="text-sm font-semibold text-red-700">Rechazaste esta respuesta. El auditado debe corregirla y volver a enviarla.</p>
            </div>
            <p x-show="! h.causa" class="mt-2 text-[15px] text-gray-500">El auditado todavía no envía su respuesta.</p>
        </div>

        {{-- Decisión del auditor: solo cuando el auditado ya envió su respuesta --}}
        <div x-show="porRevisar" class="rounded-2xl border-2 border-brand-100 bg-brand-50 p-5">
            <h3 class="font-semibold text-gray-900">Decisión del auditor</h3>
            <p class="mt-1 text-sm text-gray-600">Si apruebas, el hallazgo se cierra. Si rechazas, regresa al auditado con tu motivo.</p>

            <label for="detalle-comentario" class="mb-2 mt-4 block text-sm font-semibold text-gray-900">Comentarios</label>
            <textarea id="detalle-comentario" x-model="comentario" rows="3" placeholder="Obligatorio si rechazas"
                      class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            <p x-show="errorComentario" x-text="errorComentario" class="mt-1 text-sm text-red-600"></p>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" @click="decidir(false)"
                        class="rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-gray-50">Rechazar</button>
                <button type="button" @click="decidir(true)"
                        class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Aprobar y cerrar</button>
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
