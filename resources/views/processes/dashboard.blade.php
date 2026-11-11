<x-layouts.vigia title="Indicadores de procesos">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Indicadores de procesos</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 space-y-8">
        <h1 class="text-2xl font-semibold text-brand">Indicadores de procesos</h1>

        {{-- Cards de resumen --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4" data-tour="dashboard-summary">
            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-brand">Total procedimientos</div>
                <div class="mt-2 text-2xl font-bold text-gray-800">{{ $stats['total'] }}</div>
            </div>

            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-brand">Aprobados</div>
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
                    <div class="text-sm font-semibold text-brand">Pendientes de mi aprobación</div>
                    <div class="mt-2 text-2xl font-bold text-gray-400">0</div>
                </div>
            @endif
        </div>

        {{-- Fila de gráficas 1: Estado del flujo + Posición en el flujo --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Gráfica: Estado del flujo de aprobación (donut) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-4">Estado del flujo de aprobación</div>
                <div class="flex items-center justify-center" style="height:220px;">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>

            {{-- Gráfica: Días promedio por paso del flujo (barra agrupada) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-1">Tiempo promedio por paso del flujo</div>
                <div class="text-xs text-gray-400 mb-4">Días que tarda cada paso · detecta dónde se detiene el flujo</div>
                <div style="height:220px;">
                    <canvas id="chartDays"></canvas>
                </div>
            </div>
        </div>

        {{-- Fila de gráficas 2: Actividad semanal + Nivel de impacto --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Gráfica: Actividad semanal (línea) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-1">Actividad semanal</div>
                <div class="text-xs text-gray-400 mb-4">Decisiones de aprobación en las últimas 8 semanas</div>
                <div style="height:220px;">
                    <canvas id="chartWeekly"></canvas>
                </div>
            </div>

            {{-- Gráfica: Distribución por nivel de impacto (donut) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-4">Distribución por nivel de impacto</div>
                <div class="flex items-center justify-center" style="height:220px;">
                    <canvas id="chartImpact"></canvas>
                </div>
            </div>
        </div>

        {{-- Ranking de personas con aprobaciones pendientes --}}
        @php $pendingByUser = $chartData['pendingByUser'] ?? []; @endphp
        @if(count($pendingByUser) > 0)
            <div>
                <div class="flex items-center gap-2 mb-3">
                    <div class="text-sm font-semibold text-gray-700">Personas con aprobaciones pendientes</div>
                    <span class="text-xs text-gray-400">· ordenadas por cantidad acumulada</span>
                </div>
                {{-- Sin overflow-hidden: si no, el popup de pendientes se corta en las últimas filas. --}}
                <div class="border rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="text-gray-500 text-xs">
                            <tr>
                                <th class="text-left px-4 py-2.5 font-semibold bg-gray-50 rounded-tl-lg">#</th>
                                <th class="text-left px-4 py-2.5 font-semibold bg-gray-50">Persona</th>
                                <th class="text-center px-4 py-2.5 font-semibold bg-gray-50">Pendientes <span class="font-normal text-gray-400">(pasa el mouse)</span></th>
                                <th class="text-right px-4 py-2.5 font-semibold bg-gray-50 rounded-tr-lg">Más antigua</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($pendingByUser as $i => $person)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-4 py-2.5 text-gray-400 text-xs">{{ $i + 1 }}</td>
                                    <td class="px-4 py-2.5 font-medium text-gray-800">
                                        <div class="flex items-center gap-2">
                                            <span class="h-6 w-6 rounded-full bg-brand text-white text-xs flex items-center justify-center shrink-0">
                                                {{ mb_substr($person['name'], 0, 1) }}
                                            </span>
                                            {{ $person['name'] }}
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-center">
                                        {{-- Al pasar el mouse (o tocar en el celular) se ven los procedimientos que tiene pendientes. --}}
                                        <div class="relative inline-block" x-data="{ open: false }"
                                             @mouseenter="open = true" @mouseleave="open = false" @click.outside="open = false">
                                            <button type="button" @click="open = ! open"
                                                @class([
                                                    'inline-flex items-center justify-center h-6 min-w-[1.5rem] px-2 rounded-full text-xs font-bold cursor-help',
                                                    'bg-red-100 text-red-700' => $person['count'] >= 3,
                                                    'bg-yellow-100 text-yellow-700' => $person['count'] == 2,
                                                    'bg-gray-100 text-gray-600' => $person['count'] < 2,
                                                ])>
                                                {{ $person['count'] }}
                                            </button>

                                            @if(! empty($person['items']))
                                                <div x-show="open" x-cloak x-transition.opacity
                                                     class="absolute z-30 right-1/2 translate-x-1/2 top-full pt-2 w-80 max-w-[calc(100vw-2rem)] text-left">
                                                    <div class="bg-white border border-gray-200 rounded-lg shadow-lg overflow-hidden">
                                                        <div class="px-3 py-2 bg-gray-50 border-b text-xs font-semibold text-gray-600">
                                                            Pendientes de {{ $person['name'] }}
                                                        </div>
                                                        <ul class="max-h-64 overflow-y-auto divide-y divide-gray-100">
                                                            @foreach($person['items'] as $item)
                                                                <li>
                                                                    <a href="{{ route('processes.show', $item['id']) }}"
                                                                       class="flex items-start justify-between gap-3 px-3 py-2 hover:bg-gray-50">
                                                                        <div class="min-w-0">
                                                                            <div class="text-xs font-medium text-gray-800 truncate">
                                                                                @if($item['code'])<span class="text-gray-500">{{ $item['code'] }} ·</span>@endif
                                                                                {{ $item['name'] }}
                                                                            </div>
                                                                            <div class="text-[11px] text-gray-400 truncate">
                                                                                {{ $item['company'] ?? '—' }} · Paso {{ $item['step'] }}
                                                                            </div>
                                                                        </div>
                                                                        <span @class([
                                                                            'shrink-0 text-[11px] font-semibold whitespace-nowrap',
                                                                            'text-red-600' => $item['days'] >= 7,
                                                                            'text-yellow-600' => $item['days'] >= 3 && $item['days'] < 7,
                                                                            'text-gray-500' => $item['days'] < 3,
                                                                        ])>
                                                                            {{ $item['days'] === 0 ? 'Hoy' : ($item['days'] === 1 ? '1 día' : $item['days'] . ' días') }}
                                                                        </span>
                                                                    </a>
                                                                </li>
                                                            @endforeach
                                                        </ul>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-500 text-xs">
                                        @if($person['oldest_days'] === 0)
                                            Hoy
                                        @elseif($person['oldest_days'] === 1)
                                            1 día
                                        @else
                                            {{ $person['oldest_days'] }} días
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Pendientes de mi aprobación --}}
        @if($pendingApprovals->isNotEmpty())
            <div>
                <div class="text-sm font-semibold text-[#DB0000] mb-3">Requieren tu aprobación</div>
                <div class="border rounded-lg overflow-hidden">
                    @foreach($pendingApprovals as $approval)
                        <div class="p-4 border-b last:border-b-0 flex items-center justify-between gap-4">
                            <div>
                                <div class="font-semibold text-gray-800">
                                    {{ $approval->regulation?->name ?? '—' }}
                                </div>
                                <div class="text-xs text-gray-500 mt-1">
                                    {{ $approval->regulation?->company?->procesos_name ?? '—' }}
                                    @if($approval->regulation?->processType)
                                        · {{ $approval->regulation->processType->name }}
                                    @endif
                                    · Paso {{ $approval->step_number }}
                                </div>
                            </div>
                            <a href="{{ route('processes.show', $approval->regulation) }}"
                               class="shrink-0 text-sm font-medium text-brand hover:underline">
                                Revisar
                            </a>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Procedimientos recientes --}}
        <div>
            <div class="text-sm font-semibold text-gray-700 mb-3">Procedimientos recientes</div>
            <div class="border rounded-lg overflow-hidden">
                @forelse($recent as $regulation)
                    <div class="p-4 border-b last:border-b-0 flex items-center justify-between gap-4">
                        <div>
                            <div class="font-semibold text-gray-800">{{ $regulation->name }}</div>
                            <div class="text-xs text-gray-500 mt-1">
                                {{ $regulation->company?->procesos_name ?? '—' }}
                                @if($regulation->processType)
                                    · {{ $regulation->processType->name }}
                                @endif
                                @if($regulation->approval_status)
                                    · {{ $regulation->approvalStatusLabel() }}
                                @endif
                            </div>
                        </div>
                        <a href="{{ route('processes.show', $regulation) }}"
                           class="shrink-0 text-sm font-medium text-brand hover:underline">
                            Abrir
                        </a>
                    </div>
                @empty
                    <div class="p-4 text-sm text-gray-500">No hay procedimientos registrados.</div>
                @endforelse
            </div>
        </div>
    </div>

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const cd = @json($chartData ?? []);

        // ── Paleta de colores ────────────────────────────────────────────────────
        const BLUE   = '#5B1690';
        const YELLOW = '#FFB529';
        const RED    = '#DB0000';
        const GREEN  = '#22C55E';
        const ORANGE = '#F97316';
        const GRAY   = '#D1D5DB';

        // ── Gráfica 1: Estado del flujo (donut) ─────────────────────────────────
        new Chart(document.getElementById('chartStatus'), {
            type: 'doughnut',
            data: {
                labels: ['En revisión', 'En autorización', 'Aprobado', 'Rechazado', 'Sin asignar'],
                datasets: [{
                    data: cd.statusData ?? [0,0,0,0,0],
                    backgroundColor: [YELLOW, BLUE, GREEN, RED, GRAY],
                    borderWidth: 1,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed}`,
                        },
                    },
                },
            },
        });

        // ── Gráfica 2: Días promedio por paso del flujo (barra agrupada) ─────────
        (function () {
            const dd = cd.daysData ?? {};
            new Chart(document.getElementById('chartDays'), {
                type: 'bar',
                data: {
                    labels: dd.stepLabels ?? ['Paso 1', 'Paso 2', 'Paso 3', 'Paso 4'],
                    datasets: [
                        {
                            label: 'Aprobado',
                            data: dd.approved ?? [null, null, null, null],
                            backgroundColor: 'rgba(34,197,94,0.80)',
                            borderRadius: 4,
                        },
                        {
                            label: 'Rechazado',
                            data: dd.rejected ?? [null, null, null, null],
                            backgroundColor: 'rgba(219,0,0,0.75)',
                            borderRadius: 4,
                        },
                        {
                            label: 'En espera',
                            data: dd.pending ?? [null, null, null, null],
                            backgroundColor: 'rgba(255,181,41,0.85)',
                            borderRadius: 4,
                        },
                    ],
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                        tooltip: {
                            callbacks: {
                                label: ctx => {
                                    const days = ctx.parsed.y;
                                    if (days === null || days === undefined) return '';
                                    const counts = [dd.countApproved, dd.countRejected, dd.countPending];
                                    const cnt = (counts[ctx.datasetIndex] ?? [])[ctx.dataIndex] ?? 0;
                                    return ` ${ctx.dataset.label}: ${days} día${days !== 1 ? 's' : ''} · ${cnt} reg.`;
                                },
                            },
                        },
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: { display: true, text: 'Días promedio', font: { size: 10 }, color: '#6B7280' },
                            ticks: { font: { size: 10 } },
                            grid: { color: '#F3F4F6' },
                        },
                        x: { ticks: { font: { size: 10 } }, grid: { display: false } },
                    },
                },
            });
        })();

        // ── Gráfica 3: Actividad semanal (línea) ────────────────────────────────
        new Chart(document.getElementById('chartWeekly'), {
            type: 'line',
            data: {
                labels: cd.weeklyLabels ?? [],
                datasets: [
                    {
                        label: 'Aprobados',
                        data: cd.weeklyApproved ?? [],
                        borderColor: GREEN,
                        backgroundColor: 'rgba(34,197,94,0.10)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: GREEN,
                    },
                    {
                        label: 'Rechazados',
                        data: cd.weeklyRejected ?? [],
                        borderColor: RED,
                        backgroundColor: 'rgba(219,0,0,0.08)',
                        fill: true,
                        tension: 0.35,
                        pointRadius: 3,
                        pointBackgroundColor: RED,
                    },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#F3F4F6' },
                    },
                    x: { ticks: { font: { size: 11 } }, grid: { display: false } },
                },
            },
        });

        // ── Gráfica 4: Nivel de impacto (donut) ─────────────────────────────────
        new Chart(document.getElementById('chartImpact'), {
            type: 'doughnut',
            data: {
                labels: ['Alto', 'Medio-Alto', 'Medio', 'Bajo'],
                datasets: [{
                    data: cd.impactData ?? [0,0,0,0],
                    backgroundColor: [RED, ORANGE, YELLOW, GREEN],
                    borderWidth: 1,
                    borderColor: '#fff',
                }],
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.label}: ${ctx.parsed}`,
                        },
                    },
                },
            },
        });
    </script>
</x-layouts.vigia>
