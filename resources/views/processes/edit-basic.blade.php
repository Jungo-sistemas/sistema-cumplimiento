<x-layouts.vigia :title="'Editar info básica · ' . $regulation->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
        <span class="mx-2 text-gray-400">/</span>
        <a href="{{ route('processes.show', $regulation) }}" class="text-gray-500 hover:underline">
            <x-truncate max="max-w-[260px]">{{ $regulation->name }}</x-truncate>
        </a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">Editar info básica</span>
    </x-slot>

    @php
        $d = $regulation->details ?? [];
    @endphp

    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Editar información básica</h1>
        <p class="text-sm text-gray-500 mt-1">
            Identificación del documento: tipo, nombre, código y responsables.
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

    <form method="POST" action="{{ route('processes.updateBasic', $regulation) }}">
        @csrf
        @method('PUT')

        <div class="bg-white border rounded-xl shadow-sm overflow-hidden max-w-2xl">
            <div class="px-5 py-3.5 border-b bg-[#1A428A]">
                <h2 class="text-sm font-semibold text-white">Identificación del documento</h2>
            </div>

            <div class="p-5 space-y-4">

                {{-- Empresa (solo lectura) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Empresa</label>
                    <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        {{ $regulation->company->name ?? '—' }}
                    </div>
                </div>

                {{-- Tipo de proceso + Tipo de documento --}}
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                            Tipo de proceso <span class="text-red-500">*</span>
                        </label>
                        <select name="process_type_id"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('process_type_id') border-red-400 @enderror">
                            <option value="">— Seleccionar —</option>
                            @foreach($processTypes as $pt)
                                <option value="{{ $pt->id }}"
                                        {{ old('process_type_id', $regulation->process_type_id) == $pt->id ? 'selected' : '' }}>
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
                                <option value="{{ $dt }}"
                                        {{ old('document_type', $regulation->document_type) === $dt ? 'selected' : '' }}>
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
                           value="{{ old('nombre', $regulation->name) }}"
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
                           value="{{ old('codigo', $regulation->code) }}"
                           placeholder="Ej: P-COM-001"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                </div>

                {{-- Elaborado por --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                        Elaborado por <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="quien_elabora"
                           value="{{ old('quien_elabora', $d['quien_elabora'] ?? '') }}"
                           placeholder="Ej: Juan Pérez — Jefe de Compras"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('quien_elabora') border-red-400 @enderror">
                    @error('quien_elabora')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Aprobado por --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                        Aprobado por <span class="text-red-500">*</span>
                    </label>
                    <input type="text"
                           name="quien_aprueba"
                           value="{{ old('quien_aprueba', $d['quien_aprueba'] ?? '') }}"
                           placeholder="Ej: María López — Directora de Operaciones"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('quien_aprueba') border-red-400 @enderror">
                    @error('quien_aprueba')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Fecha de vigencia --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                        Fecha de vigencia <span class="text-red-500">*</span>
                    </label>
                    <input type="date"
                           name="fecha_vigencia"
                           value="{{ old('fecha_vigencia', $d['fecha_vigencia'] ?? '') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('fecha_vigencia') border-red-400 @enderror">
                    @error('fecha_vigencia')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

            </div>
        </div>

        <div class="mt-6 flex items-center gap-3">
            <button type="submit"
                    class="px-6 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] text-sm">
                Guardar cambios
            </button>
            <a href="{{ route('processes.show', $regulation) }}"
               class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
        </div>

    </form>

</x-layouts.vigia>
