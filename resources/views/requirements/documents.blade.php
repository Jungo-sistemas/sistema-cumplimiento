{{-- resources/views/requirements/documents.blade.php --}}

<x-layouts.vigia
    :title="'Documento oficial: ' . ($requirement->template?->name ?? $requirement->type)"
    :nav-context="$navContext"
>
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset?->company_id)])) }}"
           class="shrink-0 text-gray-600 hover:underline">
            Activos y Actividades
        </a>

        <span class="shrink-0 text-gray-400">›</span>

        <a href="{{ route('assets.show', $asset) }}" class="min-w-0 text-gray-600 hover:underline">
            <x-truncate max="max-w-[160px]">{{ $asset->name }}</x-truncate>
        </a>

        <span class="shrink-0 text-gray-400">›</span>

        <a href="{{ route('assets.requirements.show', [$asset, $requirement]) }}" class="min-w-0 text-gray-600 hover:underline">
            <x-truncate max="max-w-[180px]" class="font-semibold text-gray-700">
                {{ $requirement->template?->name ?? $requirement->type }}
            </x-truncate>
        </a>

        <span class="shrink-0 text-gray-400">›</span>

        <span class="shrink-0 text-gray-700 font-medium">Documento oficial</span>
    </x-slot>

    @php
        $assetInactive = $assetInactive ?? (
            ($asset->status ?? null) === \App\Models\Asset::STATUS_INACTIVE
            || (method_exists($asset, 'isInactive') && $asset->isInactive())
        );

        $currentDoc = $requirement->currentDocument
            ?? $requirement->documents?->firstWhere('is_current', true)
            ?? $requirement->documents?->sortByDesc('version_number')->first();

        $documentHistory = $requirement->documents
            ? $requirement->documents->sortByDesc('version_number')
            : collect();
    @endphp

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    Documento oficial
                </h1>

                <div class="text-sm text-gray-500">
                    Carpeta:
                    <x-truncate max="max-w-[700px]" class="font-semibold text-gray-700">
                        {{ $requirement->template?->name ?? $requirement->type }}
                    </x-truncate>

                    · Activo:

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

                @if($assetInactive)
                    <div class="mt-2 inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-100 text-gray-700 border-gray-300">
                        Activo sin operar
                    </div>
                @else
                    <div class="mt-2 inline-flex items-center text-xs px-3 py-1 rounded border bg-green-50 text-green-700 border-green-200">
                        Activo operando
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('assets.requirements.show', [$asset, $requirement]) }}"
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

            @if(session('renewal_suggestion'))
                @php $rSug = session('renewal_suggestion'); @endphp
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4">
                    <p class="font-semibold text-blue-800 text-sm">¿Crear tarea de renovación?</p>
                    <p class="text-sm text-blue-700 mt-1">
                        Se sugiere crear: <span class="font-medium">{{ $rSug['title'] }}</span>
                        con fecha límite <span class="font-medium">{{ \Carbon\Carbon::parse($rSug['due_date'])->format('d/m/Y') }}</span>.
                    </p>
                    <div class="mt-3 flex items-center gap-3">
                        <form method="POST"
                              action="{{ route('assets.requirements.renewal-task', [$asset, $requirement]) }}">
                            @csrf
                            <input type="hidden" name="title"    value="{{ $rSug['title'] }}">
                            <input type="hidden" name="due_date" value="{{ $rSug['due_date'] }}">
                            @if(!empty($rSug['responsible_user_id']))
                                <input type="hidden" name="responsible_user_id" value="{{ $rSug['responsible_user_id'] }}">
                            @endif
                            <button type="submit"
                                    class="px-4 py-1.5 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                Crear tarea
                            </button>
                        </form>
                        <a href="{{ route('assets.requirements.documents.index', [$asset, $requirement]) }}"
                           class="px-4 py-1.5 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                            No por ahora
                        </a>
                    </div>
                </div>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Subir / Reemplazar --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b">
                    <div class="font-semibold text-[#1A428A]">
                        {{ $currentDoc ? 'Subir nueva versión' : 'Subir documento' }}
                    </div>

                    <div class="text-sm text-gray-500">
                        Sube un archivo y se guardará como una nueva versión del documento oficial de esta carpeta.
                    </div>
                </div>

                <div class="p-5">
                    @if(!(auth()->user()->isAdmin() || auth()->user()->isOperative()))
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            No tienes permisos para subir documentación oficial.
                        </div>
                    @elseif($assetInactive)
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Este activo está desactivado. Actívalo para subir documentación oficial.
                        </div>
                    @else
                        <form method="POST"
                              action="{{ route('assets.requirements.documents.store', [$asset, $requirement]) }}"
                              enctype="multipart/form-data"
                              class="space-y-4">
                            @csrf

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Archivo
                                </label>

                                <input type="file"
                                       name="file"
                                       class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                                       required>

                                @error('file')
                                    <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                @enderror

                                <div class="text-xs text-gray-500 mt-1">
                                    Recomendado: PDF, JPG o PNG. Tamaño máximo: 10MB.
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de emisión
                                    </label>

                                    <input type="date"
                                           name="issued_at"
                                           value="{{ old('issued_at', optional($currentDoc?->issued_at)->format('Y-m-d')) }}"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">

                                    @error('issued_at')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de vencimiento
                                    </label>

                                    <input type="date"
                                           name="expires_at"
                                           value="{{ old('expires_at', optional($currentDoc?->expires_at)->format('Y-m-d')) }}"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                                           required>

                                    @error('expires_at')
                                        <div class="text-sm text-red-600 mt-1">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <button type="submit"
                                class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                                {{ $currentDoc ? 'Subir nueva versión' : 'Subir documento' }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Documento actual --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b">
                    <div class="font-semibold text-[#1A428A]">Documento actual</div>
                    <div class="text-sm text-gray-500">
                       Se muestra la versión actual del documento oficial. Las versiones anteriores permanecen en el historial.
                    </div>
                </div>

                <div class="p-5">
                    @if($currentDoc)
                        <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate">
                                    {{ $currentDoc->original_name ?? basename($currentDoc->file_path) }}
                                </div>

                                <div class="text-sm text-gray-500 mt-1">
                                    <span class="block">Subido por: {{ $currentDoc->uploader?->name ?? '—' }}</span>
                                    <span class="block">{{ optional($currentDoc->created_at)->format('Y-m-d H:i') }}</span>

                                    @if($currentDoc->issued_at)
                                        <span class="block">
                                            Emisión: {{ $currentDoc->issued_at->format('Y-m-d') }}
                                        </span>
                                    @endif

                                    @if($currentDoc->expires_at)
                                        <span class="block">
                                            Vigente hasta: {{ $currentDoc->expires_at->format('Y-m-d') }}
                                        </span>
                                    @endif

                                    <div class="text-sm text-gray-500 mt-1">
                                        <span class="block">Versión: {{ $currentDoc->version_number ?? '—' }}</span>
                                        <span class="block">Estado: {{ $currentDoc->is_current ? 'Actual' : ucfirst($currentDoc->status ?? '—') }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('assets.requirements.documents.preview', [$asset, $requirement, $currentDoc]) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>

                                <a href="{{ route('assets.requirements.documents.download', [$asset, $requirement, $currentDoc]) }}"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Descargar
                                </a>

                                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                    <button
                                        type="button"
                                        onclick="openDeleteDocumentModal(
                                            '{{ route('assets.requirements.documents.destroy', [$asset, $requirement, $currentDoc]) }}',
                                            @js($currentDoc->original_name ?? basename($currentDoc->file_path)),
                                            '{{ $currentDoc->version_number ?? '—' }}'
                                        )"
                                        class="px-3 py-2 rounded-md font-semibold text-sm
                                        {{ $assetInactive ? 'bg-gray-100 text-gray-500 border border-gray-300 cursor-not-allowed' : 'bg-[#DB0000] text-white hover:bg-red-700' }}"
                                        {{ $assetInactive ? 'disabled' : '' }}
                                    >
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Aún no hay documento oficial.
                        </div>
                    @endif
                </div>
            </div>

        </div>

        <div class="mt-8 bg-white border rounded-xl overflow-hidden">
            <div class="p-5 border-b">
                <div class="font-semibold text-[#1A428A]">Histórico documental</div>
                <div class="text-sm text-gray-500">
                    Se conservan todas las versiones del documento oficial registradas para este requerimiento.
                </div>
            </div>

            <div class="p-5">
                @if($documentHistory->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($documentHistory as $historyDoc)
                            <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">
                                        {{ $historyDoc->original_name ?? basename($historyDoc->file_path) }}
                                    </div>

                                    <div class="text-sm text-gray-500 mt-1">
                                        <span class="block">
                                            Versión: {{ $historyDoc->version_number ?? '—' }}
                                            @if($historyDoc->is_current)
                                                · <span class="text-green-700 font-medium">Actual</span>
                                            @else
                                                · <span class="text-gray-700 font-medium">{{ ucfirst($historyDoc->status ?? '—') }}</span>
                                            @endif
                                        </span>

                                        <span class="block">
                                            Subido por: {{ $historyDoc->uploader?->name ?? '—' }}
                                        </span>

                                        <span class="block">
                                            Fecha de carga: {{ optional($historyDoc->created_at)->format('Y-m-d H:i') }}
                                        </span>

                                        @if($historyDoc->issued_at)
                                            <span class="block">
                                                Emisión: {{ $historyDoc->issued_at->format('Y-m-d') }}
                                            </span>
                                        @endif

                                        @if($historyDoc->expires_at)
                                            <span class="block">
                                                Vencimiento: {{ $historyDoc->expires_at->format('Y-m-d') }}
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('assets.requirements.documents.preview', [$asset, $requirement, $historyDoc]) }}"
                                       target="_blank"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Ver
                                    </a>

                                    <a href="{{ route('assets.requirements.documents.download', [$asset, $requirement, $historyDoc]) }}"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Descargar
                                    </a>

                                    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                        <button
                                            type="button"
                                            onclick="openDeleteDocumentModal(
                                                '{{ route('assets.requirements.documents.destroy', [$asset, $requirement, $historyDoc]) }}',
                                                @js($historyDoc->original_name ?? basename($historyDoc->file_path)),
                                                '{{ $historyDoc->version_number ?? '—' }}'
                                            )"
                                            class="px-3 py-2 rounded-md font-semibold text-sm
                                            {{ $assetInactive ? 'bg-gray-100 text-gray-500 border border-gray-300 cursor-not-allowed' : 'bg-[#DB0000] text-white hover:bg-red-700' }}"
                                            {{ $assetInactive ? 'disabled' : '' }}
                                        >
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

    <div
        id="deleteDocumentModal"
        class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4"
    >
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="p-6 border-b">
                <h3 class="text-lg font-bold text-gray-900">
                    Confirmar eliminación
                </h3>
                <p class="mt-2 text-sm text-gray-600">
                    Vas a eliminar un documento del historial oficial. Esta acción debe usarse solo para archivos cargados por error.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm text-[#1A428A]">
                    Esta acción eliminará el documento seleccionado. Úsala solo si el archivo fue cargado por error.
                </div>

                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <div>
                        <span class="font-semibold">Archivo:</span>
                        <span id="deleteDocumentName">—</span>
                    </div>
                    <div class="mt-1">
                        <span class="font-semibold">Versión:</span>
                        <span id="deleteDocumentVersion">—</span>
                    </div>
                </div>

                <div>
                    <label for="delete_document_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Escribe <span class="font-bold">ELIMINAR</span> para confirmar
                    </label>

                    <input
                        id="delete_document_confirmation"
                        type="text"
                        class="block w-full rounded-md border-gray-300 focus:border-red-600 focus:ring-red-600 text-sm"
                        placeholder="ELIMINAR"
                        oninput="validateDeleteDocumentConfirmation()"
                    >
                </div>

                <form id="deleteDocumentForm" method="POST" class="flex items-center justify-end gap-3">
                    @csrf
                    @method('DELETE')

                    <button
                        type="button"
                        onclick="closeDeleteDocumentModal()"
                        class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50"
                    >
                        Cancelar
                    </button>

                    <button
                        id="deleteDocumentSubmitButton"
                        type="submit"
                        disabled
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold opacity-50 cursor-not-allowed disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        Confirmar eliminación
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteDocumentModal(actionUrl, fileName, versionNumber) {
            const modal = document.getElementById('deleteDocumentModal');
            const form = document.getElementById('deleteDocumentForm');
            const nameLabel = document.getElementById('deleteDocumentName');
            const versionLabel = document.getElementById('deleteDocumentVersion');
            const input = document.getElementById('delete_document_confirmation');
            const submitButton = document.getElementById('deleteDocumentSubmitButton');

            form.action = actionUrl;
            nameLabel.textContent = fileName || '—';
            versionLabel.textContent = versionNumber || '—';
            input.value = '';
            submitButton.disabled = true;
            submitButton.classList.add('opacity-50', 'cursor-not-allowed');

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            input.focus();
        }

        function closeDeleteDocumentModal() {
            const modal = document.getElementById('deleteDocumentModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function validateDeleteDocumentConfirmation() {
            const input = document.getElementById('delete_document_confirmation');
            const submitButton = document.getElementById('deleteDocumentSubmitButton');
            const valid = input.value.trim() === 'ELIMINAR';

            submitButton.disabled = !valid;

            if (valid) {
                submitButton.classList.remove('opacity-50', 'cursor-not-allowed');
            } else {
                submitButton.classList.add('opacity-50', 'cursor-not-allowed');
            }
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeDeleteDocumentModal();
            }
        });
    </script>
</x-layouts.vigia>