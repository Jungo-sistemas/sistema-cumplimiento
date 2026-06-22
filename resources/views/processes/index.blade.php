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
            {{ $cardView ? 'Procesos' : 'Documentos' }}
        </h1>

        <div class="flex items-center gap-2">
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
                    <a href="{{ route('processes.create', $selectedCompanyId ? ['company_id' => $selectedCompanyId] : []) }}"
                       class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                        Nuevo documento
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
                                {{ \Illuminate\Support\Str::plural('documento', $company->regulations_count) }}
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
                    <span>Modo reporte — mostrando documentos de <strong class="text-gray-700">todas las empresas</strong>. Selecciona los que quieres incluir y descarga el Excel.</span>
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
                            <th class="text-left px-4 py-3 font-semibold">Versión</th>
                            <th class="text-left px-4 py-3 font-semibold">Vigencia</th>
                            <th class="text-left px-4 py-3 font-semibold">Estatus</th>
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

                            <tr class="border-t hover:bg-gray-50"
                                :class="selected.includes({{ $regulation->id }}) ? 'bg-blue-50' : ''">
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

                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    {{ $version ? 'v' . $version->version_number : '—' }}
                                </td>

                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                    @if($version?->valid_until)
                                        {{ $version->issued_at?->format('d/m/Y') ?? '—' }}
                                        →
                                        <span class="{{ $color === 'red' ? 'text-red-600 font-medium' : ($color === 'yellow' ? 'text-yellow-600 font-medium' : '') }}">
                                            {{ $version->valid_until->format('d/m/Y') }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">Sin vigencia</span>
                                    @endif
                                </td>

                                {{-- Estatus de vigencia --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div x-data="{ open: false }" class="relative inline-block">
                                        <button type="button"
                                                @click="open = !open"
                                                @click.outside="open = false"
                                                class="flex items-center gap-2 focus:outline-none">
                                            <span class="inline-block h-3 w-3 rounded-full
                                                {{ $color === 'green'  ? 'bg-green-500' : '' }}
                                                {{ $color === 'yellow' ? 'bg-yellow-400' : '' }}
                                                {{ $color === 'red'    ? 'bg-red-500' : '' }}
                                                {{ $color === 'blue'   ? 'bg-[#1A428A]' : '' }}">
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                {{ $regulation->statusLabel() }}
                                            </span>
                                        </button>

                                        <div x-show="open"
                                             x-transition
                                             class="absolute left-0 top-6 z-20 w-56 rounded-xl border border-gray-200 bg-white shadow-lg p-3 text-xs text-gray-700">
                                            @if(! $version && $color === 'blue')
                                                <p class="font-semibold text-[#1A428A]">Aprobado</p>
                                                <p class="mt-1 text-gray-500">El documento fue aprobado. Pendiente de subir la versión final.</p>
                                            @elseif(! $version)
                                                <p class="font-semibold text-yellow-600">Sin versión cargada</p>
                                                <p class="mt-1 text-gray-500">No se ha subido ningún archivo todavía.</p>
                                            @elseif($color === 'red')
                                                <p class="font-semibold text-red-600">Vencido</p>
                                                @if($daysLeft !== null)
                                                    <p class="mt-1">Venció hace {{ abs($daysLeft) }} día(s).</p>
                                                @endif
                                                @if($version->responsible_name)
                                                    <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                                @endif
                                            @elseif($color === 'yellow')
                                                <p class="font-semibold text-yellow-600">Por vencer</p>
                                                @if($daysLeft !== null)
                                                    <p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>
                                                @endif
                                                @if($version->responsible_name)
                                                    <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                                @endif
                                            @else
                                                <p class="font-semibold text-green-600">Vigente</p>
                                                @if($daysLeft !== null)
                                                    <p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>
                                                @endif
                                                @if($version->responsible_name)
                                                    <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                                @endif
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- Columna Flujo --}}
                                <td class="px-4 py-3 min-w-[170px]">
                                    @if($user->isAdmin())
                                        @if(!$regulation->flow_locked)
                                            {{-- Selector que abre modal de asignación --}}
                                            <select
                                                @change="$dispatch('open-flow-modal', {
                                                    regulationId: {{ $regulation->id }},
                                                    regulationName: @js($regulation->name),
                                                    impactLevel: $event.target.value,
                                                    levelLabel: $event.target.options[$event.target.selectedIndex].text,
                                                    formAction: '{{ route('processes.setFlow', $regulation) }}'
                                                }); $event.target.value = '{{ $regulation->impact_level ?? '' }}'"
                                                class="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white text-gray-700 focus:outline-none focus:border-blue-400 w-full mb-1.5">
                                                <option value="">— Sin flujo —</option>
                                                @foreach(\App\Models\Regulation::IMPACT_LEVELS as $val => $lbl)
                                                    <option value="{{ $val }}" {{ $regulation->impact_level === $val ? 'selected' : '' }}>
                                                        {{ $lbl }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        @else
                                            {{-- Flujo confirmado: etiqueta con candado --}}
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 mb-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ $regulation->impactLevelLabel() }}
                                            </span>
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
                                        <span class="text-xs text-gray-400">Sin flujo</span>
                                    @endif
                                </td>

                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        <a href="{{ route('processes.print', $regulation) }}"
                                           target="_blank"
                                           class="px-3 py-1.5 rounded-md border border-gray-300 text-gray-600 text-xs font-semibold hover:bg-gray-50 flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                                            </svg>
                                            Imprimir
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
                                <td colspan="{{ $globalSearch ? 11 : 10 }}"
                                    class="px-6 py-8 text-center text-gray-500">
                                    No hay documentos para los filtros seleccionados.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <p class="mt-3 text-xs text-gray-400">
            @if($globalSearch)
                {{ $regulations->count() }} {{ \Illuminate\Support\Str::plural('documento', $regulations->count()) }}
                encontrados en {{ $regulations->pluck('company_id')->unique()->count() }}
                {{ \Illuminate\Support\Str::plural('empresa', $regulations->pluck('company_id')->unique()->count()) }}
            @else
                {{ $regulations->count() }} {{ \Illuminate\Support\Str::plural('documento', $regulations->count()) }} encontrados
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
