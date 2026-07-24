<x-layouts.vigia title="Procesos">

    @php $user = auth()->user(); @endphp

    <x-slot name="breadcrumb">
        @if(! $cardView && $user->hasGroupScope())
            <a href="{{ route('processes.index') }}" class="text-blue-600 hover:underline">Procesos</a>
            <span class="mx-2 text-gray-400">/</span>
            @if($globalSearch)
                <span class="text-gray-700 font-medium">Búsqueda: "{{ request('q') }}"</span>
            @else
                <span class="text-gray-700 font-medium">
                    {{ $companies->firstWhere('id', $selectedCompanyId)?->name ?? 'Empresa' }}
                </span>
            @endif
        @else
            <span class="text-gray-700 font-medium">Procesos</span>
        @endif
    </x-slot>

    <div x-data="flowModal(
            {{ Js::from($usersByPosition) }},
            {{ Js::from($flowDefinitions) }},
            {{ Js::from($positionLabels) }},
            {{ Js::from($positionSortOrders) }}
         )"
         @open-flow-modal.window="openModal($event.detail)">

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">
            {{ $cardView ? 'Procesos' : 'Procedimientos' }}
        </h1>

        <div class="flex items-center gap-2">
            @if($user->isAdmin())
                <a href="{{ route('processes.obsoleto') }}"
                   class="flex items-center gap-1.5 px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-600 font-semibold text-sm hover:bg-gray-50">
                    <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8l1 12a2 2 0 002 2h8a2 2 0 002-2L19 8M10 12v4M14 12v4"/>
                    </svg>
                    Obsoleto
                </a>
            @endif

            @if($cardView)
                {{-- Botón de reporte cross-empresa desde la vista de tarjetas --}}
                <a href="{{ route('processes.index', ['report' => 1]) }}"
                   class="flex items-center gap-2 px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Generar reporte
                </a>
            @else
                @if($user->hasGroupScope())
                    <a href="{{ route('processes.index') }}"
                       class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
                        Volver
                    </a>
                @endif

                @if($user->isAdmin() || $user->isOperative())
                    <a href="{{ route('processes.cargar', $selectedCompanyId ? ['company_id' => $selectedCompanyId] : []) }}"
                       class="flex items-center gap-1.5 px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold text-sm hover:bg-blue-50">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/>
                        </svg>
                        Cargar proceso
                    </a>
                    <a href="{{ route('processes.create', $selectedCompanyId ? ['company_id' => $selectedCompanyId] : []) }}"
                       class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                        Nuevo procedimiento
                    </a>
                @endif
            @endif
        </div>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('processes.index') }}"
          class="mt-4 flex flex-wrap items-end gap-3">

        @if($cardView || ($user->hasGroupScope() && !$selectedCompanyId))
            <div class="min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id"
                        class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                    <option value="">Todas las empresas</option>
                    @foreach($companies as $c)
                        <option value="{{ $c->id }}" @selected(request('company_id') == $c->id)>
                            {{ $c->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[220px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar documento</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           placeholder="Código, nombre… en cualquier empresa"
                           class="w-full rounded-md border-gray-300 text-sm pl-9 focus:border-[#1A428A] focus:ring-[#1A428A]">
                </div>
            </div>
        @else
            @if($selectedCompanyId)
                <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
            @endif

            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">Tipo de proceso</label>
                <select name="process_type_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach($processTypes as $pt)
                        <option value="{{ $pt->id }}" @selected(request('process_type_id') == $pt->id)>
                            {{ $pt->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">Tipo de documento</label>
                <select name="document_type" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todos</option>
                    @foreach($documentTypes as $dt)
                        <option value="{{ $dt }}" @selected(request('document_type') === $dt)>
                            {{ $dt }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="flex-1 min-w-[180px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                <div class="relative">
                    <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                        </svg>
                    </span>
                    <input type="text"
                           name="q"
                           value="{{ request('q') }}"
                           placeholder="Código o nombre..."
                           class="w-full rounded-md border-gray-300 text-sm pl-9">
                </div>
            </div>
        @endif

        <button type="submit"
                class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
            Buscar
        </button>

        <a href="{{ route('processes.index') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    @if($cardView)

        {{-- VISTA DE TARJETAS --}}
        @if($companiesWithCounts->isEmpty())
            <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-500">No hay empresas para este filtro.</p>
            </div>
        @else
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($companiesWithCounts as $company)
                    <a href="{{ route('processes.index', ['company_id' => $company->id]) }}"
                       class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm
                              transition hover:border-[#1A428A] hover:shadow-md">

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center
                                        rounded-lg bg-blue-50 group-hover:bg-blue-100 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#1A428A]"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                </svg>
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 leading-snug group-hover:text-[#1A428A] transition">
                                    {{ $company->name }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100
                                         px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                                {{ $company->regulations_count }}
                                {{ \Illuminate\Support\Str::plural('procedimiento', $company->regulations_count) }}
                            </span>

                            <svg xmlns="http://www.w3.org/2000/svg"
                                 class="h-4 w-4 text-gray-400 group-hover:text-[#1A428A] transition"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                            </svg>
                        </div>

                    </a>
                @endforeach
            </div>

            <p class="mt-4 text-xs text-gray-400">
                {{ $companiesWithCounts->count() }}
                {{ \Illuminate\Support\Str::plural('empresa', $companiesWithCounts->count()) }} encontradas
            </p>
        @endif

    @else

    <div x-data="reportTable({{ Js::from($regulations->pluck('id')->values()) }})">

        {{-- Barra de exportación — visible al seleccionar --}}
        <div x-show="selected.length > 0"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mt-3 flex items-center gap-3 rounded-lg border border-[#1A428A] bg-blue-50 px-4 py-2.5"
             style="display:none;">
            <span class="text-sm font-semibold text-[#1A428A]">
                <span x-text="selected.length"></span> seleccionado<span x-show="selected.length !== 1">s</span>
            </span>
            <button type="button"
                    @click="submitReport()"
                    class="ml-auto flex items-center gap-1.5 rounded-md bg-[#1A428A] px-3 py-1.5 text-xs font-semibold text-white hover:bg-[#15356d]">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Descargar Excel
            </button>
            <button type="button"
                    @click="selected = []"
                    class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50">
                Limpiar selección
            </button>
        </div>

        {{-- Formulario oculto para POST de exportación --}}
        <form x-ref="reportForm" method="POST" action="{{ route('processes.report') }}" class="hidden">
            @csrf
            <template x-for="id in selected" :key="id">
                <input type="hidden" name="regulation_ids[]" :value="id">
            </template>
        </form>

        @if($globalSearch)
            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                @if(request()->boolean('report'))
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#1A428A] shrink-0" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Modo reporte — mostrando procedimientos de <strong class="text-gray-700">todas las empresas</strong>. Selecciona los que quieres incluir y descarga el Excel.</span>
                @else
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#1A428A] shrink-0" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                    Resultados para <strong class="text-gray-700 mx-1">"{{ request('q') }}"</strong>
                    en todas las empresas
                @endif
            </div>
        @endif

        {{-- VISTA DE TABLA --}}
        <div class="mt-3 bg-white border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-3 py-3 w-10">
                                <input type="checkbox"
                                       class="rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A] cursor-pointer"
                                       :checked="allSelected"
                                       :indeterminate.prop="selected.length > 0 && !allSelected"
                                       @change="toggleAll()"
                                       title="Seleccionar todo">
                            </th>
                            @if($globalSearch)
                                <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                            @endif
                            <th class="text-left px-4 py-3 font-semibold">Código</th>
                            <th class="text-left px-4 py-3 font-semibold">Nombre</th>
                            <th class="text-left px-4 py-3 font-semibold">Proceso</th>
                            <th class="text-left px-4 py-3 font-semibold">Tipo</th>
                            @if(! $globalSearch)
                            <th class="text-left px-4 py-3 font-semibold">Anexos</th>
                            @endif
                            <th class="text-left px-4 py-3 font-semibold">Vigencia</th>
                            <th class="text-left px-4 py-3 font-semibold">Flujo</th>
                            <th class="text-right px-4 py-3 font-semibold">Acciones</th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($regulations as $regulation)
                            @php
                                $version    = $regulation->currentVersion;
                                $color      = $regulation->statusColor();
                                $daysLeft   = $regulation->daysUntilExpiry();
                                $hasPending = isset($pendingApprovalIds[$regulation->id]);
                            @endphp

                            <tr class="border-t hover:bg-gray-50 cursor-pointer"
                                :class="selected.includes({{ $regulation->id }}) ? 'bg-blue-50' : ''"
                                ondblclick="window.location.href='{{ route('processes.show', $regulation) }}'">
                                <td class="px-3 py-3">
                                    <input type="checkbox"
                                           class="rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A] cursor-pointer"
                                           :checked="selected.includes({{ $regulation->id }})"
                                           @change="toggle({{ $regulation->id }})">
                                </td>

                                @if($globalSearch)
                                    <td class="px-4 py-3 text-gray-600 whitespace-nowrap text-xs">
                                        {{ $regulation->company->name ?? '—' }}
                                    </td>
                                @endif

                                <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                    {{ $regulation->code ?? '—' }}
                                </td>

                                <td class="px-4 py-3 font-medium text-gray-800 max-w-[220px]">
                                    @php $nombre = $regulation->name; @endphp
                                    @if(strlen($nombre) > 40)
                                        <span title="{{ $nombre }}">{{ mb_substr($nombre, 0, 40) }}…</span>
                                    @else
                                        {{ $nombre }}
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $regulation->processType->name ?? '—' }}
                                </td>

                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $regulation->document_type ?? '—' }}
                                </td>

                                @if(! $globalSearch)
                                <td class="px-4 py-3 min-w-[160px] max-w-[200px]"
                                    ondblclick="event.stopPropagation()"
                                    x-data="annexEditor(
                                        {{ $regulation->id }},
                                        {{ $regulation->company_id }},
                                        {{ Js::from($regulation->annexes->map(fn($a) => ['id' => $a->id, 'code' => $a->code, 'name' => $a->name])->values()) }}
                                    )">

                                    {{-- Vista: lista colapsable + botón editar --}}
                                    <div x-show="! editing" class="space-y-1">
                                        <div class="flex items-center gap-1">
                                            <template x-if="annexes.length > 0">
                                                <button type="button"
                                                        @click="expanded = ! expanded"
                                                        class="flex items-center gap-1 text-xs text-gray-600 hover:text-[#1A428A]">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transition-transform duration-150" :class="expanded ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                                    </svg>
                                                    <span x-text="'Anexos (' + annexes.length + ')'"></span>
                                                </button>
                                            </template>
                                            <template x-if="annexes.length === 0">
                                                <span class="text-gray-400 text-xs">Sin anexos</span>
                                            </template>
                                            @if($user->isAdmin() || $user->isOperative())
                                            <button type="button"
                                                    @click="openEdit()"
                                                    class="text-gray-400 hover:text-[#1A428A] transition shrink-0"
                                                    title="Editar anexos">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                                                </svg>
                                            </button>
                                            @endif
                                        </div>
                                        <div x-show="expanded" class="flex items-center flex-wrap gap-1">
                                            <template x-for="annex in annexes" :key="annex.id">
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 text-xs font-mono"
                                                      :title="annex.name"
                                                      x-text="annex.code || '?'">
                                                </span>
                                            </template>
                                        </div>
                                    </div>

                                    {{-- Edición inline --}}
                                    <div x-show="editing" class="space-y-1.5" style="display:none;">

                                        {{-- Chips seleccionados --}}
                                        <div class="flex flex-wrap gap-1 min-h-[1.25rem]">
                                            <template x-for="annex in pending" :key="annex.id">
                                                <span class="inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-blue-100 text-blue-800 text-xs font-mono">
                                                    <span x-text="annex.code || '?'"></span>
                                                    <button type="button"
                                                            @click="removeAnnex(annex.id)"
                                                            class="text-blue-500 hover:text-blue-800 ml-0.5">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                        </svg>
                                                    </button>
                                                </span>
                                            </template>
                                        </div>

                                        {{-- Autocomplete --}}
                                        <div class="relative">
                                            <input
                                                x-ref="searchInput"
                                                type="text"
                                                x-model="search"
                                                @input="onSearchInput()"
                                                @focus="search.trim() && fetchResults()"
                                                @keydown.escape="cancelEdit()"
                                                placeholder="Buscar código…"
                                                class="w-full rounded border border-gray-300 text-xs px-2 py-1 focus:outline-none focus:border-[#1A428A] focus:ring-1 focus:ring-[#1A428A]"
                                            >
                                            <div x-show="showDropdown"
                                                 @click.outside="showDropdown = false"
                                                 class="absolute left-0 top-full mt-0.5 z-30 w-60 bg-white border border-gray-200 rounded shadow-lg max-h-40 overflow-y-auto">
                                                <template x-for="r in results" :key="r.id">
                                                    <button type="button"
                                                            @click="selectAnnex(r)"
                                                            class="w-full text-left px-2 py-1.5 text-xs hover:bg-blue-50 flex items-baseline gap-1.5">
                                                        <span class="font-mono font-semibold text-gray-800 shrink-0" x-text="r.code || '—'"></span>
                                                        <span class="text-gray-500 truncate" x-text="r.name"></span>
                                                    </button>
                                                </template>
                                            </div>
                                        </div>

                                        {{-- Acciones --}}
                                        <div class="flex items-center gap-1.5">
                                            <button type="button"
                                                    @click="save()"
                                                    :disabled="saving"
                                                    class="px-2 py-1 rounded bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d] disabled:opacity-50">
                                                <span x-text="saving ? 'Guardando…' : 'Guardar'"></span>
                                            </button>
                                            <button type="button"
                                                    @click="cancelEdit()"
                                                    :disabled="saving"
                                                    class="px-2 py-1 rounded border border-gray-300 text-gray-600 text-xs font-semibold hover:bg-gray-50">
                                                Cancelar
                                            </button>
                                            <span x-show="error" class="text-red-500 text-xs">Error al guardar</span>
                                        </div>
                                    </div>
                                </td>
                                @endif

                                {{-- Vigencia (dot + fecha vencimiento + tooltip) --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($version?->valid_until)
                                        <div x-data="{ open: false }" class="relative inline-flex">
                                            <button type="button"
                                                    @click="open = !open"
                                                    @click.outside="open = false"
                                                    class="flex items-center gap-1.5 focus:outline-none">
                                                <span class="inline-block h-2 w-2 rounded-full shrink-0
                                                    {{ $color === 'green'  ? 'bg-green-500' : '' }}
                                                    {{ $color === 'yellow' ? 'bg-yellow-400' : '' }}
                                                    {{ $color === 'red'    ? 'bg-red-500'   : '' }}
                                                    {{ $color === 'blue'   ? 'bg-[#1A428A]' : '' }}">
                                                </span>
                                                <span class="text-xs {{ $color === 'red' ? 'text-red-600 font-medium' : ($color === 'yellow' ? 'text-yellow-600 font-medium' : 'text-gray-600') }}">
                                                    {{ $version->valid_until->format('d/m/Y') }}
                                                </span>
                                            </button>
                                            <div x-show="open"
                                                 x-transition
                                                 class="absolute left-0 top-6 z-20 w-52 rounded-xl border border-gray-200 bg-white shadow-lg p-3 text-xs text-gray-700">
                                                @if($color === 'red')
                                                    <p class="font-semibold text-red-600">Vencido</p>
                                                    @if($daysLeft !== null)<p class="mt-1">Venció hace {{ abs($daysLeft) }} día(s).</p>@endif
                                                @elseif($color === 'yellow')
                                                    <p class="font-semibold text-yellow-600">Por vencer</p>
                                                    @if($daysLeft !== null)<p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>@endif
                                                @else
                                                    <p class="font-semibold text-green-600">Vigente</p>
                                                    @if($daysLeft !== null)<p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>@endif
                                                @endif
                                                @if($version->issued_at)
                                                    <p class="mt-1 text-gray-400">Desde: {{ $version->issued_at->format('d/m/Y') }}</p>
                                                @endif
                                                @if($version->responsible_name)
                                                    <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                                @endif
                                            </div>
                                        </div>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                                            <span class="inline-block h-2 w-2 rounded-full shrink-0
                                                {{ $color === 'blue' ? 'bg-[#1A428A]' : 'bg-yellow-400' }}">
                                            </span>
                                            {{ $color === 'blue' ? 'Sin archivo' : 'Sin vigencia' }}
                                        </span>
                                    @endif
                                </td>

                                {{-- Columna Flujo --}}
                                <td class="px-4 py-3 min-w-[170px]">
                                    @if($user->isAdmin())
                                        @if($regulation->flow_locked)
                                            {{-- Flujo ya configurado: solo lectura, se cambia editando el documento --}}
                                            <div class="text-xs border border-amber-300 rounded px-2 py-1 bg-amber-50 text-amber-800 w-full mb-1.5 flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 shrink-0 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                                </svg>
                                                <span>{{ \App\Models\Regulation::IMPACT_LEVELS[$regulation->impact_level] ?? $regulation->impact_level }} (activo)</span>
                                            </div>
                                        @elseif($regulation->approval_status === 'approved')
                                            {{-- Documento cargado y aprobado externamente --}}
                                            <div class="text-xs border border-green-200 rounded px-2 py-1 bg-green-50 text-green-700 w-full mb-1.5 flex items-center gap-1.5">
                                                <svg class="h-3.5 w-3.5 shrink-0 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                </svg>
                                                <span>Aprobado</span>
                                            </div>
                                        @else
                                            {{-- Sin flujo aún: selector para configurarlo --}}
                                            <select
                                                @change="$dispatch('open-flow-modal', {
                                                    regulationId: {{ $regulation->id }},
                                                    regulationName: @js($regulation->name),
                                                    impactLevel: $event.target.value,
                                                    levelLabel: $event.target.options[$event.target.selectedIndex].text,
                                                    formAction: '{{ route('processes.setFlow', $regulation) }}'
                                                }); $event.target.value = ''"
                                                class="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white text-gray-700 focus:outline-none focus:border-blue-400 w-full mb-1.5">
                                                <option value="">— Asignar flujo —</option>
                                                @foreach(\App\Models\Regulation::IMPACT_LEVELS as $val => $lbl)
                                                    <option value="{{ $val }}">{{ $lbl }}</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif

                                    {{-- Estado del flujo --}}
                                    @if($regulation->impact_level && $regulation->approval_status)
                                        @php
                                            $apColor = $regulation->approvalStatusColor();
                                            $apLabel = $regulation->approvalStatusLabel();
                                        @endphp
                                        <div class="flex flex-col gap-1.5">
                                            <span class="inline-flex items-center gap-1 text-xs
                                                {{ $apColor === 'green'  ? 'text-green-700' : '' }}
                                                {{ $apColor === 'yellow' ? 'text-yellow-700' : '' }}
                                                {{ $apColor === 'red'    ? 'text-red-600'   : '' }}
                                                {{ $apColor === 'blue'   ? 'text-blue-600'  : '' }}">
                                                <span class="h-2 w-2 rounded-full shrink-0
                                                    {{ $apColor === 'green'  ? 'bg-green-500'  : '' }}
                                                    {{ $apColor === 'yellow' ? 'bg-yellow-400' : '' }}
                                                    {{ $apColor === 'red'    ? 'bg-red-500'    : '' }}
                                                    {{ $apColor === 'blue'   ? 'bg-blue-500'   : '' }}">
                                                </span>
                                                {{ $apLabel }}
                                            </span>

                                            @if($hasPending)
                                                <div class="flex items-center gap-1">
                                                    <form method="POST" action="{{ route('processes.approve', $regulation) }}">
                                                        @csrf
                                                        <button type="submit"
                                                                onclick="return confirm('¿Aprobar «{{ addslashes($regulation->name) }}»?')"
                                                                class="px-2 py-1 rounded bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d]">
                                                            Aprobar
                                                        </button>
                                                    </form>
                                                    <a href="{{ route('processes.show', $regulation) }}#aprobacion"
                                                       class="px-2 py-1 rounded border border-red-300 text-red-600 text-xs font-semibold hover:bg-red-50">
                                                        Rechazar
                                                    </a>
                                                </div>
                                            @endif
                                        </div>
                                    @elseif(!$user->isAdmin())
                                        @if($regulation->approval_status === 'approved')
                                            <span class="inline-flex items-center gap-1 text-xs text-green-700">
                                                <span class="h-2 w-2 rounded-full bg-green-500 shrink-0"></span>
                                                Aprobado
                                            </span>
                                        @else
                                            <span class="text-xs text-gray-400">Sin flujo</span>
                                        @endif
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('processes.print', $regulation) }}"
                                           target="_blank"
                                           class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 text-xs font-semibold hover:bg-gray-50 flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                            </svg>
                                            Descargar
                                        </a>
                                        <a href="{{ route('processes.show', $regulation) }}"
                                           class="px-3 py-1.5 rounded-md bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d]">
                                            Gestionar
                                        </a>
                                    </div>
                                </td>

                            </tr>
                        @empty
                            <tr class="border-t">
                                <td colspan="9"
                                    class="px-6 py-8 text-center text-gray-500">
                                    No hay procedimientos para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            @if($globalSearch)
                {{ $regulations->count() }} {{ \Illuminate\Support\Str::plural('procedimiento', $regulations->count()) }}
                encontrados en {{ $regulations->pluck('company_id')->unique()->count() }}
                {{ \Illuminate\Support\Str::plural('empresa', $regulations->pluck('company_id')->unique()->count()) }}
            @else
                {{ $regulations->count() }} {{ \Illuminate\Support\Str::plural('procedimiento', $regulations->count()) }} encontrados
            @endif
            <span x-show="selected.length > 0" class="ml-2 font-medium text-[#1A428A]">
                · <span x-text="selected.length"></span> seleccionado<span x-show="selected.length !== 1">s</span>
            </span>
        </p>

    </div>{{-- fin reportTable --}}

    @endif

    {{-- =====================================================================
         MODAL: Asignación de flujo de aprobación
         ===================================================================== --}}
    <div x-show="show"
         x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
         style="display:none;">

        {{-- Hidden form submitted on confirm --}}
        <form x-ref="flowForm" method="POST" :action="formAction">
            @csrf
            @method('PATCH')
            <input type="hidden" name="impact_level" :value="impactLevel">
            <template x-for="pos in positions" :key="pos.slug">
                <template x-for="u in (selected[pos.slug] || [])" :key="u.id">
                    <input type="hidden"
                           :name="`users[${pos.slug}][]`"
                           :value="u.id">
                </template>
            </template>
        </form>

        {{-- Modal card --}}
        <div @click.outside="show = false"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">

            {{-- Header --}}
            <div class="bg-[#1A428A] px-6 py-4 flex items-center justify-between">
                <div>
                    <h3 class="text-white font-semibold text-base">Confirmar flujo de aprobación</h3>
                    <p class="text-blue-200 text-xs mt-0.5" x-text="regulationName"></p>
                </div>
                <button type="button" @click="show = false"
                        class="text-blue-200 hover:text-white transition">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="px-6 py-5">

                {{-- Removing flow --}}
                <template x-if="removing">
                    <div class="text-sm text-gray-700">
                        <p>¿Confirmas que deseas <strong>eliminar el flujo de aprobación</strong> de este documento?</p>
                        <p class="mt-2 text-gray-500 text-xs">El flujo no se ha confirmado aún, por lo que puede eliminarse.</p>
                    </div>
                </template>

                {{-- Assigning flow --}}
                <template x-if="!removing">
                    <div>
                        <p class="text-sm text-gray-700 mb-1">
                            Nivel seleccionado:
                            <span class="font-semibold text-[#1A428A]" x-text="levelLabel"></span>
                        </p>
                        <p class="text-xs text-gray-500 mb-4">
                            Asigna un responsable por cada puesto. Una vez confirmado, el flujo no podrá modificarse.
                        </p>

                        <div class="space-y-5">
                            <template x-for="pos in positions" :key="pos.slug">
                                <div>
                                    <div class="flex items-center gap-1.5 mb-1.5">
                                        <label class="text-xs font-semibold text-gray-700" x-text="pos.label"></label>
                                        <span x-show="!pos.requiresAll"
                                              class="text-xs text-gray-400 font-normal">(cualquiera basta)</span>
                                        <span class="ml-auto text-xs"
                                              :class="(selected[pos.slug]||[]).length > 0 ? 'text-green-600' : 'text-gray-400'"
                                              x-text="(selected[pos.slug]||[]).length + ' asignado(s)'"></span>
                                    </div>

                                    {{-- Chips de usuarios seleccionados --}}
                                    <div x-show="(selected[pos.slug]||[]).length > 0"
                                         class="flex flex-wrap gap-1.5 mb-2">
                                        <template x-for="u in (selected[pos.slug]||[])" :key="u.id">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-medium">
                                                <span x-text="u.name"></span>
                                                <button type="button"
                                                        @click="removeUser(pos.slug, u.id)"
                                                        class="ml-0.5 text-blue-500 hover:text-blue-800">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </span>
                                        </template>
                                    </div>

                                    {{-- Autocomplete input --}}
                                    <div class="relative">
                                        <input
                                            type="text"
                                            :placeholder="`Agregar persona a ${pos.label}…`"
                                            x-model="search[pos.slug]"
                                            @focus="open[pos.slug] = true"
                                            @input="open[pos.slug] = true"
                                            @keydown.escape="open[pos.slug] = false; search[pos.slug] = ''"
                                            class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 pr-8 focus:outline-none focus:border-[#1A428A] focus:ring-1 focus:ring-[#1A428A]"
                                        >
                                        <button type="button"
                                                x-show="search[pos.slug]"
                                                @click="search[pos.slug] = ''; open[pos.slug] = false"
                                                class="absolute inset-y-0 right-2 flex items-center text-gray-400 hover:text-gray-600">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>

                                        {{-- Dropdown --}}
                                        <div x-show="open[pos.slug] && filtered(pos.slug).length > 0"
                                             @click.outside="open[pos.slug] = false"
                                             class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-44 overflow-y-auto">
                                            <template x-for="u in filtered(pos.slug)" :key="u.id">
                                                <button type="button"
                                                        @click="selectUser(pos.slug, u)"
                                                        class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center gap-2">
                                                    <span class="h-6 w-6 rounded-full bg-[#1A428A] text-white text-xs flex items-center justify-center shrink-0"
                                                          x-text="u.name.charAt(0).toUpperCase()"></span>
                                                    <div>
                                                        <div class="font-medium text-gray-800" x-text="u.name"></div>
                                                        <div class="text-xs text-gray-400" x-text="u.email"></div>
                                                    </div>
                                                </button>
                                            </template>
                                            <div x-show="filtered(pos.slug).length === 0"
                                                 class="px-3 py-2 text-sm text-gray-400 italic">
                                                Sin coincidencias
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t flex justify-end gap-3">
                <button type="button"
                        @click="show = false"
                        class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 font-medium hover:bg-gray-100 transition">
                    Cancelar
                </button>
                <button type="button"
                        @click="confirmFlow()"
                        :disabled="!canConfirm"
                        :class="canConfirm
                            ? 'bg-[#1A428A] hover:bg-[#15356d] text-white cursor-pointer'
                            : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                        class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                    <span x-text="removing ? 'Sí, eliminar flujo' : 'Confirmar flujo'"></span>
                </button>
            </div>
        </div>
    </div>

    </div>{{-- end x-data wrapper --}}

</x-layouts.vigia>

<script>
function reportTable(allIds) {
    return {
        selected: [],
        allIds: allIds,

        get allSelected() {
            return this.allIds.length > 0 &&
                   this.allIds.every(id => this.selected.includes(id));
        },

        toggle(id) {
            const idx = this.selected.indexOf(id);
            if (idx === -1) this.selected.push(id);
            else this.selected.splice(idx, 1);
        },

        toggleAll() {
            this.allSelected ? (this.selected = []) : (this.selected = [...this.allIds]);
        },

        submitReport() {
            this.$refs.reportForm.submit();
        },
    };
}

function annexEditor(regulationId, companyId, initialAnnexes) {
    return {
        editing: false,
        expanded: false,
        saving: false,
        error: false,
        annexes: JSON.parse(JSON.stringify(initialAnnexes)),
        pending: [],
        search: '',
        results: [],
        showDropdown: false,
        _timer: null,

        openEdit() {
            this.pending = JSON.parse(JSON.stringify(this.annexes));
            this.search = '';
            this.results = [];
            this.showDropdown = false;
            this.editing = true;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        cancelEdit() {
            this.editing = false;
            this.search = '';
            this.results = [];
            this.showDropdown = false;
        },

        onSearchInput() {
            clearTimeout(this._timer);
            if (! this.search.trim()) {
                this.results = [];
                this.showDropdown = false;
                return;
            }
            this._timer = setTimeout(() => this.fetchResults(), 250);
        },

        async fetchResults() {
            const q   = encodeURIComponent(this.search.trim());
            const url = `/processes/search-annexes?company_id=${companyId}&q=${q}&exclude=${regulationId}`;
            try {
                const res  = await fetch(url, { headers: { 'Accept': 'application/json' } });
                const data = await res.json();
                const pendingIds = this.pending.map(p => p.id);
                this.results     = data.filter(r => ! pendingIds.includes(r.id));
                this.showDropdown = this.results.length > 0;
            } catch {
                this.results = [];
                this.showDropdown = false;
            }
        },

        selectAnnex(annex) {
            if (! this.pending.find(p => p.id === annex.id)) {
                this.pending.push({ id: annex.id, code: annex.code, name: annex.name });
            }
            this.search = '';
            this.results = [];
            this.showDropdown = false;
            this.$nextTick(() => this.$refs.searchInput?.focus());
        },

        removeAnnex(id) {
            this.pending = this.pending.filter(p => p.id !== id);
        },

        async save() {
            this.saving = true;
            this.error  = false;
            try {
                const res = await fetch(`/processes/${regulationId}/annexes`, {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                    },
                    body: JSON.stringify({ annex_ids: this.pending.map(p => p.id) }),
                });
                if (res.ok) {
                    const data   = await res.json();
                    this.annexes = data.annexes;
                    this.editing = false;
                    this.search  = '';
                } else {
                    this.error = true;
                }
            } catch {
                this.error = true;
            }
            this.saving = false;
        },
    };
}

function flowModal(usersByPosition, flowDefs, positionLabels, positionSortOrders) {
    return {
        show: false,
        removing: false,
        formAction: '',
        regulationName: '',
        impactLevel: '',
        levelLabel: '',
        positions: [],
        selected: {},
        search: {},
        open: {},

        get canConfirm() {
            if (this.removing) return true;
            return this.positions.length > 0 &&
                   this.positions.every(p => (this.selected[p.slug] || []).length > 0);
        },

        filtered(slug) {
            const users = usersByPosition[slug] || [];
            const selectedIds = (this.selected[slug] || []).map(u => u.id);
            const available = users.filter(u => !selectedIds.includes(u.id));
            const q = (this.search[slug] || '').toLowerCase().trim();
            if (!q) return available;
            return available.filter(u =>
                u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
            );
        },

        openModal(detail) {
            this.formAction   = detail.formAction;
            this.regulationName = detail.regulationName;
            this.impactLevel  = detail.impactLevel;
            this.levelLabel   = detail.levelLabel;
            this.removing     = !detail.impactLevel;

            this.positions = [];
            this.selected  = {};
            this.search    = {};
            this.open      = {};

            if (detail.impactLevel && flowDefs[detail.impactLevel]) {
                const steps = flowDefs[detail.impactLevel];
                const seen  = new Set();

                Object.entries(steps).forEach(([step, stepDef]) => {
                    stepDef.positions.forEach(slug => {
                        if (!seen.has(slug)) {
                            seen.add(slug);
                            this.positions.push({
                                slug,
                                label: positionLabels[slug] || slug,
                                step: parseInt(step),
                                requiresAll: stepDef.requires_all,
                            });
                            this.selected[slug] = [];
                            this.search[slug]   = '';
                            this.open[slug]     = false;
                        }
                    });
                });
            }

            // Sort highest hierarchy first (mayor sort_order = más importante = arriba)
            this.positions.sort((a, b) =>
                (positionSortOrders[b.slug] || 0) - (positionSortOrders[a.slug] || 0)
            );

            this.show = true;
        },

        selectUser(slug, user) {
            if (!this.selected[slug]) this.selected[slug] = [];
            if (!this.selected[slug].find(u => u.id === user.id)) {
                this.selected[slug] = [...this.selected[slug], user];
            }
            this.search[slug] = '';
            this.open[slug]   = false;
        },

        removeUser(slug, userId) {
            this.selected[slug] = (this.selected[slug] || []).filter(u => u.id !== userId);
        },

        confirmFlow() {
            if (!this.canConfirm) return;
            this.$refs.flowForm.submit();
        },
    };
}
</script>
