<x-layouts.vigia title="Cargar Proceso">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
        <span class="mx-2 text-gray-400">/</span>
        @if($selectedCompanyId)
            <a href="{{ route('processes.index', ['company_id' => $selectedCompanyId]) }}" class="text-gray-500 hover:underline">
                {{ $companies->firstWhere('id', $selectedCompanyId)?->name ?? 'Empresa' }}
            </a>
            <span class="mx-2 text-gray-400">/</span>
        @endif
        <span class="text-gray-700 font-medium">Cargar Proceso</span>
    </x-slot>

    <div x-data="{ fileName: '' }">

        {{-- ENCABEZADO --}}
        <div class="mb-6">
            <h1 class="text-2xl font-semibold text-[#1A428A]">Cargar proceso existente</h1>
            <p class="text-sm text-gray-500 mt-1">
                Para documentos que ya fueron aprobados fuera del sistema. Se registrarán directamente como
                <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-green-100 text-green-700 text-xs font-medium">aprobados</span>.
            </p>
        </div>

        @if($errors->any())
            <div class="mb-5 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ route('processes.storeCargar') }}"
              enctype="multipart/form-data">
            @csrf

            {{-- DOS COLUMNAS --}}
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">

                {{-- ── COLUMNA IZQUIERDA: Identificación ── --}}
                <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b bg-[#1A428A]">
                        <h2 class="text-sm font-semibold text-white">Identificación del documento</h2>
                    </div>
                    <div class="p-5 space-y-4">

                        {{-- Empresa --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Empresa</label>
                            <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ $companies->firstWhere('id', $selectedCompanyId)?->name ?? auth()->user()->company?->name ?? '—' }}
                            </div>
                            <input type="hidden" name="company_id" value="{{ $selectedCompanyId ?? auth()->user()->company_id }}">
                        </div>

                        {{-- Tipo de proceso + Tipo de documento en fila --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Tipo de proceso <span class="text-red-500">*</span>
                                </label>
                                <select name="process_type_id"
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('process_type_id') border-red-400 @enderror">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($processTypes as $pt)
                                        <option value="{{ $pt->id }}" {{ old('process_type_id') == $pt->id ? 'selected' : '' }}>
                                            {{ $pt->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('process_type_id')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Tipo de documento <span class="text-red-500">*</span>
                                </label>
                                <select name="document_type"
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('document_type') border-red-400 @enderror">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($documentTypes as $dt)
                                        <option value="{{ $dt }}" {{ old('document_type') === $dt ? 'selected' : '' }}>
                                            {{ $dt }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('document_type')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Nombre --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                Nombre del documento <span class="text-red-500">*</span>
                            </label>
                            <input type="text"
                                   name="nombre"
                                   value="{{ old('nombre') }}"
                                   placeholder="Ej: Procedimiento de Alta de Proveedores"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('nombre') border-red-400 @enderror">
                            @error('nombre')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Código --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                Código <span class="font-normal normal-case text-gray-400">(opcional)</span>
                            </label>
                            <input type="text"
                                   name="codigo"
                                   value="{{ old('codigo') }}"
                                   placeholder="Ej: P-COM-001"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                        </div>

                        {{-- Elaborado + Aprobado en fila --}}
                        <div class="grid grid-cols-1 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Elaborado por <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="quien_elabora"
                                       value="{{ old('quien_elabora') }}"
                                       placeholder="Ej: Juan Pérez — Jefe de Compras"
                                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('quien_elabora') border-red-400 @enderror">
                                @error('quien_elabora')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Aprobado por <span class="text-red-500">*</span>
                                </label>
                                <input type="text"
                                       name="quien_aprueba"
                                       value="{{ old('quien_aprueba') }}"
                                       placeholder="Ej: María López — Directora de Operaciones"
                                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('quien_aprueba') border-red-400 @enderror">
                                @error('quien_aprueba')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                    </div>
                </div>

                {{-- ── COLUMNA DERECHA: Archivo y vigencia ── --}}
                <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b bg-[#1A428A]">
                        <h2 class="text-sm font-semibold text-white">Archivo y vigencia</h2>
                        <p class="text-blue-200 text-xs mt-0.5">Sube el PDF y define las fechas de vigencia.</p>
                    </div>
                    <div class="p-5 space-y-4">

                        {{-- Zona de carga de archivo --}}
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                Archivo (PDF o imagen) <span class="text-red-500">*</span>
                            </label>
                            <label class="flex flex-col items-center justify-center w-full h-32 border-2 border-dashed rounded-lg cursor-pointer transition
                                          border-gray-300 bg-gray-50 hover:bg-blue-50 hover:border-[#1A428A]"
                                   :class="fileName ? 'border-[#1A428A] bg-blue-50' : ''">
                                <div class="flex flex-col items-center gap-1.5 text-center px-4">
                                    <template x-if="!fileName">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-gray-400" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                                        </svg>
                                    </template>
                                    <template x-if="fileName">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7 text-[#1A428A]" fill="none"
                                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round"
                                                  d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                        </svg>
                                    </template>
                                    <span class="text-sm font-medium"
                                          :class="fileName ? 'text-[#1A428A]' : 'text-gray-500'"
                                          x-text="fileName || 'Haz clic para seleccionar archivo'"></span>
                                    <span x-show="!fileName" class="text-xs text-gray-400">PDF, JPG o PNG — máx. 10 MB</span>
                                </div>
                                <input type="file"
                                       name="file"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       class="sr-only"
                                       @change="fileName = $event.target.files[0]?.name ?? ''">
                            </label>
                            @error('file')
                                <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                            @enderror
                        </div>

                        {{-- Fechas --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Fecha de emisión
                                </label>
                                <input type="date"
                                       name="issued_at"
                                       value="{{ old('issued_at') }}"
                                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('issued_at') border-red-400 @enderror">
                                @error('issued_at')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                                    Fecha de vencimiento <span class="text-red-500">*</span>
                                </label>
                                <input type="date"
                                       name="valid_until"
                                       value="{{ old('valid_until') }}"
                                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('valid_until') border-red-400 @enderror">
                                @error('valid_until')
                                    <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        {{-- Nota informativa --}}
                        <div class="rounded-lg bg-blue-50 border border-blue-100 px-4 py-3 text-xs text-blue-700 flex gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 mt-0.5 text-blue-400" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>El documento quedará registrado como <strong>versión 1</strong> y se marcará como vigente de forma inmediata.</span>
                        </div>

                    </div>
                </div>

            </div>{{-- fin grid --}}

            {{-- ── ACCIONES ── --}}
            <div class="mt-6 flex items-center justify-end gap-3">
                <a href="{{ route('processes.index', $selectedCompanyId ? ['company_id' => $selectedCompanyId] : []) }}"
                   class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-6 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    Guardar procedimiento
                </button>
            </div>

        </form>
    </div>

</x-layouts.vigia>
