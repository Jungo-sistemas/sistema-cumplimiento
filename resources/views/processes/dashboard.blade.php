<x-layouts.vigia title="Tablero · Procesos">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Tablero</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Tablero de procesos</h1>

        {{-- Cards --}}
        <div class="mt-6 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-[#1A428A]">Total documentos</div>
                <div class="mt-2 text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
            </div>

            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-[#1A428A]">Aprobados</div>
                <div class="mt-2 text-2xl font-bold text-gray-800">{{ $stats['approved'] }}</div>
            </div>

            <div class="bg-[#FFB529] rounded-lg shadow-sm p-4 text-white">
                <div class="text-sm font-semibold">En revisión</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['in_review'] }}</div>
            </div>

            @if($stats['pending_me'] > 0)
                <div class="bg-[#DB0000] rounded-lg shadow-sm p-4 text-white">
                    <div class="text-sm font-semibold">Pendientes de mi aprobación</div>
                    <div class="mt-2 text-2xl font-bold">{{ $stats['pending_me'] }}</div>
                </div>
            @else
                <div class="bg-white border rounded-lg shadow-sm p-4">
                    <div class="text-sm font-semibold text-[#1A428A]">Pendientes de mi aprobación</div>
                    <div class="mt-2 text-2xl font-bold text-gray-400">0</div>
                </div>
            @endif
        </div>

        {{-- Pendientes de mi aprobación --}}
        @if($pendingApprovals->isNotEmpty())
            <div class="mt-8">
                <div class="text-sm font-semibold text-[#DB0000] mb-3">Requieren tu aprobación</div>
                <div class="border rounded-lg overflow-hidden">
                    @foreach($pendingApprovals as $approval)
                        <div class="p-4 border-b last:border-b-0 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-800">
                                    {{ $approval->regulation?->name ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $approval->regulation?->company?->name ?? '—' }}
                                    @if($approval->regulation?->processType)
                                        · {{ $approval->regulation->processType->name }}
                                    @endif
                                    · Paso {{ $approval->step_number }}
                                </div>
                            </div>
                            <a href="{{ route('processes.show', $approval->regulation) }}"
                               class="shrink-0 text-sm font-medium text-[#1A428A] hover:underline">
                                Revisar
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Reglamentos recientes --}}
        <div class="mt-8">
            <div class="text-sm font-semibold text-gray-700 mb-3">Documentos recientes</div>
            <div class="border rounded-lg overflow-hidden">
                @forelse($recent as $regulation)
                    <div class="p-4 border-b last:border-b-0 flex items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold text-gray-800">{{ $regulation->name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $regulation->company?->name ?? '—' }}
                                @if($regulation->processType)
                                    · {{ $regulation->processType->name }}
                                @endif
                                @if($regulation->approval_status)
                                    · <span class="capitalize">{{ str_replace('_', ' ', $regulation->approval_status) }}</span>
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('processes.show', $regulation) }}"
                           class="shrink-0 text-sm font-medium text-[#1A428A] hover:underline">
                            Abrir
                        </a>
                    </div>
                @empty
                    <div class="p-4 text-sm text-gray-500">No hay documentos registrados.</div>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.vigia>
