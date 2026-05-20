<x-layouts.vigia :title="$category->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('documents.index') }}" class="text-gray-500 hover:underline">Documentos</a>
        <span class="mx-1 text-gray-400">›</span>
        @if($category->parent)
            <a href="{{ route('documents.folders.show', $category->parent) }}"
               class="text-gray-500 hover:underline">
                {{ $category->parent->name }}
            </a>
            <span class="mx-1 text-gray-400">›</span>
        @endif
        <span class="text-gray-700 font-medium">{{ $category->name }}</span>
    </x-slot>

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">{{ $category->name }}</h1>
            @if($category->parent)
                <p class="text-sm text-gray-500 mt-1">{{ $category->parent->name }}</p>
            @endif
        </div>

        <div class="flex items-center gap-3">
            @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                <button type="button"
                        x-data
                        @click="$dispatch('open-modal', 'create-document')"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    + Nuevo documento
                </button>
            @endif

            <a href="{{ $category->parent ? route('documents.folders.show', $category->parent) : route('documents.index') }}"
               class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
        </div>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA DE DOCUMENTOS --}}
    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">Nombre del Documento</th>
                        <th class="text-left px-4 py-3 font-semibold">Referencia / Oficio</th>
                        <th class="text-left px-4 py-3 font-semibold">Fecha</th>
                        <th class="text-left px-4 py-3 font-semibold">Vencimiento</th>
                        <th class="text-left px-4 py-3 font-semibold">Tipo</th>
                        <th class="text-left px-4 py-3 font-semibold">Responsable</th>
                        <th class="text-left px-4 py-3 font-semibold">Accesos Autorizados</th>
                        <th class="text-left px-4 py-3 font-semibold">Archivo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($documents as $document)
                        @php
                            $version = $document->currentVersion;
                            $isExpired = $document->isExpired();
                            $isNearExpiration = !$isExpired && $document->isNearExpiration();
                        @endphp

                        <tr class="border-t hover:bg-gray-50">

                            {{-- Nombre --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $document->name }}</div>
                                @if($document->is_required)
                                    <span class="inline-block mt-1 text-xs bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">
                                        Requerido
                                    </span>
                                @endif
                            </td>

                            {{-- Referencia / Oficio --}}
                            <td class="px-4 py-3 text-gray-600">
                                {{ $document->reference ?? '—' }}
                            </td>

                            {{-- Fecha (issued_at de la versión actual) --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $version?->issued_at?->format('d/m/Y') ?? '—' }}
                            </td>

                            {{-- Vencimiento --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($version?->valid_until)
                                    <span class="font-medium
                                        @if($isExpired) text-red-600
                                        @elseif($isNearExpiration) text-yellow-600
                                        @else text-gray-700
                                        @endif">
                                        {{ $version->valid_until->format('d/m/Y') }}
                                    </span>
                                    @if($isExpired)
                                        <div class="text-xs text-red-500">Vencido</div>
                                    @elseif($isNearExpiration)
                                        <div class="text-xs text-yellow-600">Por vencer</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </td>

                            {{-- Tipo de Documento --}}
                            <td class="px-4 py-3 text-gray-600">
                                {{ $document->document_type ?? '—' }}
                            </td>

                            {{-- Responsable --}}
                            <td class="px-4 py-3 text-gray-700">
                                {{ $document->responsible_name ?? '—' }}
                            </td>

                            {{-- Accesos Autorizados --}}
                            <td class="px-4 py-3 text-gray-500 text-xs max-w-xs">
                                @if($document->authorized_access_notes)
                                    <x-truncate :text="$document->authorized_access_notes" :length="80" />
                                @else
                                    —
                                @endif
                            </td>

                            {{-- Archivo --}}
                            <td class="px-4 py-3">
                                @if($version && $version->file_path)
                                    <span class="inline-flex items-center gap-1 text-xs bg-green-100 text-green-700 rounded px-2 py-1 font-medium">
                                        ✓ Disponible
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 text-xs bg-gray-100 text-gray-500 rounded px-2 py-1">
                                        Sin archivo
                                    </span>
                                @endif
                            </td>

                            {{-- Acción --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('documents.document.show', [$category, $document]) }}"
                                   class="text-blue-600 font-semibold text-sm hover:underline">
                                    Gestionar →
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="9" class="px-6 py-6 text-center text-gray-500">
                                No hay documentos en esta categoría.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>


    {{-- MODAL: Crear documento --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
        <x-modal name="create-document" :show="$errors->createDocument->isNotEmpty()" focusable maxWidth="lg">
            <form method="POST"
                  action="{{ route('documents.categories.documents.store', $category) }}"
                  class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Nuevo documento</h2>

                <div class="space-y-4">

                    {{-- Nombre --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->createDocument->has('name'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createDocument->first('name') }}</p>
                        @endif
                    </div>

                    {{-- Referencia / Oficio --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Referencia / Oficio
                        </label>
                        <input type="text"
                               name="reference"
                               value="{{ old('reference') }}"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    {{-- Tipo de documento --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de documento
                        </label>
                        <select name="document_type"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">— Seleccionar —</option>
                            <option value="Original" @selected(old('document_type') === 'Original')>Original</option>
                            <option value="Copia"    @selected(old('document_type') === 'Copia')>Copia</option>
                        </select>
                    </div>

                    {{-- Responsable --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Responsable
                        </label>
                        <select name="responsible_name"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">— Seleccionar usuario —</option>
                            @foreach($users as $u)
                                <option value="{{ $u->name }}"
                                        @selected(old('responsible_name') === $u->name)>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Accesos Autorizados --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Accesos Autorizados
                        </label>
                        <textarea name="authorized_access_notes"
                                  rows="2"
                                  class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">{{ old('authorized_access_notes') }}</textarea>
                    </div>

                    {{-- ¿Requerido? --}}
                    <div class="flex items-center gap-2">
                        <input type="checkbox"
                               name="is_required"
                               id="is_required_modal"
                               value="1"
                               {{ old('is_required') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A]">
                        <label for="is_required_modal" class="text-sm text-gray-700">
                            Documento requerido
                        </label>
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                            x-on:click="$dispatch('close')"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                        Guardar
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

</x-layouts.vigia>
