<x-layouts.vigia :title="$document->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('documents.index') }}" class="text-gray-600 hover:underline">Documentos</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">{{ $document->name }}</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    {{ $document->name }}
                </h1>

                @if(auth()->user()->hasGroupScope() && $document->company)
                    <div class="text-sm text-gray-500">
                        Empresa:
                        <span class="font-semibold text-gray-700">{{ $document->company->name }}</span>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 mt-2">
                    @if($document->document_type)
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-700 border-gray-200">
                            {{ $document->document_type }}
                        </span>
                    @endif

                    @if($document->reference)
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-600 border-gray-200">
                            Ref: {{ $document->reference }}
                        </span>
                    @endif

                    @if($document->bodega)
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-600 border-gray-200">
                            Bodega: {{ $document->bodega }}
                        </span>
                    @endif

                    @if($document->authorizedUsers->isNotEmpty())
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-600 border-gray-200">
                            Accesos: {{ $document->authorizedUsers->pluck('name')->join(', ') }}
                        </span>
                    @endif
                </div>
            </div>

            <a href="{{ route('documents.index') }}"
               class="shrink-0 px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
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

        {{-- Columnas: subir + versión actual --}}
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Subir / Reemplazar --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b flex items-start justify-between gap-3">
                    <div>
                        <div class="font-semibold text-[#1A428A]">
                            {{ $currentVersion ? 'Subir nueva versión' : 'Subir documento' }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Sube un archivo. Quedará registrado como una nueva versión y el histórico se conserva.
                        </div>
                    </div>
                    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                        <button type="button"
                                x-data
                                @click="$dispatch('open-modal', 'edit-document')"
                                class="shrink-0 px-3 py-1.5 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                            Editar
                        </button>
                    @endif
                </div>

                <div class="p-5">
                    @if(!(auth()->user()->isAdmin() || auth()->user()->isOperative()))
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            No tienes permisos para subir documentos.
                        </div>
                    @else
                        @php
                            $defaultMode = $currentVersion?->valid_until
                                ? 'renewal'
                                : ($currentVersion?->issued_at ? 'no_renewal' : 'no_dates');
                        @endphp
                        <form method="POST"
                              action="{{ route('documents.versions.store', $document) }}"
                              enctype="multipart/form-data"
                              x-data="{ mode: '{{ old('date_mode', $defaultMode) }}' }"
                              class="space-y-4">
                            @csrf
                            <input type="hidden" name="date_mode" :value="mode">

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo</label>
                                <input type="file"
                                       name="file"
                                       class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                                       required>
                                @error('file')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror
                                <div class="text-xs text-gray-500 mt-1">PDF, JPG o PNG. Máximo 10 MB.</div>
                            </div>

                            {{-- Tipo de documento --}}
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de documento</label>
                                <div class="grid grid-cols-3 gap-2">
                                    <button type="button" @click="mode = 'no_dates'"
                                            :class="mode === 'no_dates'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Sin fechas<br>
                                        <span class="font-normal opacity-75">sin revisión</span>
                                    </button>
                                    <button type="button" @click="mode = 'no_renewal'"
                                            :class="mode === 'no_renewal'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Sin renovación<br>
                                        <span class="font-normal opacity-75">solo emisión</span>
                                    </button>
                                    <button type="button" @click="mode = 'renewal'"
                                            :class="mode === 'renewal'
                                                ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                                : 'bg-white text-gray-700 border-gray-300 hover:bg-gray-50'"
                                            class="px-3 py-2 text-xs font-medium rounded-md border transition text-center leading-snug">
                                        Con renovación<br>
                                        <span class="font-normal opacity-75">emisión y vencimiento</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Fechas (condicional según tipo) --}}
                            <div x-show="mode !== 'no_dates'"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de emisión
                                    </label>
                                    <input type="date"
                                           name="issued_at"
                                           value="{{ old('issued_at', $currentVersion?->issued_at?->format('Y-m-d')) }}"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    @error('issued_at')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div x-show="mode === 'renewal'"
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0"
                                     x-transition:enter-end="opacity-100">
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de vencimiento
                                    </label>
                                    <input type="date"
                                           name="valid_until"
                                           value="{{ old('valid_until', $currentVersion?->valid_until?->format('Y-m-d')) }}"
                                           :required="mode === 'renewal'"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                                    @error('valid_until')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <button type="submit"
                                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                                {{ $currentVersion ? 'Subir nueva versión' : 'Subir documento' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Versión actual --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b">
                    <div class="font-semibold text-[#1A428A]">Versión actual</div>
                    <div class="text-sm text-gray-500">
                        La versión vigente del documento. Las anteriores permanecen en el historial.
                    </div>
                </div>

                <div class="p-5">
                    @if($currentVersion)
                        <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate">
                                    {{ $currentVersion->original_name ?? basename($currentVersion->file_path) }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="block">Subido por: {{ $currentVersion->uploader?->name ?? '—' }}</span>
                                    <span class="block">{{ $currentVersion->created_at?->format('d/m/Y H:i') }}</span>
                                    @if($currentVersion->issued_at)
                                        <span class="block">Emisión: {{ $currentVersion->issued_at->format('d/m/Y') }}</span>
                                    @endif
                                    @if($currentVersion->valid_until)
                                        <span class="block {{ $currentVersion->isExpired() ? 'text-red-600 font-medium' : ($currentVersion->isNearExpiration() ? 'text-yellow-600 font-medium' : '') }}">
                                            Vigente hasta: {{ $currentVersion->valid_until->format('d/m/Y') }}
                                            @if($currentVersion->isExpired()) <span class="text-xs">(Vencido)</span>
                                            @elseif($currentVersion->isNearExpiration()) <span class="text-xs">(Por vencer)</span>
                                            @endif
                                        </span>
                                    @endif
                                    <span class="block">Versión: {{ $currentVersion->version_number }} · <span class="text-green-700 font-medium">Actual</span></span>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('document-versions.preview', $currentVersion) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>
                                <a href="{{ route('document-versions.download', $currentVersion) }}"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Descargar
                                </a>
                                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                    <button type="button"
                                            onclick="openDeleteModal(
                                                '{{ route('document-versions.destroy', [$document, $currentVersion]) }}',
                                                @js($currentVersion->original_name ?? basename($currentVersion->file_path)),
                                                '{{ $currentVersion->version_number }}'
                                            )"
                                            class="px-3 py-2 rounded-md font-semibold text-sm bg-[#DB0000] text-white hover:bg-red-700">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Aún no hay archivo subido para este documento.
                        </div>
                    @endif
                </div>
            </div>

        </div>

        {{-- Historial de versiones --}}
        <div class="mt-8 bg-white border rounded-xl overflow-hidden">
            <div class="p-5 border-b">
                <div class="font-semibold text-[#1A428A]">Histórico documental</div>
                <div class="text-sm text-gray-500">
                    Se conservan todas las versiones del documento registradas.
                </div>
            </div>

            <div class="p-5">
                @if($versionHistory->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($versionHistory as $v)
                            <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">
                                        {{ $v->original_name ?? basename($v->file_path) }}
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <span class="block">
                                            Versión: {{ $v->version_number }}
                                            ·
                                            @if($v->is_current)
                                                <span class="text-green-700 font-medium">Actual</span>
                                            @else
                                                <span class="font-medium">{{ ucfirst($v->status ?? 'Reemplazada') }}</span>
                                            @endif
                                        </span>
                                        <span class="block">Subido por: {{ $v->uploader?->name ?? '—' }}</span>
                                        <span class="block">Fecha de carga: {{ $v->created_at?->format('d/m/Y H:i') }}</span>
                                        @if($v->issued_at)
                                            <span class="block">Emisión: {{ $v->issued_at->format('d/m/Y') }}</span>
                                        @endif
                                        @if($v->valid_until)
                                            <span class="block {{ $v->isExpired() ? 'text-red-600 font-medium' : ($v->isNearExpiration() ? 'text-yellow-600 font-medium' : '') }}">
                                                Vigente hasta: {{ $v->valid_until->format('d/m/Y') }}
                                                @if($v->isExpired()) <span class="text-xs">(Vencido)</span>
                                                @elseif($v->isNearExpiration()) <span class="text-xs">(Por vencer)</span>
                                                @endif
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('document-versions.preview', $v) }}"
                                       target="_blank"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Ver
                                    </a>
                                    <a href="{{ route('document-versions.download', $v) }}"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Descargar
                                    </a>
                                    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                        <button type="button"
                                                onclick="openDeleteModal(
                                                    '{{ route('document-versions.destroy', [$document, $v]) }}',
                                                    @js($v->original_name ?? basename($v->file_path)),
                                                    '{{ $v->version_number }}'
                                                )"
                                                class="px-3 py-2 rounded-md font-semibold text-sm bg-[#DB0000] text-white hover:bg-red-700">
                                            Eliminar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                        Aún no hay versiones registradas en el historial.
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Modal de confirmación de eliminación --}}
    <div id="deleteModal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="p-6 border-b">
                <h3 class="text-lg font-bold text-gray-900">Confirmar eliminación</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Vas a eliminar una versión del historial. Esta acción debe usarse solo para archivos cargados por error.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-[#1A428A]">
                    Esta acción eliminará el archivo seleccionado permanentemente.
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <div><span class="font-semibold">Archivo:</span> <span id="deleteFileName">—</span></div>
                    <div class="mt-1"><span class="font-semibold">Versión:</span> <span id="deleteVersionNumber">—</span></div>
                </div>

                <div>
                    <label for="delete_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Escribe <span class="font-bold">ELIMINAR</span> para confirmar
                    </label>
                    <input id="delete_confirmation"
                           type="text"
                           class="block w-full rounded-md border-gray-300 focus:border-red-600 focus:ring-red-600 text-sm"
                           placeholder="ELIMINAR"
                           oninput="validateDeleteConfirmation()">
                </div>

                <form id="deleteForm" method="POST" class="flex items-center justify-end gap-3">
                    @csrf
                    @method('DELETE')

                    <button type="button"
                            onclick="closeDeleteModal()"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>

                    <button id="deleteSubmitButton"
                            type="submit"
                            disabled
                            class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed">
                        Confirmar eliminación
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(actionUrl, fileName, versionNumber) {
            const modal = document.getElementById('deleteModal');
            const form  = document.getElementById('deleteForm');
            const input = document.getElementById('delete_confirmation');
            const btn   = document.getElementById('deleteSubmitButton');

            form.action = actionUrl;
            document.getElementById('deleteFileName').textContent = fileName || '—';
            document.getElementById('deleteVersionNumber').textContent = versionNumber || '—';
            input.value = '';
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            input.focus();
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function validateDeleteConfirmation() {
            const input = document.getElementById('delete_confirmation');
            const btn   = document.getElementById('deleteSubmitButton');
            const valid = input.value.trim() === 'ELIMINAR';
            btn.disabled = !valid;
            btn.classList.toggle('opacity-50', !valid);
            btn.classList.toggle('cursor-not-allowed', !valid);
        }

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') closeDeleteModal();
        });
    </script>

    {{-- MODAL: Editar documento --}}
    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
        <x-modal name="edit-document" :show="$errors->editDocument->isNotEmpty()" focusable maxWidth="lg">
            <form method="POST"
                  action="{{ route('documents.update', $document) }}"
                  x-data
                  x-init="$nextTick(() => {
                      initPersonPicker($refs.responsibleUser, { multiple: false });
                      initPersonPicker($refs.authorizedUsers);
                  })"
                  class="p-6">
                @csrf
                @method('PATCH')

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Editar documento</h2>

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
                                        @selected(old('company_id', $document->company_id) == $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($errors->editDocument->has('company_id'))
                                <p class="text-sm text-red-600 mt-1">{{ $errors->editDocument->first('company_id') }}</p>
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
                               value="{{ old('name', $document->name) }}"
                               required
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->editDocument->has('name'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->editDocument->first('name') }}</p>
                        @endif
                    </div>

                    {{-- Referencia / Oficio --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Referencia / Oficio
                        </label>
                        <input type="text"
                               name="reference"
                               value="{{ old('reference', $document->reference) }}"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    {{-- Bodega --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Bodega
                        </label>
                        <input type="text"
                               name="bodega"
                               value="{{ old('bodega', $document->bodega) }}"
                               placeholder="Ubicación física del documento"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->editDocument->has('bodega'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->editDocument->first('bodega') }}</p>
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
                                <option value="{{ $type }}" @selected(old('document_type', $document->document_type) === $type)>{{ $type }}</option>
                            @endforeach
                        </select>
                        @if($errors->editDocument->has('document_type'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->editDocument->first('document_type') }}</p>
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
                                        @selected(old('responsible_name', $document->responsible_name) === $u->name)>
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
                        @php $selectedAuthorizedIds = old('authorized_user_ids', $document->authorizedUsers->pluck('id')->all()); @endphp
                        <select name="authorized_user_ids[]" multiple x-ref="authorizedUsers">
                            @foreach($groupUsers as $u)
                                <option value="{{ $u->id }}"
                                    @selected(in_array($u->id, $selectedAuthorizedIds))>
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
                        Guardar cambios
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

</x-layouts.vigia>
