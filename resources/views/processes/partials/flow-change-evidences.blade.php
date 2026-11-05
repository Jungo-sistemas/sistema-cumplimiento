{{--
    Evidencias de apoyo de la síntesis (opcional, máx. Regulation::MAX_CHANGE_EVIDENCES): por ejemplo,
    capturas de quienes ya aprobaron para que el flujo nuevo solo lleve a los que faltan.

    Los inputs se ligan al formulario con el atributo form="…", así que este bloque puede vivir
    dentro de la tarjeta del modal aunque el <form> esté en otro lado. Requiere
    processes.partials.change-evidence-script en la página.

    Parámetros: $formId (id del <form>), $existing (expresión JS con [{id, name}] de las que ya tiene).
    En el listado, el mismo modal sirve para varios procesos: se reinicia con open-flow-modal.
--}}
<div x-data="changeEvidencePicker({{ $existing ?? '[]' }}, {{ \App\Models\Regulation::MAX_CHANGE_EVIDENCES }})"
     @open-flow-modal.window="reset($event.detail.evidences || [])"
     class="mb-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-700">
                Evidencias de apoyo <span class="font-normal text-gray-400">(opcional, máx. {{ \App\Models\Regulation::MAX_CHANGE_EVIDENCES }})</span>
            </p>
            <p class="text-xs text-gray-500">Por ejemplo, capturas de quienes ya aprobaron.</p>
        </div>
        <button type="button" x-show="remaining > 0" @click="$refs.picker.click()"
                class="shrink-0 px-3 py-1.5 rounded-lg border border-brand text-brand text-xs font-semibold hover:bg-brand-50">
            + Agregar evidencia
        </button>
    </div>

    <input type="file" x-ref="picker" class="hidden" accept=".pdf,.jpg,.jpeg,.png" multiple
           @click.stop @change="pick($event)">
    <input type="file" x-ref="files" name="change_evidences[]" form="{{ $formId }}" class="hidden" multiple>
    <template x-for="id in removed" :key="id">
        <input type="hidden" name="remove_evidences[]" form="{{ $formId }}" :value="id">
    </template>

    <ul x-show="existing.length || names.length" class="mt-2 space-y-1">
        <template x-for="ev in existing" :key="'e' + ev.id">
            <li class="flex items-center justify-between gap-2 text-xs">
                <span class="truncate" :class="removed.includes(ev.id) ? 'line-through text-gray-400' : 'text-gray-700'" x-text="ev.name"></span>
                <button type="button" @click="toggleExisting(ev.id)"
                        class="shrink-0 text-gray-500 hover:text-red-600"
                        x-text="removed.includes(ev.id) ? 'Conservar' : 'Quitar'"></button>
            </li>
        </template>
        <template x-for="(name, i) in names" :key="'n' + i + name">
            <li class="flex items-center justify-between gap-2 text-xs">
                <span class="truncate text-gray-700">
                    <span x-text="name"></span>
                    <span class="ml-1 text-green-600">nueva</span>
                </span>
                <button type="button" @click="removeNew(i)" class="shrink-0 text-gray-500 hover:text-red-600">Quitar</button>
            </li>
        </template>
    </ul>

    <p x-show="error" x-text="error" class="text-xs text-red-600 mt-1"></p>
    <p class="text-[11px] text-gray-400 mt-1.5">Imagen (JPG/PNG) o PDF, máx. 20 MB cada una.</p>
</div>
