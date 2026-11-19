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
            'evidencias' => [],
            'historial'  => ['11 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '16 sep 2026 · Laura Méndez: Envió la causa raíz y las acciones.'],
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
            'historial'  => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '18 sep 2026 · Jorge Salinas: Envió la causa raíz y las acciones.', '20 sep 2026 · Daniel Ruiz: Aprobó la causa raíz y las acciones.', '28 sep 2026 · Jorge Salinas: Subió la evidencia.'],
        ],
        'HAL-004' => [
            'clausula'   => '8.5',
            'causa'      => 'Faltaban etiquetas en el almacén.',
            'acciones'   => 'Comprar etiquetas.',
            'evidencias' => [],
            'historial'  => ['28 ago 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '02 sep 2026 · Ana Torres: Envió la causa raíz y las acciones.', '03 sep 2026 · Daniel Ruiz: Rechazó la causa raíz: describe el problema, no por qué faltaban las etiquetas.'],
        ],
        'HAL-005' => [
            'clausula'   => '7.2',
            'causa'      => 'El programa de capacitación no avisaba cuando una certificación estaba por vencer.',
            'acciones'   => 'Agregar alertas de vencimiento al programa y reprogramar la capacitación.',
            'evidencias' => [],
            'historial'  => ['14 sep 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '19 sep 2026 · Jorge Salinas: Envió la causa raíz y las acciones.', '22 sep 2026 · Daniel Ruiz: Aprobó la causa raíz y las acciones.'],
        ],
        'HAL-006' => [
            'clausula'   => '7.5.3',
            'causa'      => 'No se retiraban las copias impresas al publicar una nueva versión.',
            'acciones'   => 'Recoger las copias anteriores al liberar cada versión y dejarlo registrado.',
            'evidencias' => ['acta-retiro-copias.pdf'],
            'historial'  => ['20 ago 2026 · Daniel Ruiz: Hallazgo registrado y enviado al auditado.', '24 ago 2026 · Luis Herrera: Envió la causa raíz y las acciones.', '25 ago 2026 · Daniel Ruiz: Aprobó la causa raíz y las acciones.', '04 sep 2026 · Luis Herrera: Subió la evidencia.', '05 sep 2026 · Daniel Ruiz: Aprobó la evidencia. Hallazgo cerrado.'],
        ],
    ];
@endphp

<div x-data="{
        abierto: false,
        h: {},
        comentario: '',
        errorComentario: '',
        mensaje: '',
        auditor: @js(auth()->user()->name),
        // Hoy en el mismo formato que la fecha compromiso (2026-11-17), para compararlas.
        hoy: @js(today()->toDateString()),
        // Qué le toca revisar al auditor en este hallazgo: la causa raíz, la evidencia o nada (null).
        get revision() {
            if (this.h.seguimiento === 'Causa raíz por revisar') return 'causa';
            if (this.h.seguimiento === 'Evidencia por revisar') return 'evidencia';
            return null;
        },
        // Vencido: sigue abierto y su fecha compromiso ya pasó (igual que en la lista).
        get vencido() { return this.h.seguimiento !== 'Cerrado' && !! this.h.compromiso && this.h.compromiso < this.hoy },
        // La fecha compromiso escrita igual que en el seguimiento: 25 nov 2026.
        get fechaTexto() {
            if (! this.h.compromiso) return '';
            return new Date(this.h.compromiso + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
        },
        detalles: @js($detallesHallazgo),
        colores: @js(['severidad' => $severidades, 'seguimiento' => $seguimientos]),
        abrir(fila) {
            // Junta los datos de la fila con los de detalle del mismo código.
            this.h = { ...fila, ...(this.detalles[fila.codigo] ?? {}) };
            this.comentario = '';
            this.errorComentario = '';
            this.mensaje = '';
            this.abierto = true;
        },
        decidir(aprueba) {
            this.errorComentario = '';
            if (! aprueba && ! this.comentario.trim()) {
                this.errorComentario = 'Escribe el motivo del rechazo.';
                return;
            }

            // Se guarda antes de cambiar el seguimiento, porque revision depende de él.
            const revision = this.revision;
            const dia = new Date().toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' });
            let texto;
            if (! aprueba) {
                // Rechazar la causa raíz la regresa a escribirse; rechazar la evidencia solo pide otra.
                texto = (revision === 'causa' ? 'Rechazó la causa raíz: ' : 'Rechazó la evidencia: ') + this.comentario.trim();
                this.h.seguimiento = revision === 'causa' ? 'Rechazada' : 'Sin cargar';
            } else if (revision === 'causa') {
                texto = 'Aprobó la causa raíz y las acciones.';
                this.h.seguimiento = 'Sin cargar';
            } else {
                texto = 'Aprobó la evidencia. Hallazgo cerrado.';
                this.h.seguimiento = 'Cerrado';
            }

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

        {{-- Datos del hallazgo: se eligen al registrarlo y aquí solo se consultan (la clasificación es la etiqueta de arriba).
             Si la fecha compromiso pasa y el hallazgo sigue abierto, no se mueve la fecha: solo se marca Vencido. --}}
        <div class="grid grid-cols-1 gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-3">
            <div>
                <div class="text-sm font-semibold text-gray-900">Responsable</div>
                <p class="mt-1 text-[15px] text-gray-700" x-text="h.responsable"></p>
            </div>
            <div>
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    Fecha compromiso
                    <span x-show="vencido" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Vencido</span>
                </div>
                <p class="mt-1 text-[15px]" :class="vencido ? 'font-semibold text-red-700' : 'text-gray-700'" x-text="fechaTexto"></p>
            </div>
            <div>
                <div class="text-sm font-semibold text-gray-900">Cláusula</div>
                <p class="mt-1 text-[15px] text-gray-700" x-text="h.clausula"></p>
            </div>
        </div>

        {{-- Primera vuelta: causa raíz y acciones. Solo lectura, las escribe el responsable --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Causa raíz y acciones</h3>
            <div x-show="h.causa" class="mt-4 space-y-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Causa raíz</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.causa"></p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Acciones</div>
                    <p class="mt-1 text-[15px] text-gray-700" x-text="h.acciones"></p>
                </div>
                <p x-show="h.seguimiento === 'Rechazada'" class="text-sm font-semibold text-red-700">Rechazaste la causa raíz. El auditado debe corregirla y volver a enviarla.</p>
            </div>
            <p x-show="! h.causa" class="mt-2 text-[15px] text-gray-500">El auditado todavía no envía la causa raíz.</p>
        </div>

        {{-- Segunda vuelta: evidencia. El auditado la sube cuando el auditor aprueba la causa raíz --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Evidencia</h3>
            <ul x-show="(h.evidencias ?? []).length" class="mt-2 flex flex-wrap gap-2">
                <template x-for="archivo in (h.evidencias ?? [])" :key="archivo">
                    <li class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-brand" x-text="archivo"></li>
                </template>
            </ul>
            <p x-show="h.seguimiento === 'Sin cargar' && (h.evidencias ?? []).length" class="mt-2 text-sm font-semibold text-red-700">Rechazaste esta evidencia. El auditado debe subir otra.</p>
            <p x-show="! (h.evidencias ?? []).length" class="mt-2 text-[15px] text-gray-500">Sin evidencia cargada.</p>
        </div>

        {{-- Decisión del auditor: solo cuando le toca revisar la causa raíz o la evidencia --}}
        <div x-show="revision" class="rounded-2xl border-2 border-brand-100 bg-brand-50 p-5">
            <h3 class="font-semibold text-gray-900" x-text="revision === 'causa' ? 'Decisión del auditor: causa raíz y acciones' : 'Decisión del auditor: evidencia'"></h3>
            <p class="mt-1 text-sm text-gray-600" x-text="revision === 'causa' ? 'Si apruebas, el auditado podrá subir la evidencia. Si rechazas, debe corregir la causa raíz.' : 'Si apruebas, el hallazgo se cierra. Si rechazas, el auditado debe subir otra evidencia.'"></p>

            <label for="detalle-comentario" class="mb-2 mt-4 block text-sm font-semibold text-gray-900">Comentarios</label>
            <textarea id="detalle-comentario" x-model="comentario" rows="3" placeholder="Obligatorio si rechazas"
                      class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            <p x-show="errorComentario" x-text="errorComentario" class="mt-1 text-sm text-red-600"></p>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="button" @click="decidir(false)"
                        class="rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-gray-50">Rechazar</button>
                <button type="button" @click="decidir(true)"
                        class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark"
                        x-text="revision === 'causa' ? 'Aprobar causa raíz y acciones' : 'Aprobar evidencia y cerrar'"></button>
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
