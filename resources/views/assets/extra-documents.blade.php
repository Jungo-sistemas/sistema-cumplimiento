{{-- resources/views/assets/extra-documents.blade.php --}}

<x-layouts.vigia
    :title="'Info extra: ' . $asset->name"
    :nav-context="$navContext"
>
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset->company_id)])) }}"
           class="shrink-0 text-gray-600 hover:underline">
            Activos y Actividades
        </a>

        <span class="shrink-0 text-gray-400">›</span>

        <a href="{{ route('assets.show', $asset) }}" class="min-w-0 text-gray-600 hover:underline">
            <x-truncate max="max-w-[160px]">{{ $asset->name }}</x-truncate>
        </a>

        <span class="shrink-0 text-gray-400">›</span>

        <span class="shrink-0 text-gray-700 font-medium">Info extra</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    Info extra
                </h1>

                <div class="text-sm text-gray-500">
                    Activo:
                    <span class="font-semibold text-gray-700">
                        {{ $asset->name }}
                    </span>

                    @if(auth()->user()->hasGroupScope() && $asset->company)
                        · Empresa:
                        <span class="font-semibold text-gray-700">
                            {{ $asset->company->name }}
                        </span>
                    @endif
                </div>

                <p class="text-sm text-gray-500 max-w-2xl">
                    Documentación adicional que no forma parte del expediente normativo y no afecta el estado de cumplimiento del activo, pero es útil conservar.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('assets.show', $asset) }}"
                   class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                    Volver
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="mt-6 space-y-3">
            @if(session('success') || session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
                    {{ session('success') ?? session('status') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Subir documentación extra --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b">
                    <div class="font-semibold text-[#1A428A]">
                        Subir documentación extra
                    </div>

                    <div class="text-sm text-gray-500">
                        Puedes subir varios archivos a la vez. Cada archivo puede tener fecha de emisión, de vencimiento, ambas o ninguna.
                    </div>
                </div>

                <div class="p-5">
                    @if(!(auth()->user()->isAdmin() || auth()->user()->isOperative()))
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            No tienes permisos para subir documentación extra.
                        </div>
                    @elseif($assetInactive)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Este activo está desactivado. Actívalo para subir documentación extra.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('assets.extra-documents.store', $asset) }}"
                              enctype="multipart/form-data"
                              x-data="{
                                  mode: '{{ old('date_mode', 'no_dates') }}',
                                  slots: [1],
                                  nextId: 2,
                                  addSlot() { if (this.slots.length < 10) this.slots.push(this.nextId++); },
                                  removeSlot(id) { if (this.slots.length > 1) this.slots = this.slots.filter(s => s !== id); }
                              }"
                              class="space-y-5">
                            @csrf
                            <input type="hidden" name="date_mode" :value="mode">

                            {{-- Tipo de fecha --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Fechas del documento</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" @click="mode = 'no_dates'"
                                            :class="mode === 'no_dates'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Sin fechas<br>
                                        <span class="font-normal opacity-75">solo archivo</span>
                                    </button>
                                    <button type="button" @click="mode = 'no_renewal'"
                                            :class="mode === 'no_renewal'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Solo emisión<br>
                                        <span class="font-normal opacity-75">sin vencimiento</span>
                                    </button>
                                    <button type="button" @click="mode = 'renewal'"
                                            :class="mode === 'renewal'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Emisión y vencimiento<br>
                                        <span class="font-normal opacity-75">ambas fechas</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Fechas (condicional según modo) --}}
                            <div x-show="mode !== 'no_dates'"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de emisión</label>
                                    <input type="date"
                                           name="issued_at"
                                           value="{{ old('issued_at') }}"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    @error('issued_at')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div x-show="mode === 'renewal'"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de vencimiento</label>
                                    <input type="date"
                                           name="expires_at"
                                           value="{{ old('expires_at') }}"
                                           :required="mode === 'renewal'"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    @error('expires_at')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            {{-- Notas --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Notas <span class="font-normal text-gray-400">(opcional)</span></label>
                                <textarea name="notes" rows="2"
                                          class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">{{ old('notes') }}</textarea>
                                @error('notes')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Archivos (hasta 10) --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">
                                    Archivos
                                    <span class="font-normal text-gray-400">(máx. 10)</span>
                                </label>

                                <div class="space-y-2">
                                    <template x-for="(slotId, index) in slots" :key="slotId">
                                        <div class="flex items-center gap-2">
                                            <input type="file"
                                                   name="files[]"
                                                   accept=".pdf,.jpg,.jpeg,.png,.zip"
                                                   required
                                                   class="block flex-1 rounded-md border border-gray-300 text-sm text-gray-700
                                                          file:mr-3 file:py-1.5 file:px-3 file:rounded file:border-0
                                                          file:text-sm file:font-medium file:bg-[#1A428A] file:text-white
                                                          hover:file:bg-[#15356d] focus:outline-none">
                                            <button type="button"
                                                    @click="removeSlot(slotId)"
                                                    x-show="slots.length > 1"
                                                    class="shrink-0 w-8 h-8 flex items-center justify-center rounded-md border border-gray-300 text-gray-400 hover:text-red-600 hover:border-red-300 transition">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </template>
                                </div>

                                <button type="button"
                                        @click="addSlot()"
                                        x-show="slots.length < 10"
                                        class="mt-2 flex items-center gap-1.5 text-sm text-[#1A428A] hover:underline font-medium">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
                                    </svg>
                                    Agregar otro archivo
                                </button>

                                @error('files')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                                @error('files.*')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                                <p class="text-xs text-gray-500 mt-1.5">PDF, JPG, PNG o ZIP · Máximo 50 MB por archivo.</p>
                            </div>

                            <button type="submit"
                                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                                Subir documentación
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Listado --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-[#1A428A]">Documentos ({{ $documents->count() }})</div>
                        <div class="text-sm text-gray-500">
                            Documentación extra registrada para este activo.
                        </div>
                    </div>
                </div>

                <div class="p-5">
                    @if($documents->isNotEmpty())
                        <div class="space-y-3 max-h-[560px] overflow-y-auto pr-1">
                            @foreach($documents as $doc)
                                <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <div class="font-semibold text-gray-900 truncate">
                                            {{ $doc->original_name ?? basename($doc->file_path) }}
                                        </div>

                                        <div class="text-sm text-gray-500 mt-1">
                                            <span class="block">Subido por: {{ $doc->uploader?->name ?? '—' }}</span>
                                            <span class="block">Fecha de carga: {{ optional($doc->created_at)->format('Y-m-d H:i') }}</span>

                                            @if($doc->issued_at)
                                                <span class="block">Emisión: {{ $doc->issued_at->format('Y-m-d') }}</span>
                                            @endif

                                            @if($doc->expires_at)
                                                <span class="block">Vigente hasta: {{ $doc->expires_at->format('Y-m-d') }}</span>
                                            @endif

                                            @if($doc->notes)
                                                <span class="block mt-1 text-gray-600 italic">{{ $doc->notes }}</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <a href="{{ route('assets.extra-documents.preview', [$asset, $doc]) }}"
                                           target="_blank"
                                           class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                            Ver
                                        </a>

                                        <a href="{{ route('assets.extra-documents.download', [$asset, $doc]) }}"
                                           class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                            Descargar
                                        </a>

                                        @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                            <form method="POST"
                                                  action="{{ route('assets.extra-documents.destroy', [$asset, $doc]) }}"
                                                  onsubmit="return confirm('¿Eliminar este documento extra?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                        class="px-3 py-2 rounded-md font-semibold text-sm bg-[#DB0000] text-white hover:bg-red-700">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Aún no hay documentación extra registrada.
                        </div>
                    @endif
                </div>
            </div>

        </div>
    </div>
</x-layouts.vigia>
