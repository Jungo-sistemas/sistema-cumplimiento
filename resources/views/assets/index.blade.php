<x-layouts.vigia :title="'Bóveda'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Activos y Actividades</span>
    </x-slot>

    @php
        $user = auth()->user();
        $showCompanyColumn = $user->hasGroupScope();
        $filtersGridClass = $showCompanyColumn ? 'md:grid-cols-7' : 'md:grid-cols-6';
    @endphp

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Lista de activos</h1>

        @can('create', App\Models\Asset::class)
            <a href="{{ route('assets.create', array_filter(['company_id' => request('company_id', $selectedCompanyId ?? null)])) }}"
               class="bg-[#1A428A] text-white px-4 py-2 rounded-md font-semibold hover:bg-[#15356d]">
                + Nuevo activo
            </a>
        @endcan
    </div>

    <form method="GET"
          action="{{ route('assets.index') }}"
          class="grid grid-cols-1 {{ $filtersGridClass }} gap-4 items-end">

        @if($showCompanyColumn)
            <div>
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

        <div>
            <label class="block text-xs text-gray-500 mb-1">Estatus</label>
            <select name="status" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>
                <option value="active" @selected(request('status') === 'active')>Operación</option>
                <option value="inactive" @selected(request('status') === 'inactive')>Sin operación</option>
            </select>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Tipo</label>
            <select name="asset_type_id" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>

                @foreach($assetTypes as $type)
                    <option value="{{ $type->id }}"
                        @selected((string) request('asset_type_id') === (string) $type->id)>
                        {{ $type->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Ubicación</label>
            <select name="location" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todas</option>

                @foreach($locations as $loc)
                    <option value="{{ $loc }}" @selected(request('location') === $loc)>
                        {{ $loc }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text"
                   name="q"
                   value="{{ request('q') }}"
                   placeholder="Nombre del activo..."
                   class="w-full rounded-md border-gray-300 text-sm">
        </div>

        <div>
            <a href="{{ route('assets.index') }}"
               class="w-full inline-flex justify-center px-4 py-2 rounded-md border bg-white text-gray-700 font-semibold hover:bg-gray-50">
                Limpiar
            </a>
        </div>

        <div>
            <button type="submit"
                    class="w-full px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                Filtrar
            </button>
        </div>
    </form>

    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold">Nombre</th>

                        @if($showCompanyColumn)
                            <th class="text-left px-6 py-3 font-semibold">Empresa</th>
                        @endif

                        <th class="text-left px-6 py-3 font-semibold">Tipo</th>
                        <th class="text-left px-6 py-3 font-semibold">Responsable</th>
                        <th class="text-left px-6 py-3 font-semibold">Creado</th>
                        <th class="text-left px-6 py-3 font-semibold">Ubicación</th>
                        <th class="text-left px-6 py-3 font-semibold">Fecha de Creación</th>
                        <th class="text-right px-6 py-3 font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $asset)
                        <tr class="border-t">
                            <td class="px-6 py-3">
                                <div class="font-semibold text-gray-800">{{ $asset->name }}</div>
                                <div class="text-xs text-gray-500">
                                    {{ $asset->status === 'active' ? 'OPERACIÓN' : 'SIN OPERACIÓN' }}
                                </div>
                            </td>

                            @if($showCompanyColumn)
                                <td class="px-6 py-3 text-gray-700">
                                    {{ $asset->company->name ?? '-' }}
                                </td>
                            @endif

                            <td class="px-6 py-3 text-gray-700">
                                {{ $asset->type->name ?? '-' }}
                            </td>

                            <td class="px-6 py-3 text-gray-700">
                                {{ $asset->responsibleUser->name ?? '-' }}
                            </td>

                            <td class="px-6 py-3 text-gray-700">
                                {{ $asset->creator->name ?? 'Sistema' }}
                            </td>

                            <td class="px-6 py-3 text-gray-600 text-sm">
                                {{ $asset->location ?? '-' }}
                            </td>

                            <td class="px-6 py-3 text-gray-600 text-sm">
                                {{ $asset->created_at?->format('Y-m-d') }}
                            </td>

                            <td class="px-6 py-3 text-right">
                                <a href="{{ route('assets.show', $asset) }}"
                                   class="text-blue-600 hover:underline font-semibold">
                                    Ver
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="{{ $showCompanyColumn ? 8 : 7 }}" class="px-6 py-6 text-center text-gray-500">
                                No hay activos para este filtro.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="mt-6">
        @if($assets->hasPages())
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                <div class="text-sm text-gray-500 whitespace-nowrap">
                    {{ $assets->firstItem() }} a {{ $assets->lastItem() }}
                    de {{ $assets->total() }} resultados
                </div>

                <div class="flex items-center gap-2">
                    @if($assets->onFirstPage())
                        <span class="px-3 py-2 rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed">
                            ←
                        </span>
                    @else
                        <a href="{{ $assets->previousPageUrl() }}"
                           class="px-3 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                            ←
                        </a>
                    @endif

                    @php
                        $current = $assets->currentPage();
                        $last = $assets->lastPage();
                        $start = max(1, $current - 1);
                        $end = min($last, $current + 1);
                    @endphp

                    @if($start > 1)
                        <a href="{{ $assets->url(1) }}"
                           class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                            1
                        </a>

                        @if($start > 2)
                            <span class="px-2 text-gray-400">…</span>
                        @endif
                    @endif

                    @for($page = $start; $page <= $end; $page++)
                        @if($page == $current)
                            <span class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold border border-[#1A428A]">
                                {{ $page }}
                            </span>
                        @else
                            <a href="{{ $assets->url($page) }}"
                               class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                                {{ $page }}
                            </a>
                        @endif
                    @endfor

                    @if($end < $last)
                        @if($end < $last - 1)
                            <span class="px-2 text-gray-400">…</span>
                        @endif

                        <a href="{{ $assets->url($last) }}"
                           class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                            {{ $last }}
                        </a>
                    @endif

                    @if($assets->hasMorePages())
                        <a href="{{ $assets->nextPageUrl() }}"
                           class="px-3 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                            →
                        </a>
                    @else
                        <span class="px-3 py-2 rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed">
                            →
                        </span>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-layouts.vigia>