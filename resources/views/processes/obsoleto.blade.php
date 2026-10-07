<x-layouts.vigia title="Procedimientos obsoletos">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-600 hover:underline">Procesos</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Obsoleto</span>
    </x-slot>

    @php $user = auth()->user(); @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">Obsoleto</h1>
            <p class="text-sm text-gray-500 mt-0.5">Regulaciones vencidas y versiones anteriores reemplazadas</p>
        </div>
        <a href="{{ route('processes.index') }}"
           class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold text-sm hover:bg-blue-50">
            Volver a Procesos
        </a>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('processes.obsoleto') }}"
          class="mt-4 flex flex-wrap items-end gap-3">
        <div class="min-w-[220px] flex-1">
            <label class="block text-xs text-gray-500 mb-1">Buscar procedimiento</label>
            <input type="text" name="q" value="{{ $search }}" placeholder="Nombre o código..."
                   class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
        </div>
        <div class="min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1">Tipo de proceso</label>
            <select name="process_type_id"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                <option value="">Todos los tipos</option>
                @foreach($processTypes as $pt)
                    <option value="{{ $pt->id }}" @selected((string) $selectedProcessTypeId === (string) $pt->id)>
                        {{ $pt->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @if($user->hasGroupScope())
        <div class="min-w-[180px]">
            <label class="block text-xs text-gray-500 mb-1">Empresa</label>
            <select name="company_id"
                    class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                <option value="">Todas las empresas</option>
                @foreach($companies as $c)
                    <option value="{{ $c->id }}" @selected((string) $selectedCompanyId === (string) $c->id)>
                        {{ $c->name }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif
        <button type="submit"
                class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
            Filtrar
        </button>
        <a href="{{ route('processes.obsoleto') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

    {{-- SECCIÓN 1: Regulaciones vencidas --}}
    <div class="mt-8">
        <div class="flex items-center gap-2 mb-3">
            <div class="flex h-7 w-7 items-center justify-center rounded-lg bg-red-100 shrink-0">
                <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <h2 class="text-base font-semibold text-gray-800">Regulaciones vencidas</h2>
            <span class="text-xs text-gray-400 font-normal">(versión actual con vigencia expirada y sin renovar)</span>
            <span class="ml-auto text-xs font-medium text-gray-500">{{ $expiredRegulations->count() }}</span>
        </div>

        @if($expiredRegulations->isEmpty())
            <div class="rounded-xl border border-dashed border-gray-200 bg-white px-6 py-8 text-center">
                <p class="text-sm text-gray-400">No hay regulaciones vencidas{{ $selectedCompanyId ? ' para esta empresa' : '' }}.</p>
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50">
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Procedimiento</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Código</th>
                            @if($user->hasGroupScope())
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Empresa</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Tipo de proceso</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Versión</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Venció</th>
                            <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wide">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($expiredRegulations as $reg)
                        @php $cv = $reg->currentVersion; @endphp
                        <tr class="hover:bg-gray-50 transition">
                            <td class="px-4 py-3 font-medium text-gray-800">{{ $reg->name }}</td>
                            <td class="px-4 py-3 text-gray-500 font-mono text-xs">{{ $reg->code ?? '—' }}</td>
                            @if($user->hasGroupScope())
                            <td class="px-4 py-3 text-gray-600">{{ $reg->company?->name ?? '—' }}</td>
                            @endif
                            <td class="px-4 py-3 text-gray-600">{{ $reg->processType?->name ?? '—' }}</td>
                            <td class="px-4 py-3 text-gray-600">
                                @if($cv)
                                    <span class="font-medium">v{{ str_pad($cv->version_number, 2, '0', STR_PAD_LEFT) }}</span>
                                @else —
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                @if($cv?->valid_until)
                                    <span class="inline-flex items-center gap-1 text-xs px-2 py-0.5 rounded-full bg-red-50 text-red-700 border border-red-200 font-medium">
                                        {{ $cv->valid_until->format('d/m/Y') }}
                                    </span>
                                @else —
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    @if($cv)
                                        <a href="{{ route('regulation-versions.preview', $cv) }}"
                                           target="_blank"
                                           class="inline-flex items-center gap-1 whitespace-nowrap text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:border-[#1A428A] hover:text-[#1A428A] transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                            </svg>
                                            Ver
                                        </a>
                                        <a href="{{ route('regulation-versions.download', $cv) }}"
                                           class="inline-flex items-center gap-1 whitespace-nowrap text-xs px-2.5 py-1.5 rounded-md border border-gray-200 text-gray-600 hover:border-[#1A428A] hover:text-[#1A428A] transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Descargar
                                        </a>
                                    @endif
                                    <a href="{{ route('processes.show', $reg) }}"
                                       class="inline-flex items-center gap-1 whitespace-nowrap text-xs px-2.5 py-1.5 rounded-md bg-[#1A428A] text-white hover:bg-[#15356d] transition">
                                        Ir al proceso
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                        </svg>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

</x-layouts.vigia>
