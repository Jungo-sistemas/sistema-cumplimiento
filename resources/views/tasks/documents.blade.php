{{-- resources/views/tasks/documents.blade.php --}}

<x-layouts.vigia
    :title="'Documentos: ' . $task->title"
    :nav-context="$navContext"
>
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset?->company_id)])) }}"
           class="text-gray-600 hover:underline">
            Energético
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.show', $asset) }}" class="text-gray-600 hover:underline">
            {{ $asset->name }}
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.requirements.show', [$asset->id, $requirement->id]) }}"
           class="text-gray-600 hover:underline">
            <x-truncate max="max-w-[400px]" class="font-semibold text-gray-700">
                {{ $requirement->template?->name ?? $requirement->type }}
            </x-truncate>
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('requirements.tasks.show', [$requirement->id, $task->id]) }}"
           class="text-gray-600 hover:underline">
            <x-truncate max="max-w-[180px]">{{ $task->title }}</x-truncate>
        </a>

        <span class="text-gray-400">›</span>

        <span class="text-gray-700 font-medium">
            Documentos
        </span>
    </x-slot>

    @php
        $assetInactive = ($asset->status ?? null) === 'inactive'
            || (method_exists($asset,'isInactive') && $asset->isInactive());
    @endphp

    <div class="bg-white rounded-xl shadow p-6">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    Evidencias: {{ $task->title }}
                </h1>

                <div class="text-sm text-gray-500">
                    Activo:
                    <span class="font-semibold text-gray-700">
                        {{ $asset->name }}
                    </span>

                    · Carpeta:

                    <x-truncate max="max-w-[700px]" class="font-semibold text-gray-700">
                        {{ $requirement->template?->name ?? $requirement->type }}
                    </x-truncate>

                    @if(auth()->user()->hasGroupScope() && $asset->company)
                        · Empresa:
                        <span class="font-semibold text-gray-700">
                            {{ $asset->company->name }}
                        </span>
                    @endif
                </div>

                <span class="inline-flex text-xs px-3 py-1 rounded border
                    {{ $assetInactive ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-green-50 text-green-700 border-green-200' }}">
                    {{ $assetInactive ? 'ASSET SIN OPERACIÓN' : 'ASSET OPERANDO' }}
                </span>
            </div>

            <div class="flex items-center gap-2">
                <a href="{{ route('assets.requirements.show', [$asset,$requirement]) }}"
                   class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                    Volver a la carpeta
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="mt-6 space-y-3">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif
        </div>

        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Subir evidencia --}}
            @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                <div class="bg-white border rounded-xl overflow-hidden">
                    <div class="p-5 border-b">
                        <div class="font-semibold text-[#1A428A]">
                            Subir evidencia
                        </div>

                        <div class="text-sm text-gray-500">
                            Sube un archivo como evidencia para esta tarea.
                        </div>
                    </div>

                    <div class="p-5">
                        @if($assetInactive)
                            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                                Este activo está desactivado. No puedes subir evidencias.
                            </div>
                        @else
                            <form method="POST"
                                  action="{{ route('tasks.documents.store',$task) }}"
                                  enctype="multipart/form-data"
                                  class="space-y-4">

                                @csrf

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Archivo
                                    </label>

                                    <input type="file"
                                           name="file"
                                           accept=".pdf,.jpg,.jpeg,.png,.zip"
                                           class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                                           required>

                                    @error('file')
                                        <div class="text-sm text-red-600 mt-1">
                                            {{ $message }}
                                        </div>
                                    @enderror

                                    <div class="text-xs text-gray-500 mt-1">
                                        Tamaño máximo: 50MB.
                                    </div>
                                </div>

                                <button type="submit"
                                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                                    Subir
                                </button>
                            </form>
                        @endif
                    </div>
                </div>
            @endif

            {{-- Lista documentos --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b flex items-center justify-between">
                    <div>
                        <div class="font-semibold text-[#1A428A]">
                            Documentos
                        </div>

                        <div class="text-sm text-gray-500">
                            {{ $task->documents->count() }} archivo(s)
                        </div>
                    </div>
                </div>

                <div class="p-5 space-y-3">
                    @forelse($task->documents as $doc)
                        <div class="border rounded-xl p-4 flex items-center justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate">
                                    {{ $doc->original_name ?? basename($doc->file_path) }}
                                </div>

                                <div class="text-sm text-gray-500 mt-1">
                                    Subido por: {{ $doc->uploader?->name ?? '—' }}
                                    · {{ $doc->created_at->format('Y-m-d H:i') }}
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('tasks.documents.preview', [$task,$doc]) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>

                                <a href="{{ route('documents.download',$doc) }}"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Descargar
                                </a>

                                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                    <form method="POST"
                                          action="{{ route('documents.destroy',$doc) }}"
                                          onsubmit="return confirm('¿Eliminar este documento?')">

                                        @csrf
                                        @method('DELETE')

                                        <button type="submit"
                                            class="px-3 py-2 rounded-md font-semibold text-sm
                                            {{ $assetInactive ? 'bg-gray-100 text-gray-500 border border-gray-300 cursor-not-allowed' : 'bg-[#DB0000] text-white hover:bg-red-700' }}"
                                            {{ $assetInactive ? 'disabled' : '' }}>
                                            Eliminar
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Aún no hay documentos.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</x-layouts.vigia>