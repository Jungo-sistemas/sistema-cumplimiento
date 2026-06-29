{{--
    Partial: Flow assignment modal
    Required variables: $usersByPosition, $positionLabels, $positionSortOrders, $flowDefinitions
    The parent must wrap this in an element with x-data="flowModal(...)" or include it
    inside the flowModal x-data div.
--}}

{{-- Hidden form submitted on confirm --}}
<form x-ref="flowForm" method="POST" :action="formAction">
    @csrf
    @method('PATCH')
    <input type="hidden" name="impact_level" :value="impactLevel">
    <template x-for="pos in positions" :key="pos.slug">
        <template x-for="u in (selected[pos.slug] || [])" :key="u.id">
            <input type="hidden"
                   :name="`users[${pos.slug}][]`"
                   :value="u.id">
        </template>
    </template>
</form>

{{-- Modal backdrop + card --}}
<div x-show="show"
     x-transition.opacity
     class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
     style="display:none;">

    <div @click.outside="show = false"
         class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">

        {{-- Header --}}
        <div class="bg-[#1A428A] px-6 py-4 flex items-center justify-between">
            <div>
                <h3 class="text-white font-semibold text-base">Confirmar flujo de aprobación</h3>
                <p class="text-blue-200 text-xs mt-0.5" x-text="regulationName"></p>
            </div>
            <button type="button" @click="show = false" class="text-blue-200 hover:text-white transition">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        {{-- Body --}}
        <div class="px-6 py-5">

            <template x-if="removing">
                <div class="text-sm text-gray-700">
                    <p>¿Confirmas que deseas <strong>eliminar el flujo de aprobación</strong> de este documento?</p>
                    <p class="mt-2 text-gray-500 text-xs">El flujo no se ha confirmado aún, por lo que puede eliminarse.</p>
                </div>
            </template>

            <template x-if="!removing">
                <div>
                    <p class="text-sm text-gray-700 mb-1">
                        Nivel seleccionado:
                        <span class="font-semibold text-[#1A428A]" x-text="levelLabel"></span>
                    </p>
                    <p class="text-xs text-gray-500 mb-4">
                        Asigna un responsable por cada puesto. Una vez confirmado, el flujo quedará activo.
                    </p>

                    <div class="space-y-5">
                        <template x-for="pos in positions" :key="pos.slug">
                            <div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <label class="text-xs font-semibold text-gray-700" x-text="pos.label"></label>
                                    <span x-show="!pos.requiresAll" class="text-xs text-gray-400 font-normal">(cualquiera basta)</span>
                                    <span class="ml-auto text-xs"
                                          :class="(selected[pos.slug]||[]).length > 0 ? 'text-green-600' : 'text-gray-400'"
                                          x-text="(selected[pos.slug]||[]).length + ' asignado(s)'"></span>
                                </div>

                                <div x-show="(selected[pos.slug]||[]).length > 0" class="flex flex-wrap gap-1.5 mb-2">
                                    <template x-for="u in (selected[pos.slug]||[])" :key="u.id">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-medium">
                                            <span x-text="u.name"></span>
                                            <button type="button" @click="removeUser(pos.slug, u.id)"
                                                    class="ml-0.5 text-blue-500 hover:text-blue-800">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>

                                <div class="relative">
                                    <input type="text"
                                           :placeholder="`Agregar persona a ${pos.label}…`"
                                           x-model="search[pos.slug]"
                                           @focus="open[pos.slug] = true"
                                           @input="open[pos.slug] = true"
                                           @keydown.escape="open[pos.slug] = false; search[pos.slug] = ''"
                                           class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 pr-8 focus:outline-none focus:border-[#1A428A] focus:ring-1 focus:ring-[#1A428A]">
                                    <button type="button"
                                            x-show="search[pos.slug]"
                                            @click="search[pos.slug] = ''; open[pos.slug] = false"
                                            class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>

                                    <div x-show="open[pos.slug] && filtered(pos.slug).length > 0"
                                         @click.outside="open[pos.slug] = false"
                                         class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-44 overflow-y-auto">
                                        <template x-for="u in filtered(pos.slug)" :key="u.id">
                                            <button type="button"
                                                    @click="selectUser(pos.slug, u)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center gap-2">
                                                <span class="h-6 w-6 rounded-full bg-[#1A428A] text-white text-xs flex items-center justify-center shrink-0"
                                                      x-text="u.name.charAt(0).toUpperCase()"></span>
                                                <div>
                                                    <div class="font-medium text-gray-800" x-text="u.name"></div>
                                                    <div class="text-xs text-gray-400" x-text="u.email"></div>
                                                </div>
                                            </button>
                                        </template>
                                        <div x-show="filtered(pos.slug).length === 0"
                                             class="px-3 py-2 text-sm text-gray-400 italic">Sin coincidencias</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Footer --}}
        <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
            <button type="button" @click="show = false"
                    class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 font-medium hover:bg-gray-100 transition">
                Cancelar
            </button>
            <button type="button"
                    @click="confirmFlow()"
                    :disabled="!canConfirm"
                    :class="canConfirm
                        ? 'bg-[#1A428A] hover:bg-[#15356d] text-white cursor-pointer'
                        : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                    class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                <span x-text="removing ? 'Sí, eliminar flujo' : 'Confirmar flujo'"></span>
            </button>
        </div>
    </div>
</div>
