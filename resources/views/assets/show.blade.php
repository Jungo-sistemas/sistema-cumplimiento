{{-- resources/views/assets/show.blade.php --}}
<x-layouts.vigia :title="$asset->name" :nav-context="$navContext">

    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset->company_id)])) }}"
        class="text-gray-600 hover:underline">
            Activos y Actividades
        </a>

        <span class="text-gray-400">›</span>

        <span class="text-gray-700 font-medium">
            {{ $asset->name }}
        </span>
    </x-slot>

    @php
        $activeSearch = $search ?? request('search');
        $activeAuthority = $authority ?? request('authority');
        $activeRisk = $risk ?? request('risk');
        $activeStatus = $status ?? request('status');

        $hasActiveFilters =
            filled($activeSearch) ||
            filled($activeAuthority) ||
            filled($activeRisk) ||
            filled($activeStatus);
    @endphp

    <div class="bg-white rounded-xl shadow p-6 space-y-8">

        {{-- ================= HEADER ================= --}}
        <div class="flex items-start justify-between flex-wrap gap-6">
            <div>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-3xl font-bold text-[#1A428A]">
                        {{ $asset->name }}
                    </h1>

                    <span class="text-xs px-3 py-1 rounded border
                        {{ $assetInactive
                            ? 'bg-gray-100 text-gray-700 border-gray-300'
                            : 'bg-green-50 text-green-700 border-green-200' }}">
                        {{ $assetInactive ? 'SIN OPERACIÓN' : 'OPERANDO' }}
                    </span>
                </div>

                @if(!empty($asset->code))
                    <div class="text-sm text-gray-500 mt-1">
                        Código: {{ $asset->code }}
                    </div>
                @endif
            </div>

            @can('update', $asset)
                <div class="flex items-center gap-3">
                    <a href="{{ route('assets.edit', array_merge(['asset' => $asset], array_filter(['company_id' => request('company_id', $asset->company_id)]))) }}"
                    class="px-5 py-2 rounded-md border border-[#1A428A] text-[#1A428A] font-semibold hover:bg-blue-50
                    {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                        Editar
                    </a>

                    @if($assetInactive)
                        <form method="POST" action="{{ route('assets.activate', $asset) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="px-6 py-2 rounded-md font-semibold text-white bg-[#1A428A] hover:bg-[#15356d]">
                                Volver a Operar
                            </button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('assets.deactivate', $asset) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                onclick="return confirm('¿Seguro que quieres desactivar este activo?');"
                                class="px-6 py-2 rounded-md font-semibold text-white bg-[#DB0000] hover:bg-red-700">
                                Sin Operación
                            </button>
                        </form>
                    @endif
                </div>
            @endcan
        </div>

        {{-- ================= RESUMEN ================= --}}
        <div class="bg-gray-50 border rounded-xl px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm">
                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Tipo
                    </div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $asset->assetType->name ?? '-' }}
                    </div>
                </div>

                <div class="md:border-l md:border-gray-200 md:pl-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Ubicación
                    </div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $asset->location ?? '-' }}
                    </div>
                </div>

                <div class="md:border-l md:border-gray-200 md:pl-6">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        Responsable
                    </div>
                    <div class="mt-1 text-base font-semibold text-gray-900">
                        {{ $asset->responsible->name ?? '-' }}
                    </div>
                </div>
            </div>
        </div>

        {{-- ================= RELACIÓN CON OTROS ACTIVOS ================= --}}
        @php
            $assetTypeName = \Illuminate\Support\Str::lower(trim($asset->assetType->name ?? ''));
            $isParentType = in_array($assetTypeName, ['plantas', 'transporte']);
            $hasParent = !is_null($asset->parent);
            $childrenCount = $asset->children->count();
        @endphp

        @if($hasParent || $isParentType)
            <div class="bg-white border rounded-xl p-6 space-y-4">
                <div>
                    <div class="font-semibold text-[#1A428A] text-lg">
                        Relación con otros activos
                    </div>
                    <div class="text-sm text-gray-500">
                        Consulta la relación de este activo con otros activos del sistema.
                    </div>
                </div>

                {{-- Si es hijo, mostrar solo padre --}}
                @if($hasParent)
                    @php
                        $parentInactive = !is_null($asset->parent->inactive_at);
                    @endphp

                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                        <div class="flex items-start justify-between gap-4 flex-wrap">
                            <div>
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                    Activo principal
                                </div>

                                <div class="mt-3 flex items-center gap-3 flex-wrap">
                                    <a href="{{ route('assets.show', $asset->parent) }}"
                                    class="text-lg font-semibold text-[#1A428A] hover:underline">
                                        {{ $asset->parent->name }}
                                    </a>

                                    <span class="text-xs px-3 py-1 rounded border
                                        {{ $parentInactive
                                            ? 'bg-gray-100 text-gray-700 border-gray-300'
                                            : 'bg-green-50 text-green-700 border-green-200' }}">
                                        {{ $parentInactive ? 'SIN OPERACIÓN' : 'OPERANDO' }}
                                    </span>
                                </div>
                            </div>

                            <div>
                                <a href="{{ route('assets.show', $asset->parent) }}"
                                class="inline-flex items-center px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] font-semibold hover:bg-blue-50 whitespace-nowrap">
                                    Abrir activo principal
                                </a>
                            </div>
                        </div>
                    </div>

                {{-- Si es padre, mostrar solo hijos --}}
                @elseif($isParentType)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-5">
                        <div class="flex items-center justify-between gap-4 flex-wrap">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Activos relacionados
                            </div>

                            <div class="text-xs px-3 py-1 rounded border bg-blue-50 text-[#1A428A] border-blue-200">
                                {{ $childrenCount }} {{ \Illuminate\Support\Str::plural('activo', $childrenCount) }}
                            </div>
                        </div>

                        @if($childrenCount > 0)
                            <div class="mt-4 overflow-x-auto">
                                <table class="min-w-full text-sm">
                                    <thead class="bg-white text-gray-600">
                                        <tr>
                                            <th class="text-left px-4 py-3 font-semibold">Activo</th>
                                            <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Tipo</th>
                                            <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Código</th>
                                            <th class="text-left px-4 py-3 font-semibold whitespace-nowrap">Responsable</th>
                                            <th class="text-right px-4 py-3 font-semibold whitespace-nowrap">Acción</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        @foreach($asset->children->take(10) as $child)
                                            <tr class="bg-gray-50 hover:bg-gray-100">
                                                <td class="px-4 py-3 font-medium text-[#1A428A]">
                                                    {{ $child->name }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-700 whitespace-nowrap">
                                                    {{ $child->assetType->name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                                    {{ $child->code ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-gray-500 whitespace-nowrap">
                                                    {{ $child->responsible->name ?? '-' }}
                                                </td>
                                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                                    <a href="{{ route('assets.show', $child) }}"
                                                    class="text-blue-600 hover:underline font-semibold">
                                                        Abrir
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>

                                @if($childrenCount > 10)
                                    <div class="mt-3 text-sm text-gray-500">
                                        Y {{ $childrenCount - 10 }} más...
                                    </div>
                                @endif
                            </div>
                        @else
                            <div class="mt-3 text-sm text-gray-500">
                                Este activo no tiene activos relacionados todavía.
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @endif

        {{-- ================= REQUERIMIENTOS ================= --}}
        <div class="bg-white border rounded-xl overflow-hidden">

            <form method="GET" action="{{ route('assets.show', $asset) }}">
                <div class="p-6 border-b space-y-5">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                        <div>
                            <div class="font-semibold text-[#1A428A] text-lg">
                                Cumplimiento normativo
                            </div>
                            <div class="text-sm text-gray-500">
                                {{ $scopeDescription }}
                            </div>
                        </div>

                        <div class="flex items-center gap-2 flex-wrap">
                            @if($usesCategoryView)
                                @foreach($categoryTabs as $key => $label)
                                    <a href="{{ route('assets.show', ['asset' => $asset, 'scope' => $key]) }}"
                                       class="px-4 py-2 rounded-md border font-semibold whitespace-nowrap
                                       {{ $scope === $key
                                           ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                           : 'bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50' }}">
                                        {{ $label }}
                                    </a>
                                @endforeach
                            @else
                                <a href="{{ route('assets.show', ['asset' => $asset, 'scope' => 'project']) }}"
                                   class="px-4 py-2 rounded-md border font-semibold whitespace-nowrap
                                   {{ $scope === 'project'
                                       ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                       : 'bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50' }}">
                                    Normativa de proyecto
                                </a>
                                <a href="{{ route('assets.show', ['asset' => $asset, 'scope' => 'operation']) }}"
                                   class="px-4 py-2 rounded-md border font-semibold whitespace-nowrap
                                   {{ $scope === 'operation'
                                       ? 'bg-[#1A428A] text-white border-[#1A428A]'
                                       : 'bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50' }}">
                                    Normativa de operación
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="font-semibold text-[#1A428A] text-base">
                        {{ $scopeTitle }}
                    </div>

                    <input type="hidden" name="scope" value="{{ $scope }}">
                    <input type="hidden" name="show_filters" value="{{ $showFilters ? 1 : 0 }}" id="show_filters_input">

                    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
                        <div class="flex items-center gap-3 flex-wrap">
                            <input
                                type="text"
                                name="search"
                                value="{{ $activeSearch }}"
                                placeholder="Buscar requerimiento..."
                                class="w-72 rounded-md border border-gray-300 bg-white px-4 py-2 text-sm text-gray-700 placeholder-gray-400 focus:border-[#1A428A] focus:ring-[#1A428A]"
                            >

                            <button
                                type="submit"
                                class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] whitespace-nowrap"
                            >
                                Buscar
                            </button>

                            <button
                                type="button"
                                onclick="toggleRequirementFilters()"
                                class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50 whitespace-nowrap"
                            >
                                Filtros
                            </button>

                            @if($hasActiveFilters)
                                <a
                                    href="{{ route('assets.show', ['asset' => $asset, 'scope' => $scope, 'clear_filters' => 1]) }}"
                                    class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50 whitespace-nowrap"
                                >
                                    Limpiar
                                </a>
                            @endif
                        </div>

                        @if($requirements->hasPages())
                            <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 xl:justify-end">
                                <div class="text-sm text-gray-500 whitespace-nowrap">
                                    {{ $requirements->firstItem() }} a {{ $requirements->lastItem() }}
                                    de {{ $requirements->total() }} requerimientos
                                </div>

                                <div class="flex items-center gap-2">
                                    @if($requirements->onFirstPage())
                                        <span class="px-3 py-2 rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed">
                                            ←
                                        </span>
                                    @else
                                        <a href="{{ $requirements->previousPageUrl() }}"
                                        class="px-3 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                                            ←
                                        </a>
                                    @endif

                                    @php
                                        $current = $requirements->currentPage();
                                        $last = $requirements->lastPage();
                                        $start = max(1, $current - 1);
                                        $end = min($last, $current + 1);
                                    @endphp

                                    @if($start > 1)
                                        <a href="{{ $requirements->url(1) }}"
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
                                            <a href="{{ $requirements->url($page) }}"
                                            class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                                                {{ $page }}
                                            </a>
                                        @endif
                                    @endfor

                                    @if($end < $last)
                                        @if($end < $last - 1)
                                            <span class="px-2 text-gray-400">…</span>
                                        @endif

                                        <a href="{{ $requirements->url($last) }}"
                                        class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                                            {{ $last }}
                                        </a>
                                    @endif

                                    @if($requirements->hasMorePages())
                                        <a href="{{ $requirements->nextPageUrl() }}"
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

                    <div
                        id="requirement-filters-panel"
                        class="{{ $showFilters ? '' : 'hidden' }} rounded-xl border border-gray-200 bg-gray-50 p-4"
                    >
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Entidad
                                </label>

                                <select
                                    name="authority"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-[#1A428A] focus:ring-[#1A428A]"
                                >
                                    <option value="">Todas</option>
                                    @foreach($authorities as $item)
                                        <option value="{{ $item }}" {{ $activeAuthority === $item ? 'selected' : '' }}>
                                            {{ $item }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Riesgo
                                </label>

                                <select
                                    name="risk"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-[#1A428A] focus:ring-[#1A428A]"
                                >
                                    <option value="">Todos</option>
                                    <option value="normal" {{ $activeRisk === 'normal' ? 'selected' : '' }}>Normal</option>
                                    <option value="warning" {{ $activeRisk === 'warning' ? 'selected' : '' }}>Crítico</option>
                                    <option value="danger" {{ $activeRisk === 'danger' ? 'selected' : '' }}>Peligro</option>
                                </select>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Estado
                                </label>

                                <select
                                    name="status"
                                    class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-[#1A428A] focus:ring-[#1A428A]"
                                >
                                    <option value="">Todos</option>
                                    <option value="missing_document" {{ $activeStatus === 'missing_document' ? 'selected' : '' }}>Falta documento oficial</option>
                                    <option value="pending" {{ $activeStatus === 'pending' ? 'selected' : '' }}>Pendiente</option>
                                    <option value="in_progress" {{ $activeStatus === 'in_progress' ? 'selected' : '' }}>En progreso</option>
                                    <option value="completed" {{ $activeStatus === 'completed' ? 'selected' : '' }}>Completado</option>
                                    <option value="expired" {{ $activeStatus === 'expired' ? 'selected' : '' }}>Vencido</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </form>

            @if($hasActiveFilters)
                <div class="px-6 py-3 bg-blue-50 border-t border-b text-sm text-[#1A428A]">
                    @if(filled($activeSearch))
                        <span>
                            Búsqueda: <span class="font-semibold">{{ $activeSearch }}</span>
                        </span>
                    @endif

                    @if(filled($activeAuthority))
                        <span class="ml-4">
                            Entidad: <span class="font-semibold">{{ $activeAuthority }}</span>
                        </span>
                    @endif

                    @if(filled($activeRisk))
                        <span class="ml-4">
                            Riesgo: <span class="font-semibold">
                                {{ $activeRisk === 'warning' ? 'Crítico' : ($activeRisk === 'danger' ? 'Peligro' : 'Normal') }}
                            </span>
                        </span>
                    @endif

                    @if(filled($activeStatus))
                        <span class="ml-4">
                            Estado: <span class="font-semibold">
                                {{ match($activeStatus) {
                                    'missing_document' => 'Falta documento oficial',
                                    'pending' => 'Pendiente',
                                    'in_progress' => 'En progreso',
                                    'completed' => 'Completado',
                                    'expired' => 'Vencido',
                                    default => $activeStatus,
                                } }}
                            </span>
                        </span>
                    @endif
                </div>
            @endif

            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="text-left px-6 py-4 font-semibold">Carpeta</th>
                            <th class="text-left px-6 py-4 font-semibold whitespace-nowrap">Vence</th>
                            <th class="text-left px-6 py-4 font-semibold whitespace-nowrap">Riesgo</th>
                            <th class="text-left px-6 py-4 font-semibold whitespace-nowrap">Estado</th>
                            <th class="text-left px-6 py-4 font-semibold whitespace-nowrap">Progreso</th>
                            <th class="text-left px-6 py-4 font-semibold whitespace-nowrap">Tareas</th>
                            <th class="text-right px-6 py-4 font-semibold whitespace-nowrap">Acciones</th>
                        </tr>
                    </thead>

                    <tbody class="divide-y">
                        @forelse($requirements as $req)
                            @php
                                $title = $req->template?->name ?? $req->type;
                                $due = ($req->expires_at ?? $req->due_date)?->format('Y-m-d') ?? '-';

                                $tasksTotal = (int) ($req->tasks_total ?? 0);
                                $tasksDone  = (int) ($req->tasks_done ?? 0);

                                $progress = (int) ($req->computed_progress ?? 0);
                                $riskVal = strtolower($req->risk_level ?? 'normal');
                                $statusVal = $req->computed_status ?? 'pending';

                                $renewalPending = (int) ($req->renewal_pending ?? 0);
                                $checkinPending = (int) ($req->checkin_pending ?? 0);

                                if ($statusVal === 'completed' && ($renewalPending || $checkinPending)) {
                                    if ($renewalPending && $checkinPending) {
                                        $statusVal   = 'special_both';
                                        $statusLabel = 'Procesos pendientes';
                                    } elseif ($renewalPending) {
                                        $statusVal   = 'special_renewal';
                                        $statusLabel = 'Renovación';
                                    } else {
                                        $statusVal   = 'special_checkin';
                                        $statusLabel = 'Check out';
                                    }
                                } else {
                                    $statusLabel = match ($statusVal) {
                                        'missing_document' => 'Falta documento oficial',
                                        'pending'          => 'Pendiente',
                                        'in_progress'      => 'En progreso',
                                        'completed'        => 'Completado',
                                        'expired'          => 'Vencido',
                                        default            => \App\Enums\RequirementStatus::tryFrom($statusVal)?->label()
                                                              ?? $req->status?->label()
                                                              ?? 'Pendiente',
                                    };
                                }
                            @endphp

                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 font-semibold text-gray-800">
                                    {{ $title }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $due }}
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @if($riskVal === 'danger')
                                        <span class="text-xs px-3 py-1 rounded border bg-red-50 text-red-700 border-red-200">PELIGRO</span>
                                    @elseif($riskVal === 'warning')
                                        <span class="text-xs px-3 py-1 rounded border bg-yellow-50 text-yellow-700 border-yellow-200">CRÍTICO</span>
                                    @else
                                        <span class="text-xs px-3 py-1 rounded border bg-green-50 text-green-700 border-green-200">NORMAL</span>
                                    @endif
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    @php
                                        $statusClasses = match ($statusVal) {
                                            'missing_document' => 'bg-red-50 text-red-700 border-red-200',
                                            'expired'          => 'bg-red-50 text-red-700 border-red-200',
                                            'completed'        => 'bg-green-50 text-green-700 border-green-200',
                                            'in_progress'      => 'bg-yellow-50 text-yellow-700 border-yellow-200',
                                            'special_renewal'  => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'special_checkin'  => 'bg-blue-50 text-blue-700 border-blue-200',
                                            'special_both'     => 'bg-orange-50 text-orange-700 border-orange-200',
                                            default            => 'bg-gray-50 text-gray-800 border-gray-200',
                                        };
                                    @endphp

                                    <span class="text-xs px-3 py-1 rounded border {{ $statusClasses }}">
                                        {{ $statusLabel }}
                                    </span>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center gap-3">
                                        <div class="w-32 bg-gray-200 rounded-full h-2 overflow-hidden">
                                            <div class="h-2 bg-gray-800"
                                                style="width: {{ $progress }}%">
                                            </div>
                                        </div>
                                        <div class="text-xs text-gray-600">{{ $progress }}%</div>
                                    </div>
                                </td>

                                <td class="px-6 py-4 whitespace-nowrap">
                                    {{ $tasksDone }}/{{ $tasksTotal }}
                                </td>

                                <td class="px-6 py-4 text-right whitespace-nowrap space-x-4">
                                    <a href="{{ route('assets.requirements.show', [$asset, $req]) }}"
                                    class="text-blue-600 hover:underline font-semibold">
                                        Abrir
                                    </a>

                                    <a href="{{ route('assets.requirements.documents.index', [$asset, $req]) }}"
                                    class="text-blue-600 hover:underline font-semibold">
                                        Documentos
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-10 text-center text-gray-500">
                                    @if($hasActiveFilters)
                                        No se encontraron requerimientos con los filtros aplicados.
                                    @else
                                        No hay requerimientos de {{ $usesCategoryView ? strtolower($scopeTitle) : ($scope === 'operation' ? 'operación' : 'proyecto') }} todavía.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function toggleRequirementFilters() {
            const panel = document.getElementById('requirement-filters-panel');
            const hiddenInput = document.getElementById('show_filters_input');

            panel.classList.toggle('hidden');
            hiddenInput.value = panel.classList.contains('hidden') ? '0' : '1';
        }
    </script>
</x-layouts.vigia>