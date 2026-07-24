@php
    $isEdit = ($draft['mode'] ?? 'create') === 'edit';
@endphp

<x-layouts.vigia :title="$isEdit ? 'Vista previa — Editar Proceso' : 'Vista previa — Nuevo Proceso'">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
        <span class="mx-2 text-gray-400">/</span>
        <a href="{{ route('processes.index', ['company_id' => $company->id]) }}" class="text-gray-500 hover:underline">
            {{ $company->name }}
        </a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">Vista previa</span>
    </x-slot>

    <div x-data="previewApp()" class="pb-16">

        {{-- ===== OVERLAY: PROCESANDO ===== --}}
        <div x-show="submitting" style="display: none;"
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg px-8 py-6 shadow-xl text-center max-w-sm">
                <svg class="animate-spin h-8 w-8 text-[#1A428A] mx-auto mb-3" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="text-gray-700 font-medium" x-text="submittingMessage"></p>
                <p class="text-gray-500 text-sm mt-1">No cierres esta ventana.</p>
            </div>
        </div>

        @if($errors->first('ai'))
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                {{ $errors->first('ai') }}
            </div>
        @endif

        @if($errors->first('feedback'))
            <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
                {{ $errors->first('feedback') }}
            </div>
        @endif

        <div class="flex items-center justify-between mb-6 flex-wrap gap-2">
            <div>
                <h1 class="text-2xl font-semibold text-[#1A428A]">Vista previa del procedimiento</h1>
                <p class="text-sm text-gray-500">
                    {{ $draft['meta']['nombre'] }}
                    @if(($draft['revisions'] ?? 0) > 0)
                        <span class="ml-2 inline-block px-2 py-0.5 rounded-full bg-blue-50 text-[#1A428A] text-xs font-semibold">
                            Revisión {{ $draft['revisions'] }}
                        </span>
                    @endif
                </p>
            </div>
            <form method="POST" action="{{ route('processes.preview.cancel') }}">
                @csrf
                <button type="submit" class="text-sm text-gray-500 hover:text-red-600 hover:underline">
                    Cancelar y volver a empezar
                </button>
            </form>
        </div>

        <div class="flex gap-6 items-start flex-col lg:flex-row">

            {{-- ===== DOCUMENTO (dividido en hojas tamaño carta) ===== --}}
            <div class="flex-1 min-w-0 overflow-x-auto">
                <div id="doc-source" style="display: none;">
                    {!! $draft['ai']['documento_html'] !!}
                </div>
                <template id="doc-header-template">
                    @include('processes.partials.header-table', ['meta' => [
                        'nombre'                   => $draft['meta']['nombre'],
                        'codigo'                   => $draft['meta']['codigo'] ? \Illuminate\Support\Str::upper($draft['meta']['codigo']) : null,
                        'version'                  => $headerVersion,
                        'quien_elabora'            => $draft['meta']['quien_elabora'],
                        'quien_aprueba'            => $draft['meta']['quien_aprueba'],
                        'fecha_vigencia_formatted' => $draft['meta']['fecha_vigencia']
                            ? \Carbon\Carbon::parse($draft['meta']['fecha_vigencia'])->format('d/m/Y')
                            : null,
                    ]])
                </template>
                <div id="doc-pages"></div>
            </div>

            {{-- ===== ACCIONES ===== --}}
            <aside class="w-full lg:w-80 shrink-0 space-y-4 lg:sticky lg:top-4">
                <div class="bg-white border rounded-xl shadow-sm p-4">
                    <h2 class="font-semibold text-gray-800 mb-1">¿El documento quedó bien?</h2>
                    <p class="text-sm text-gray-500 mb-4">
                        @if($isEdit)
                            Léelo completo. Si tiene sentido y está listo, acéptalo para actualizar el
                            procedimiento y guardar una nueva versión.
                        @else
                            Léelo completo. Si tiene sentido y está listo, acéptalo para crear el procedimiento
                            y su primera versión.
                        @endif
                    </p>
                    <form method="POST" action="{{ route('processes.preview.confirm') }}"
                          @submit="submitting = true; submittingMessage = '{{ $isEdit ? 'Actualizando el documento…' : 'Creando el documento…' }}'">
                        @csrf
                        <button type="submit"
                                class="w-full px-4 py-2.5 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                            {{ $isEdit ? 'Aceptar y actualizar documento' : 'Aceptar y crear documento' }}
                        </button>
                    </form>
                </div>

                <div class="bg-white border rounded-xl shadow-sm p-4">
                    <h3 class="font-semibold text-gray-800 mb-1 text-sm">¿Falta algo o hay que ajustar?</h3>
                    <p class="text-xs text-gray-500 mb-3">
                        Describe el cambio puntual. La IA solo aplicará lo que pidas aquí; el resto del documento
                        se mantiene igual.
                    </p>
                    <form method="POST" action="{{ route('processes.preview.revise') }}"
                          @submit="submitting = true; submittingMessage = 'Aplicando los cambios…'">
                        @csrf
                        <textarea name="feedback" rows="4" required maxlength="2000"
                                  class="w-full border rounded-md px-3 py-2 text-sm mb-2 focus:outline-none focus:ring-2 focus:ring-[#1A428A]/30 @error('feedback') border-red-400 @enderror"
                                  placeholder="Ej: en el bloque de actividades, agrega un paso de validación con el cliente antes de cerrar el caso.">{{ old('feedback') }}</textarea>
                        <button type="submit"
                                class="w-full px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] text-sm font-semibold hover:bg-blue-50">
                            Solicitar cambios
                        </button>
                    </form>
                </div>
            </aside>
        </div>
    </div>

    <link rel="stylesheet" href="{{ asset('css/document-pagination.css') }}">

    <script>
        function previewApp() {
            return {
                submitting: false,
                submittingMessage: 'Procesando…',
            };
        }
    </script>
    <script src="{{ asset('js/document-pagination.js') }}"></script>

</x-layouts.vigia>
