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

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">
            {{ $cardView ? 'Procesos' : 'Documentos' }}
        </h1>

        <div class="flex items-center gap-2">
            @if(! $cardView)
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
            {{-- Selector de empresa (aplica en card view y en búsqueda global) --}}
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

            {{-- Búsqueda global: nombre o código de reglamento --}}
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
            {{-- Vista de tabla dentro de una empresa --}}
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

        {{-- VISTA DE TARJETAS: empresas como carpetas --}}
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

                        {{-- Icono + nombre --}}
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

                        {{-- Pie: conteo de reglamentos + flecha --}}
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

        {{-- Encabezado de resultado de búsqueda global --}}
        @if($globalSearch)
            <div class="mt-4 flex items-center gap-2 text-sm text-gray-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-[#1A428A]" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                </svg>
                Resultados para <strong class="text-gray-700 mx-1">"{{ request('q') }}"</strong>
                en todas las empresas
            </div>
        @endif

        {{-- VISTA DE TABLA --}}
        <div class="mt-3 bg-white border rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
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

                            <tr class="border-t hover:bg-gray-50">

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

                                {{-- Columna Estatus de vigencia --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div x-data="{ open: false }" class="relative inline-block">
                                        <button type="button"
                                                @click="open = !open"
                                                @click.outside="open = false"
                                                class="flex items-center gap-2 focus:outline-none">
                                            <span class="inline-block h-3 w-3 rounded-full
                                                {{ $color === 'green'  ? 'bg-green-500' : '' }}
                                                {{ $color === 'yellow' ? 'bg-yellow-400' : '' }}
                                                {{ $color === 'red'    ? 'bg-red-500' : '' }}">
                                            </span>
                                            <span class="text-xs text-gray-500">
                                                {{ $regulation->statusLabel() }}
                                            </span>
                                        </button>

                                        <div x-show="open"
                                             x-transition
                                             class="absolute left-0 top-6 z-20 w-56 rounded-xl border border-gray-200 bg-white shadow-lg p-3 text-xs text-gray-700">
                                            @if(! $version)
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
                                            {{-- Selector con confirm() al cambiar --}}
                                            <form method="POST" action="{{ route('processes.setFlow', $regulation) }}" class="mb-1.5">
                                                @csrf
                                                @method('PATCH')
                                                <select name="impact_level"
                                                        onchange="
                                                            var label = this.options[this.selectedIndex].text;
                                                            var msg = this.value
                                                                ? '¿Confirmar flujo «' + label + '»?\n\nUna vez confirmado no podrá modificarse.'
                                                                : '¿Eliminar el flujo de aprobación?';
                                                            if (confirm(msg)) {
                                                                this.form.submit();
                                                            } else {
                                                                this.value = '{{ $regulation->impact_level ?? '' }}';
                                                            }
                                                        "
                                                        class="text-xs border border-gray-200 rounded px-1.5 py-1 bg-white text-gray-700 focus:outline-none focus:border-blue-400 w-full">
                                                    <option value="">— Sin flujo —</option>
                                                    @foreach(\App\Models\Regulation::IMPACT_LEVELS as $val => $lbl)
                                                        <option value="{{ $val }}" {{ $regulation->impact_level === $val ? 'selected' : '' }}>
                                                            {{ $lbl }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </form>
                                        @else
                                            {{-- Flujo confirmado: solo etiqueta con candado --}}
                                            <span class="inline-flex items-center gap-1 text-xs font-medium text-gray-700 mb-1.5">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 text-gray-400 shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M12 1.5a5.25 5.25 0 00-5.25 5.25v3a3 3 0 00-3 3v6.75a3 3 0 003 3h10.5a3 3 0 003-3v-6.75a3 3 0 00-3-3v-3c0-2.9-2.35-5.25-5.25-5.25zm3.75 8.25v-3a3.75 3.75 0 10-7.5 0v3h7.5z" clip-rule="evenodd"/>
                                                </svg>
                                                {{ $regulation->impactLevelLabel() }}
                                            </span>
                                        @endif
                                    @endif

                                    {{-- Estado del flujo (todos los usuarios, si hay flujo) --}}
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
                                <td colspan="{{ $globalSearch ? 10 : 9 }}"
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
        </p>

    @endif

</x-layouts.vigia>
