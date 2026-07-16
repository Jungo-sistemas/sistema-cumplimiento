<x-layouts.vigia :title="'Bóveda'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Energético</span>
    </x-slot>

    @php
        $user = auth()->user();
        $showCompanyColumn = $user->hasGroupScope();
        $filtersGridClass = $showCompanyColumn ? 'md:grid-cols-7' : 'md:grid-cols-6';
    @endphp

    {{-- License limit alert --}}
    @if(session('license_limit'))
        <div class="mb-4 rounded-lg border border-orange-200 bg-orange-50 p-4 flex items-start gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 shrink-0 text-orange-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <div>
                <p class="font-semibold text-orange-800 text-sm">Límite de licencia alcanzado</p>
                <p class="text-sm text-orange-700 mt-0.5">
                    Has alcanzado el número máximo de activos permitidos en tu plan actual.
                    Para agregar más activos, comunícate con tu administrador para actualizar tu licencia.
                </p>
            </div>
        </div>
    @endif

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Lista de activos</h1>

        @can('create', App\Models\Asset::class)
            @if(!($licenseInfo['at_limit'] ?? false))
                <a href="{{ route('assets.create', array_filter(['company_id' => request('company_id', $selectedCompanyId ?? null)])) }}"
                   class="bg-[#1A428A] text-white px-4 py-2 rounded-md font-semibold hover:bg-[#15356d]">
                    + Nuevo activo
                </a>
            @else
                <span class="px-4 py-2 rounded-md bg-gray-100 text-gray-400 font-semibold text-sm cursor-not-allowed"
                      title="Límite de licencia alcanzado">
                    + Nuevo activo
                </span>
            @endif
        @endcan
    </div>

    {{-- License usage bar (only when limit is set) --}}
    @if(!is_null($licenseInfo['limit'] ?? null))
        @php
            $pct   = $licenseInfo['percent'];
            $color = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-orange-400' : 'bg-[#1A428A]');
            $scope = $licenseInfo['scope'] === 'group' ? 'del grupo' : 'de la empresa';
        @endphp
        <div class="mt-3 mb-1">
            <div class="flex items-center justify-between text-xs text-gray-500 mb-1">
                <span>Activos {{ $scope }}: <span class="font-semibold text-gray-700">{{ $licenseInfo['current'] }} / {{ $licenseInfo['limit'] }}</span></span>
                <span class="{{ $pct >= 100 ? 'text-red-600 font-semibold' : ($pct >= 80 ? 'text-orange-600 font-semibold' : '') }}">
                    {{ $pct >= 100 ? 'Límite alcanzado' : $licenseInfo['remaining'].' disponibles' }}
                </span>
            </div>
            <div class="h-1.5 w-full rounded-full bg-gray-200">
                <div class="h-1.5 rounded-full {{ $color }} transition-all"
                     style="width: {{ $pct }}%"></div>
            </div>
        </div>
    @endif

    <form method="GET"
          action="{{ route('assets.index') }}"
          class="grid grid-cols-1 {{ $filtersGridClass }} gap-4 items-end">

        @if($showCompanyColumn)
            <div x-data="{
                    grupo: '{{ $filterGrupo }}',
                    otraId: '{{ $filterOtraId }}'
                }"
                class="flex flex-col gap-1"
            >
                <label class="block text-xs text-gray-500">Empresa</label>

                {{-- Selector principal: empresas normales + opción "Otras" --}}
                <select
                    x-model="grupo"
                    @change="otraId = ''"
                    class="w-full rounded-md border-gray-300 text-sm"
                >
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                    @if($otrasCompanies->isNotEmpty())
                        <option value="otras">Otras</option>
                    @endif
                </select>

                {{-- Sub-filtro: aparece solo al seleccionar "Otras" --}}
                <div x-show="grupo === 'otras'" x-transition style="display:none">
                    <select
                        x-model="otraId"
                        class="w-full rounded-md border-gray-300 text-sm"
                    >
                        <option value="">Todas las otras</option>
                        @foreach($otrasCompanies as $company)
                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Inputs ocultos que se envían realmente al servidor --}}
                <input type="hidden" name="company_id" :value="grupo !== 'otras' ? grupo : otraId">
                <input type="hidden" name="otras"      :value="grupo === 'otras' ? '1' : ''">
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