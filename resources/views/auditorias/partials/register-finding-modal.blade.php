{{-- Ventana "Registrar hallazgo". Se incluye en Hallazgos y en Resultado.
     Cualquier botón de la página la abre con: @click="$dispatch('registrar-hallazgo')" --}}
@php
    // Datos de ejemplo: las auditorías finalizadas, que es de donde salen los hallazgos.
    $auditoriasHallazgo = [
        3 => ['nombre' => 'Manejo de residuos peligrosos · Asesorías Daval', 'responsable' => 'Ana Torres'],
        4 => ['nombre' => 'Control de documentos · Vigía',                  'responsable' => 'Luis Herrera'],
    ];
@endphp

<div x-data="{ abierto: false }"
     @registrar-hallazgo.window="abierto = true"
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

        <form @submit.prevent class="mt-5 space-y-5 rounded-2xl border border-gray-200 bg-gray-50 p-5">
            <div>
                <label for="hallazgo-auditoria" class="mb-2 block text-sm font-semibold text-gray-900">Auditoría</label>
                <select id="hallazgo-auditoria" name="auditoria"
                        class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <option value="">Selecciona</option>
                    @foreach($auditoriasHallazgo as $id => $a)
                        <option value="{{ $id }}">{{ $a['nombre'] }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="hallazgo-criterio" class="mb-2 block text-sm font-semibold text-gray-900">Criterio del checklist</label>
                {{-- En el siguiente paso, esta lista muestra solo los criterios de la auditoría elegida --}}
                <select id="hallazgo-criterio" name="criterio" disabled
                        class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand disabled:bg-gray-100 disabled:text-gray-400">
                    <option value="">Elige primero una auditoría</option>
                </select>
            </div>

            <div>
                <label for="hallazgo-titulo" class="mb-2 block text-sm font-semibold text-gray-900">Título del hallazgo</label>
                <input id="hallazgo-titulo" type="text" name="titulo" placeholder="Describe la desviación observada"
                       class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
            </div>

            <div>
                <label for="hallazgo-descripcion" class="mb-2 block text-sm font-semibold text-gray-900">Descripción y evidencia observada</label>
                <textarea id="hallazgo-descripcion" name="descripcion" rows="3"
                          placeholder="Explica qué encontraste, qué requisito no se cumple y en qué evidencia se sustenta."
                          class="w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand"></textarea>
            </div>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <label for="hallazgo-severidad" class="mb-2 block text-sm font-semibold text-gray-900">Severidad</label>
                    <select id="hallazgo-severidad" name="severidad"
                            class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                        <option value="">Selecciona</option>
                        <option value="critico">Crítico</option>
                        <option value="mayor">Mayor</option>
                        <option value="menor">Menor</option>
                    </select>
                </div>
                <div>
                    <label for="hallazgo-fecha" class="mb-2 block text-sm font-semibold text-gray-900">Fecha compromiso</label>
                    <input id="hallazgo-fecha" type="date" name="fecha_compromiso"
                           class="h-12 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                </div>
            </div>

            {{-- En el siguiente paso, aquí aparece la persona auditada de la auditoría elegida --}}
            <p class="text-sm text-gray-600">Responsable: <span class="text-gray-400">se muestra al elegir la auditoría</span></p>

            <div>
                <label for="hallazgo-soporte" class="mb-2 block text-sm font-semibold text-gray-900">Adjuntar soporte (opcional)</label>
                <input id="hallazgo-soporte" type="file" name="soportes[]" multiple
                       class="block w-full rounded-lg border border-gray-200 bg-white p-2 text-sm text-gray-600 file:mr-3 file:rounded-md file:border-0 file:bg-brand-50 file:px-3 file:py-1.5 file:text-sm file:font-semibold file:text-brand">
            </div>

            <button type="submit" class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Registrar y notificar hallazgo</button>
        </form>
    </div>
</div>
