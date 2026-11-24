{{-- Ventana con el detalle de un hallazgo (Hallazgos y Resultado). Cada fila la abre mandando sus
     datos (AuditorController::findingPayload): @click="$dispatch('abrir-hallazgo', @js($h))".
     Cuando le toca al auditor, aquí aprueba o devuelve la causa raíz, o verifica / rechaza la evidencia.
     Colores: AuditorController::SEVERITY_COLORS y TRACKING_COLORS. --}}
<div x-data="{
        abierto: false,
        h: {},
        comentario: '',
        errorComentario: '',
        colores: @js(['severidad' => \App\Http\Controllers\AuditorController::SEVERITY_COLORS, 'seguimiento' => \App\Http\Controllers\AuditorController::TRACKING_COLORS]),
        abrir(fila) {
            this.h = fila;
            this.comentario = '';
            this.errorComentario = '';
            this.abierto = true;
        },
        decidir(evento, aprueba) {
            this.errorComentario = '';
            if (! aprueba && ! this.comentario.trim()) {
                evento.preventDefault();
                this.errorComentario = 'Escribe el motivo del rechazo.';
            }
        },
     }"
     @abrir-hallazgo.window="abrir($event.detail)"
     @keydown.escape.window="abierto = false"
     x-show="abierto" x-cloak
     class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-gray-900/50 p-4 backdrop-blur-sm sm:py-10">

    <div @click.outside="abierto = false" x-show="abierto" x-transition
         class="w-full max-w-2xl space-y-5 rounded-2xl bg-white p-6 shadow-xl">

        <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-4">
            <div>
                <h2 class="text-xl font-bold text-brand-ink" x-text="h.titulo"></h2>
                <p class="mt-1 text-sm text-gray-500" x-text="h.codigo + ' · ' + h.proceso"></p>
            </div>
            <button type="button" @click="abierto = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-600 hover:bg-gray-50">Cerrar</button>
        </div>
        <div class="flex flex-wrap gap-2">
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="colores.severidad[h.severidad]" x-text="h.severidad"></span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="colores.seguimiento[h.seguimiento]" x-text="h.seguimiento"></span>
            <span class="rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-700" x-text="h.empresa"></span>
        </div>

        <div class="grid grid-cols-1 gap-4 rounded-2xl border border-gray-200 p-5 sm:grid-cols-2">
            <div>
                <div class="text-sm font-semibold text-gray-900">Responsable</div>
                <p class="mt-1 text-[15px] text-gray-700" x-text="h.responsable"></p>
            </div>
            <div>
                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                    Plazo de la etapa
                    <span x-show="h.vencido" class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Vencido</span>
                </div>
                <p class="mt-1 text-[15px]" :class="h.vencido ? 'font-semibold text-red-700' : 'text-gray-700'" x-text="h.plazo ?? '—'"></p>
            </div>
            <div class="sm:col-span-2" x-show="h.criterio">
                <div class="text-sm font-semibold text-gray-900">Criterio del checklist</div>
                <p class="mt-1 text-[15px] text-gray-700" x-text="h.criterio"></p>
            </div>
            <div class="sm:col-span-2">
                <div class="text-sm font-semibold text-gray-900">Lo que encontró el auditor</div>
                <p class="mt-1 whitespace-pre-line text-[15px] text-gray-700" x-text="h.descripcion"></p>
            </div>
        </div>

        {{-- Primera vuelta: causa raíz y acciones (las escribe el responsable) --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Causa raíz y acciones</h3>
            <div x-show="h.causa" class="mt-4 space-y-3">
                <div>
                    <div class="text-sm font-semibold text-gray-900">Causa raíz</div>
                    <p class="mt-1 whitespace-pre-line text-[15px] text-gray-700" x-text="h.causa"></p>
                </div>
                <div>
                    <div class="text-sm font-semibold text-gray-900">Acciones</div>
                    <p class="mt-1 whitespace-pre-line text-[15px] text-gray-700" x-text="h.acciones"></p>
                </div>
                <ul x-show="(h.causaArchivos ?? []).length" class="flex flex-wrap gap-2">
                    <template x-for="archivo in (h.causaArchivos ?? [])" :key="archivo.url">
                        <li><a :href="archivo.url" class="inline-flex rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-brand hover:bg-gray-50" x-text="archivo.name"></a></li>
                    </template>
                </ul>
                <p x-show="h.status === 'causa_rechazada'" class="text-sm font-semibold text-red-700">Devolviste la causa raíz. El auditado debe corregirla y volver a enviarla.</p>
            </div>
            <p x-show="! h.causa" class="mt-2 text-[15px] text-gray-500">El auditado todavía no envía la causa raíz.</p>
        </div>

        {{-- Segunda vuelta: evidencia del seguimiento --}}
        <div class="rounded-2xl border border-gray-200 p-5">
            <h3 class="font-semibold text-gray-900">Evidencia del seguimiento</h3>
            <p x-show="h.evidenciaComentario" class="mt-2 text-[15px] text-gray-700" x-text="h.evidenciaComentario"></p>
            <ul x-show="(h.evidencias ?? []).length" class="mt-2 flex flex-wrap gap-2">
                <template x-for="archivo in (h.evidencias ?? [])" :key="archivo.url">
                    <li><a :href="archivo.url" class="inline-flex rounded-lg border border-gray-200 px-3 py-1.5 text-sm font-semibold text-brand hover:bg-gray-50" x-text="archivo.name"></a></li>
                </template>
            </ul>
            <p x-show="h.status === 'evidencia_rechazada'" class="mt-2 text-sm font-semibold text-red-700">Rechazaste esta evidencia. El auditado debe subir otra.</p>
            <p x-show="! (h.evidencias ?? []).length" class="mt-2 text-[15px] text-gray-500">Sin evidencia cargada.</p>
        </div>

        {{-- Decisión del auditor: solo cuando le toca revisar --}}
        <p x-show="h.revision && ! h.puedeRevisar" class="rounded-2xl border border-brand-100 bg-brand-50 p-4 text-sm text-brand-ink">
            Está en revisión del auditor asignado (<span x-text="h.auditor"></span>).
        </p>
        <form x-show="h.revision && h.puedeRevisar" method="POST" :action="h.reviewUrl" class="rounded-2xl border-2 border-brand-100 bg-brand-50 p-5">
            @csrf
            <h3 class="font-semibold text-gray-900" x-text="h.revision === 'causa' ? 'Decisión del auditor: causa raíz y acciones' : 'Decisión del auditor: evidencia'"></h3>
            <p class="mt-1 text-sm text-gray-600" x-text="h.revision === 'causa' ? 'Si apruebas, el auditado podrá subir la evidencia del seguimiento. Si devuelves, debe corregir la causa raíz.' : 'Si apruebas, el hallazgo se cierra. Si rechazas, el auditado debe subir otra evidencia.'"></p>

            <label for="detalle-comentario" class="mb-2 mt-4 block text-sm font-semibold text-gray-900">Comentarios</label>
            <textarea id="detalle-comentario" name="comment" x-model="comentario" rows="3" placeholder="Obligatorio si rechazas"
                      class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            <p x-show="errorComentario" x-text="errorComentario" class="mt-1 text-sm text-red-600"></p>

            <div class="mt-4 flex flex-wrap gap-3">
                <button type="submit" name="decision" value="rechazar" @click="decidir($event, false)"
                        class="rounded-lg border-2 border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-brand-ink hover:bg-gray-50"
                        x-text="h.revision === 'causa' ? 'Devolver' : 'Rechazar'"></button>
                <button type="submit" name="decision" value="aprobar"
                        class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark"
                        x-text="h.revision === 'causa' ? 'Aprobar causa raíz y acciones' : 'Aprobar evidencia y cerrar'"></button>
            </div>
        </form>

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
