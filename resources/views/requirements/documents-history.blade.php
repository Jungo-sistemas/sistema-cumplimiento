{{-- resources/views/requirements/documents-history.blade.php --}}

<x-layouts.vigia
    :title="'Historial documental: ' . ($requirement->template?->name ?? $requirement->type)"
    :nav-context="$navContext"
>
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => $asset?->company_id])) }}"
           class="shrink-0 text-gray-600 hover:underline">
            Energético
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

        <a href="{{ route('assets.requirements.documents.index', [$asset, $requirement]) }}" class="shrink-0 text-gray-600 hover:underline">
            Documento oficial
        </a>
        <span class="shrink-0 text-gray-400">›</span>

        <span class="shrink-0 text-gray-700 font-medium">Historial completo</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">Historial documental completo</h1>
                <div class="text-sm text-gray-500">
                    Carpeta:
                    <x-truncate max="max-w-[500px]" class="font-semibold text-gray-700">
                        {{ $requirement->template?->name ?? $requirement->type }}
                    </x-truncate>
                    · Activo:
                    <span class="font-semibold text-gray-700">{{ $asset->name }}</span>
                    @if(auth()->user()->hasGroupScope() && $asset->company)
                        · Empresa:
                        <span class="font-semibold text-gray-700">{{ $asset->company->name }}</span>
                    @endif
                </div>
                <p class="text-xs text-gray-400">
                    {{ $documentHistory->count() }} {{ $documentHistory->count() === 1 ? 'versión registrada' : 'versiones registradas' }}
                </p>
            </div>

            <a href="{{ route('assets.requirements.documents.index', [$asset, $requirement]) }}"
               class="shrink-0 px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
        </div>

        {{-- Historial --}}
        <div class="mt-8">
            @if($documentHistory->isNotEmpty())
                <div class="space-y-3">
                    @foreach($documentHistory as $doc)
                        <div class="border rounded-xl p-4 flex items-start justify-between gap-4
                            {{ $doc->is_current ? 'border-[#1A428A] bg-blue-50/30' : '' }}">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="font-semibold text-gray-900 truncate">
                                        {{ $doc->original_name ?? basename($doc->file_path) }}
                                    </span>
                                    @if($doc->is_current)
                                        <span class="text-xs px-2 py-0.5 rounded border bg-green-50 text-green-700 border-green-200 font-medium shrink-0">
                                            Actual
                                        </span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded border bg-gray-50 text-gray-500 border-gray-200 shrink-0">
                                            {{ ucfirst($doc->status ?? 'Reemplazada') }}
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-x-6 gap-y-1 text-sm text-gray-500">
                                    <div>
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide block">Versión</span>
                                        {{ $doc->version_number ?? '—' }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide block">Subido por</span>
                                        {{ $doc->uploader?->name ?? '—' }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide block">Fecha de carga</span>
                                        {{ optional($doc->created_at)->format('d/m/Y H:i') ?? '—' }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide block">Emisión</span>
                                        {{ optional($doc->issued_at)->format('d/m/Y') ?? '—' }}
                                    </div>
                                    <div>
                                        <span class="text-xs font-medium text-gray-400 uppercase tracking-wide block">Vencimiento</span>
                                        @if($doc->expires_at)
                                            <span class="{{ $doc->expires_at->isPast() ? 'text-red-600 font-medium' : '' }}">
                                                {{ $doc->expires_at->format('d/m/Y') }}
                                                @if($doc->expires_at->isPast())
                                                    <span class="text-xs">(vencido)</span>
                                                @endif
                                            </span>
                                        @else
                                            —
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('assets.requirements.documents.preview', [$asset, $requirement, $doc]) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>
                                <a href="{{ route('assets.requirements.documents.download', [$asset, $requirement, $doc]) }}"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Descargar
                                </a>
                                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                    <button type="button"
                                            onclick="openDeleteModal(
                                                '{{ route('assets.requirements.documents.destroy', [$asset, $requirement, $doc]) }}',
                                                @js($doc->original_name ?? basename($doc->file_path)),
                                                '{{ $doc->version_number ?? '—' }}'
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
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-6 text-gray-500 text-sm text-center">
                    No hay versiones registradas en el historial.
                </div>
            @endif
        </div>
    </div>

    {{-- Modal eliminar --}}
    <div id="deleteModal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="p-6 border-b">
                <h3 class="text-lg font-bold text-gray-900">Confirmar eliminación</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Úsala solo si el archivo fue cargado por error. Esta acción no puede deshacerse.
                </p>
            </div>
            <div class="p-6 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <div><span class="font-semibold">Archivo:</span> <span id="deleteFileName">—</span></div>
                    <div class="mt-1"><span class="font-semibold">Versión:</span> <span id="deleteVersion">—</span></div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Escribe <span class="font-bold">ELIMINAR</span> para confirmar
                    </label>
                    <input id="deleteConfirmInput" type="text"
                           class="block w-full rounded-md border-gray-300 focus:border-red-600 focus:ring-red-600 text-sm"
                           placeholder="ELIMINAR"
                           oninput="validateDelete()">
                </div>
                <form id="deleteForm" method="POST" class="flex items-center justify-end gap-3">
                    @csrf
                    @method('DELETE')
                    <button type="button" onclick="closeDeleteModal()"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button id="deleteSubmitBtn" type="submit" disabled
                            class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold opacity-50 cursor-not-allowed">
                        Confirmar eliminación
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(url, name, version) {
            document.getElementById('deleteForm').action = url;
            document.getElementById('deleteFileName').textContent = name || '—';
            document.getElementById('deleteVersion').textContent = version || '—';
            document.getElementById('deleteConfirmInput').value = '';
            const btn = document.getElementById('deleteSubmitBtn');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            const modal = document.getElementById('deleteModal');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('deleteConfirmInput').focus();
        }
        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
        function validateDelete() {
            const valid = document.getElementById('deleteConfirmInput').value.trim() === 'ELIMINAR';
            const btn = document.getElementById('deleteSubmitBtn');
            btn.disabled = !valid;
            btn.classList.toggle('opacity-50', !valid);
            btn.classList.toggle('cursor-not-allowed', !valid);
        }
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteModal(); });
    </script>

</x-layouts.vigia>
