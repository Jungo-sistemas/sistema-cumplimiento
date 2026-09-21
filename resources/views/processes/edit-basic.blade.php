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

    <form method="POST" action="{{ route('processes.updateBasic', $regulation) }}"
          x-data
          x-init="$nextTick(() => { initPersonPicker($refs.responsables); })">
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

                {{-- is_annex --}}
                <div class="flex items-start gap-2 rounded-md border border-gray-200 bg-gray-50 px-3 py-2.5">
                    <input
                        type="checkbox"
                        id="is_annex"
                        name="is_annex"
                        value="1"
                        {{ old('is_annex', $regulation->is_annex) ? 'checked' : '' }}
                        class="mt-0.5 rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A]"
                    >
                    <label for="is_annex" class="text-xs text-gray-600">
                        <span class="font-medium uppercase tracking-wide text-gray-500">Es un anexo</span>
                        <span class="block text-xs text-gray-400 mt-0.5 normal-case">
                            Documento que existe para ser referenciado por otros procesos (formatos,
                            tabuladores, etc.), no un procedimiento en sí. No aparece en el listado
                            principal por defecto.
                        </span>
                    </label>
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

                {{-- Fecha de elaboración (el nombre interno del campo sigue siendo fecha_vigencia) --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                        Fecha de elaboración <span class="text-red-500">*</span>
                    </label>
                    <input type="date"
                           name="fecha_vigencia"
                           value="{{ old('fecha_vigencia', $d['fecha_vigencia'] ?? '') }}"
                           class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('fecha_vigencia') border-red-400 @enderror">
                    <p class="text-xs text-gray-400 mt-1">La vigencia (1 año) se asigna automáticamente cuando el documento se aprueba.</p>
                    @error('fecha_vigencia')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Motivo de creación --}}
                <div>
                    <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                        Motivo de creación <span class="text-red-500">*</span>
                    </label>
                    <textarea name="motivo_creacion"
                              rows="2"
                              placeholder="Ej: Se detectó falta de un procedimiento formal durante la auditoría interna 2026"
                              class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] @error('motivo_creacion') border-red-400 @enderror">{{ old('motivo_creacion', $d['motivo_creacion'] ?? '') }}</textarea>
                    @error('motivo_creacion')
                        <p class="text-xs text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Responsables de edición — solo un admin puede reasignarlos --}}
                @if(auth()->user()->isAdmin())
                    @php
                        // Un correo de "solicitud de acceso" trae ?highlight=<user_id> para que el
                        // admin aterrice exacto en la persona que pidió acceso, ya seleccionada
                        // como "chip" en el buscador — solo falta que dé clic en "Guardar".
                        $highlightUserId = (int) request('highlight');
                        $selectedResponsables = old('responsables', $regulation->responsables->pluck('id')->all());
                        if ($highlightUserId && ! in_array($highlightUserId, $selectedResponsables, true)) {
                            $selectedResponsables[] = $highlightUserId;
                        }
                    @endphp
                    <div id="responsables">
                        <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">
                            Responsables (pueden editar este reglamento)
                        </label>
                        <select name="responsables[]" multiple x-ref="responsables">
                            @foreach($candidateResponsables as $candidate)
                                <option value="{{ $candidate->id }}"
                                    @selected(in_array($candidate->id, $selectedResponsables, true))>
                                    {{ $candidate->name }}{{ $highlightUserId === $candidate->id ? ' (pidió acceso)' : '' }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-gray-400 mt-1">
                            Un operativo solo puede editar los reglamentos de los que es responsable. Los admins
                            pueden editar cualquier reglamento sin necesidad de estar en esta lista.
                        </p>
                    </div>
                @endif

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
