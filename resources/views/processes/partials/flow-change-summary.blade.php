{{--
    Botón opcional para adjuntar la síntesis de cambios al establecer el flujo. Se usa dentro de
    los modales de flujo (flowModal en index, reviewFlowModal en show), que deben tener un
    <input type="file" name="change_summary" x-ref="summaryInput"> dentro de su formulario y las
    propiedades summaryName / summaryError / clearSummary().
--}}
<div class="mb-5 rounded-lg border border-dashed border-gray-300 bg-gray-50 px-4 py-3">
    <div class="flex items-center justify-between gap-3">
        <div class="min-w-0">
            <p class="text-xs font-semibold text-gray-700">
                Síntesis de cambios <span class="font-normal text-gray-400">(opcional)</span>
            </p>
            <p class="text-xs text-gray-500 truncate"
               x-text="summaryName || 'Documento que verán los aprobadores en lugar de revisar todo el proceso.'"></p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" x-show="summaryName" @click="clearSummary()"
                    class="text-xs text-gray-500 hover:text-red-600">
                Quitar
            </button>
            <button type="button" @click="$refs.summaryInput.click()"
                    class="px-3 py-1.5 rounded-lg bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d]"
                    x-text="summaryName ? 'Cambiar' : 'Adjuntar síntesis'"></button>
        </div>
    </div>
    <p x-show="summaryError" x-text="summaryError" class="text-xs text-red-600 mt-1"></p>
    <p class="text-[11px] text-gray-400 mt-1.5">
        PDF, Word, Excel o imagen, máx. 20 MB. Reemplaza la síntesis anterior del proceso, si había.
    </p>
</div>
