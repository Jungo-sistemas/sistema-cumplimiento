<x-layouts.vigia title="Papelera de Documentos">

    <x-slot name="breadcrumb">
        <a href="{{ route('documents.index') }}" class="text-gray-500 hover:underline">Documentos</a>
        <span class="mx-1 text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Papelera</span>
    </x-slot>

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-red-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-semibold text-gray-800">Papelera de Documentos</h1>
                <p class="text-sm text-gray-500 mt-0.5">Los documentos se eliminan permanentemente después de 2 meses. Solo visible para administradores.</p>
            </div>
        </div>

        <a href="{{ route('documents.index') }}"
           class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">
            Volver a Documentos
        </a>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA --}}
    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="bg-gray-50 text-gray-600 border-b">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">Documento</th>
                        <th class="text-left px-4 py-3 font-semibold">Ubicación original</th>
                        <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                        <th class="text-left px-4 py-3 font-semibold">Cargado por</th>
                        <th class="text-left px-4 py-3 font-semibold">Eliminado por</th>
                        <th class="text-left px-4 py-3 font-semibold">Fecha de eliminación</th>
                        <th class="text-left px-4 py-3 font-semibold">Eliminación permanente</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($documents as $document)
                        @php
                            $daysLeft = $document->permanently_delete_at
                                ? (int) now()->diffInDays($document->permanently_delete_at, false)
                                : null;
                            $firstVersion = $document->versions->last();
                        @endphp

                        <tr class="border-t hover:bg-gray-50">

                            {{-- Documento --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $document->name }}</div>
                                @if($document->document_type)
                                    <span class="text-xs text-gray-500">{{ $document->document_type }}</span>
                                @endif
                                @if($document->reference)
                                    <div class="text-xs text-gray-400 mt-0.5">Ref: {{ $document->reference }}</div>
                                @endif
                                <div class="mt-1 text-xs text-gray-400">
                                    {{ $document->versions->count() }} versión(es) almacenada(s)
                                </div>
                            </td>

                            {{-- Ubicación original --}}
                            <td class="px-4 py-3 text-gray-600">
                                @if($document->folder)
                                    @if($document->folder->parent)
                                        <span class="text-gray-400 text-xs">{{ $document->folder->parent->name }}</span>
                                        <div class="font-medium text-gray-700">{{ $document->folder->name }}</div>
                                    @else
                                        <span class="font-medium text-gray-700">{{ $document->folder->name }}</span>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic">Carpeta eliminada</span>
                                @endif
                            </td>

                            {{-- Empresa --}}
                            <td class="px-4 py-3 text-gray-600">
                                {{ $document->company?->name ?? '—' }}
                            </td>

                            {{-- Cargado por --}}
                            <td class="px-4 py-3">
                                @if($document->uploader)
                                    <div class="text-gray-800 font-medium">{{ $document->uploader->name }}</div>
                                @elseif($firstVersion?->uploader)
                                    <div class="text-gray-800 font-medium">{{ $firstVersion->uploader->name }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                                @if($firstVersion?->created_at)
                                    <div class="text-xs text-gray-400 mt-0.5">
                                        {{ $firstVersion->created_at->format('d/m/Y') }}
                                    </div>
                                @endif
                            </td>

                            {{-- Eliminado por --}}
                            <td class="px-4 py-3">
                                @if($document->deletedBy)
                                    <div class="text-gray-800 font-medium">{{ $document->deletedBy->name }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Fecha de eliminación --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $document->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>

                            {{-- Eliminación permanente --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($document->permanently_delete_at)
                                    <div class="font-medium
                                        @if($daysLeft !== null && $daysLeft <= 7) text-red-600
                                        @elseif($daysLeft !== null && $daysLeft <= 30) text-yellow-600
                                        @else text-gray-700
                                        @endif">
                                        {{ $document->permanently_delete_at->format('d/m/Y') }}
                                    </div>
                                    @if($daysLeft !== null && $daysLeft > 0)
                                        <div class="text-xs mt-0.5
                                            @if($daysLeft <= 7) text-red-500
                                            @elseif($daysLeft <= 30) text-yellow-600
                                            @else text-gray-400
                                            @endif">
                                            En {{ $daysLeft }} día(s)
                                        </div>
                                    @elseif($daysLeft !== null && $daysLeft <= 0)
                                        <div class="text-xs text-red-500 mt-0.5">Pendiente de purgar</div>
                                    @endif
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">

                                    {{-- Restaurar --}}
                                    <form method="POST"
                                          action="{{ route('documents.trash.restore', $document->id) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-green-600 text-white text-xs font-semibold hover:bg-green-700"
                                                onclick="return confirm('¿Restaurar el documento \"{{ addslashes($document->name) }}\"?')">
                                            Restaurar
                                        </button>
                                    </form>

                                    {{-- Eliminar permanentemente --}}
                                    <form method="POST"
                                          action="{{ route('documents.trash.force-destroy', $document->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-red-600 text-white text-xs font-semibold hover:bg-red-700"
                                                onclick="return confirm('Esta acción es irreversible. ¿Eliminar permanentemente el documento \"{{ addslashes($document->name) }}\" y todos sus archivos?')">
                                            Eliminar ahora
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="8" class="px-6 py-10 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10 text-gray-300"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                    </svg>
                                    <span>La papelera está vacía.</span>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>
    </div>

</x-layouts.vigia>
