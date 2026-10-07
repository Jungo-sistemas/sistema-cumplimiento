<x-layouts.vigia title="Papelera de Procesos">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
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
                <h1 class="text-2xl font-semibold text-gray-800">Papelera de Procesos</h1>
                <p class="text-sm text-gray-500 mt-0.5">Los procedimientos se eliminan permanentemente después de 2 meses. Solo visible para administradores.</p>
            </div>
        </div>

        <a href="{{ route('processes.index') }}"
           class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 text-sm font-semibold hover:bg-gray-50">
            Volver a Procesos
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
                        <th class="text-left px-4 py-3 font-semibold">Procedimiento</th>
                        <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                        <th class="text-left px-4 py-3 font-semibold">Creado por</th>
                        <th class="text-left px-4 py-3 font-semibold">Eliminado por</th>
                        <th class="text-left px-4 py-3 font-semibold">Fecha de eliminación</th>
                        <th class="text-left px-4 py-3 font-semibold">Eliminación permanente</th>
                        <th class="text-left px-4 py-3 font-semibold">Acciones</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($regulations as $regulation)
                        @php
                            $daysLeft = $regulation->permanently_delete_at
                                ? (int) now()->diffInDays($regulation->permanently_delete_at, false)
                                : null;
                        @endphp

                        <tr class="border-t hover:bg-gray-50">

                            {{-- Procedimiento --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $regulation->name }}</div>
                                <span class="text-xs text-gray-500">
                                    {{ $regulation->code ?? 'Sin código' }} · {{ $regulation->document_type ?? '—' }}
                                </span>
                                <div class="mt-1 text-xs text-gray-400">
                                    {{ $regulation->versions->count() }} versión(es) almacenada(s)
                                </div>
                            </td>

                            {{-- Empresa --}}
                            <td class="px-4 py-3 text-gray-600">
                                {{ $regulation->company?->name ?? '—' }}
                            </td>

                            {{-- Creado por --}}
                            <td class="px-4 py-3">
                                @if($regulation->creator)
                                    <div class="text-gray-800 font-medium">{{ $regulation->creator->name }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Eliminado por --}}
                            <td class="px-4 py-3">
                                @if($regulation->deletedBy)
                                    <div class="text-gray-800 font-medium">{{ $regulation->deletedBy->name }}</div>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>

                            {{-- Fecha de eliminación --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $regulation->deleted_at?->format('d/m/Y H:i') ?? '—' }}
                            </td>

                            {{-- Eliminación permanente --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if($regulation->permanently_delete_at)
                                    <div class="font-medium
                                        @if($daysLeft !== null && $daysLeft <= 7) text-red-600
                                        @elseif($daysLeft !== null && $daysLeft <= 30) text-yellow-600
                                        @else text-gray-700
                                        @endif">
                                        {{ $regulation->permanently_delete_at->format('d/m/Y') }}
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
                                          action="{{ route('processes.trash.restore', $regulation->id) }}">
                                        @csrf
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d]"
                                                onclick="return confirm('¿Restaurar el procedimiento \"{{ addslashes($regulation->name) }}\"?')">
                                            Restaurar
                                        </button>
                                    </form>

                                    {{-- Eliminar permanentemente --}}
                                    <form method="POST"
                                          action="{{ route('processes.trash.force-destroy', $regulation->id) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="px-3 py-1.5 rounded-md border border-gray-300 bg-white text-gray-700 text-xs font-semibold hover:bg-gray-50"
                                                onclick="return confirm('Esta acción es irreversible. ¿Eliminar permanentemente el procedimiento \"{{ addslashes($regulation->name) }}\" y todos sus archivos?')">
                                            Eliminar
                                        </button>
                                    </form>

                                </div>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="7" class="px-6 py-10 text-center text-gray-500">
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
