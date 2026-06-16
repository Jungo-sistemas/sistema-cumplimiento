<x-layouts.vigia :title="$folder->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('documents.index') }}" class="text-gray-500 hover:underline">Documentos</a>
        <span class="mx-1 text-gray-400">›</span>
        <span class="text-gray-700 font-medium">{{ $folder->name }}</span>
    </x-slot>

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#1A428A]"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
            </div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">{{ $folder->name }}</h1>
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

            <a href="{{ route('documents.index') }}"
               class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
        </div>
    </div>

    {{-- FILTRO DE EMPRESA --}}
    @if(auth()->user()->hasGroupScope() && $companies->isNotEmpty())
        <form method="GET" action="{{ route('documents.folders.show', $folder) }}"
              class="mt-4 flex items-end gap-3">
            <div class="min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            @selected((string) $selectedCompanyId === (string) $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit"
                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                Filtrar
            </button>
            @if($selectedCompanyId)
                <a href="{{ route('documents.folders.show', $folder) }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                    Limpiar
                </a>
            @endif
        </form>
    @endif

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
                        @if(auth()->user()->hasGroupScope())
                            <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                        @endif
                        <th class="text-left px-4 py-3 font-semibold">Referencia / Oficio</th>
                        <th class="text-left px-4 py-3 font-semibold">Fecha</th>
                        <th class="text-left px-4 py-3 font-semibold">Vencimiento</th>
                        <th class="text-left px-4 py-3 font-semibold">Tipo</th>
                        <th class="text-left px-4 py-3 font-semibold">Responsable</th>
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

                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $document->name }}</div>
                                @if($document->is_required)
                                    <span class="inline-block mt-1 text-xs bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">
                                        Requerido
                                    </span>
                                @endif
                            </td>

                            @if(auth()->user()->hasGroupScope())
                                <td class="px-4 py-3 text-sm text-gray-600">
                                    {{ $document->company?->name ?? '—' }}
                                </td>
                            @endif

                            <td class="px-4 py-3 text-gray-600">
                                {{ $document->reference ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $version?->issued_at?->format('d/m/Y') ?? '—' }}
                            </td>

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

                            <td class="px-4 py-3 text-gray-600">
                                {{ $document->document_type ?? '—' }}
                            </td>

                            <td class="px-4 py-3 text-gray-700">
                                {{ $document->responsible_name ?? '—' }}
                            </td>

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

                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-3">
                                    <a href="{{ route('documents.folder.document.show', [$folder, $document]) }}"
                                       class="text-blue-600 font-semibold text-sm hover:underline whitespace-nowrap">
                                        Gestionar →
                                    </a>
                                    @if(auth()->user()->isAdmin())
                                        <form method="POST"
                                              action="{{ route('documents.folders.documents.destroy', [$folder, $document]) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="text-red-500 hover:text-red-700 text-xs font-medium"
                                                    onclick="return confirm('¿Mover «{{ addslashes($document->name) }}» a la papelera? Podrás restaurarlo en los próximos 2 meses.')">
                                                Eliminar
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="{{ auth()->user()->hasGroupScope() ? 9 : 8 }}"
                                class="px-6 py-6 text-center text-gray-500">
                                No hay documentos en esta carpeta
                                @if(auth()->user()->hasGroupScope() && $selectedCompanyId)
                                    para la empresa seleccionada
                                @endif.
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
                  action="{{ route('documents.folders.documents.store', $folder) }}"
                  class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Nuevo documento</h2>

                <div class="space-y-4">

                    {{-- Empresa --}}
                    @if(auth()->user()->hasGroupScope() && $companies->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Empresa <span class="text-red-500">*</span>
                            </label>
                            <select name="company_id" required
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">— Seleccionar empresa —</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}"
                                        @selected(old('company_id', $selectedCompanyId) == $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($errors->createDocument->has('company_id'))
                                <p class="text-sm text-red-600 mt-1">{{ $errors->createDocument->first('company_id') }}</p>
                            @endif
                        </div>
                    @endif

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
                               id="is_required_folder"
                               value="1"
                               {{ old('is_required') ? 'checked' : '' }}
                               class="rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A]">
                        <label for="is_required_folder" class="text-sm text-gray-700">
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
