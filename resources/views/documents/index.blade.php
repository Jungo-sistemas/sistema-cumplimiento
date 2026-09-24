<x-layouts.vigia title="Documentos">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Documentos</span>
    </x-slot>

    @php $user = auth()->user(); @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Documentos</h1>
        <div class="flex items-center gap-3">
            @if($user->isAdmin() || $user->isOperative())
                <button type="button"
                        x-data
                        @click="$dispatch('open-modal', 'create-document')"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    + Nuevo documento
                </button>
                <a href="{{ route('documents.report.weekly') }}"
                   class="flex items-center gap-2 px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M12 10v6m0 0l-3-3m3 3l3-3M3 17V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    </svg>
                    Reporte semanal
                </a>
            @endif
            @if($user->isAdmin())
                <a href="{{ route('documents.trash.index') }}"
                   class="flex items-center gap-2 px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Papelera
                </a>
            @endif
        </div>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('documents.index') }}"
          class="mt-4 flex flex-wrap items-end gap-3">

        @if($user->hasGroupScope())
            <div class="min-w-[180px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            @selected((string) request('company_id', $selectedCompanyId) === (string) $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="min-w-[200px]">
            <label class="block text-xs text-gray-500 mb-1">Tipo de documento</label>
            <select name="document_type" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>
                @foreach($documentTypes as $type)
                    <option value="{{ $type }}" @selected($selectedType === $type)>{{ $type }}</option>
                @endforeach
            </select>
        </div>

        <div class="min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1">Vigencia</label>
            <select name="vigencia" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todas</option>
                <option value="vigente" @selected($selectedVigencia === 'vigente')>Vigente</option>
                <option value="por_vencer" @selected($selectedVigencia === 'por_vencer')>Por vencer</option>
                <option value="vencido" @selected($selectedVigencia === 'vencido')>Vencido</option>
                <option value="sin_vencimiento" @selected($selectedVigencia === 'sin_vencimiento')>Sin vencimiento</option>
            </select>
        </div>

        <div class="flex-1 min-w-[200px] max-w-sm">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text"
                   name="q"
                   value="{{ request('q') }}"
                   placeholder="Nombre o referencia..."
                   class="w-full rounded-md border-gray-300 text-sm">
        </div>

        <button type="submit"
                class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
            Filtrar
        </button>

        <a href="{{ route('documents.index') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

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

                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="text-left px-4 py-3">Nombre del Documento</th>
                        @if($user->hasGroupScope())
                            <th class="text-left px-4 py-3">Empresa</th>
                        @endif
                        <th class="text-left px-4 py-3">Referencia / Oficio</th>
                        <th class="text-left px-4 py-3">Fecha</th>
                        <th class="text-left px-4 py-3">Vencimiento</th>
                        <th class="text-left px-4 py-3">Tipo</th>
                        <th class="text-left px-4 py-3">Responsable</th>
                        <th class="text-left px-4 py-3">Archivo</th>
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

                        <tr class="border-t border-gray-100 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                            ondblclick="window.location.href='{{ route('documents.show', $document) }}'">

                            {{-- Nombre --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900">{{ $document->name }}</div>
                            </td>

                            {{-- Empresa --}}
                            @if($user->hasGroupScope())
                                <td class="px-4 py-3">
                                    {{ $document->company?->name ?? '—' }}
                                </td>
                            @endif

                            {{-- Referencia / Oficio --}}
                            <td class="px-4 py-3">
                                {{ $document->reference ?? '—' }}
                            </td>

                            {{-- Fecha (issued_at de la versión actual) --}}
                            <td class="px-4 py-3 whitespace-nowrap">
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
                            <td class="px-4 py-3">
                                {{ $document->document_type ?? '—' }}
                            </td>

                            {{-- Responsable --}}
                            <td class="px-4 py-3">
                                {{ $document->responsible_name ?? '—' }}
                            </td>

                            {{-- Archivo --}}
                            <td class="px-4 py-3">
                                @if($version && $version->file_path)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                        ✓ Disponible
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                        Sin archivo
                                    </span>
                                @endif
                            </td>

                            {{-- Acción --}}
                            <td class="px-4 py-3 text-right" ondblclick="event.stopPropagation()">
                                <div class="flex items-center justify-end gap-4">
                                    <a href="{{ route('documents.show', $document) }}"
                                       class="font-semibold text-blue-600 hover:underline whitespace-nowrap">
                                        Gestionar →
                                    </a>
                                    @if($user->isAdmin())
                                        <form method="POST"
                                              action="{{ route('documents.trash.move', $document) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    class="font-semibold text-red-500 hover:text-red-700"
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
                            <td colspan="{{ $user->hasGroupScope() ? 9 : 8 }}"
                                class="px-6 py-6 text-center text-gray-500">
                                No se encontraron documentos con los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        @if($documents->hasPages())
            <div class="px-4 py-3 border-t bg-gray-50">
                {{ $documents->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL: Crear documento --}}
    @if($user->isAdmin() || $user->isOperative())
        <x-modal name="create-document" :show="$errors->createDocument->isNotEmpty()" focusable maxWidth="lg">
            <form method="POST"
                  action="{{ route('documents.store') }}"
                  x-data
                  x-init="$nextTick(() => {
                      initPersonPicker($refs.responsibleUser, { multiple: false });
                      initPersonPicker($refs.authorizedUsers);
                  })"
                  class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Nuevo documento</h2>

                <div class="space-y-4">

                    {{-- Empresa --}}
                    @if($user->hasGroupScope() && $companies->isNotEmpty())
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

                    {{-- Bodega --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Bodega
                        </label>
                        <input type="text"
                               name="bodega"
                               value="{{ old('bodega') }}"
                               placeholder="Ubicación física del documento"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->createDocument->has('bodega'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createDocument->first('bodega') }}</p>
                        @endif
                    </div>

                    {{-- Tipo de documento --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de documento <span class="text-red-500">*</span>
                        </label>
                        <select name="document_type" required
                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">— Seleccionar —</option>
                            @foreach($documentTypes as $type)
                                <option value="{{ $type }}" @selected(old('document_type') === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @if($errors->createDocument->has('document_type'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createDocument->first('document_type') }}</p>
                        @endif
                    </div>

                    {{-- Responsable --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Responsable
                        </label>
                        <select name="responsible_name" x-ref="responsibleUser">
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
                        <p class="text-xs text-gray-500 mb-2">
                            Busca y agrega las personas que tienen acceso autorizado a este documento.
                        </p>
                        <select name="authorized_user_ids[]" multiple x-ref="authorizedUsers">
                            @foreach($groupUsers as $u)
                                <option value="{{ $u->id }}"
                                    @selected(in_array($u->id, old('authorized_user_ids', [])))>
                                    {{ $u->name }}
                                </option>
                            @endforeach
                        </select>
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
