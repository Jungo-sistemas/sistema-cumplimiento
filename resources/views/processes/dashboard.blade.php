<x-layouts.vigia title="Tablero · Procesos">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Tablero</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 space-y-8">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Tablero de procesos</h1>

        {{-- Cards de resumen --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
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

        {{-- Fila de gráficas 1: Estado del flujo + Posición en el flujo --}}
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            {{-- Gráfica: Estado del flujo de aprobación (donut) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-4">Estado del flujo de aprobación</div>
                <div class="flex items-center justify-center" style="height:220px;">
                    <canvas id="chartStatus"></canvas>
                </div>
            </div>

            {{-- Gráfica: Posición en el flujo (barra horizontal) --}}
            <div class="border rounded-xl p-5">
                <div class="text-sm font-semibold text-gray-700 mb-4">Documentos por paso del flujo</div>
                <div style="height:220px;">
                    <canvas id="chartStep"></canvas>
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

        {{-- Documentos recientes --}}
        <div>
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

    {{-- Chart.js --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        const cd = @json($chartData ?? []);

        // ── Paleta de colores ────────────────────────────────────────────────────
        const BLUE   = '#1A428A';
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

        // ── Gráfica 2: Posición en el flujo (barra horizontal) ──────────────────
        new Chart(document.getElementById('chartStep'), {
            type: 'bar',
            data: {
                labels: ['Paso 1 · Líder', 'Paso 2', 'Paso 3', 'Paso 4 · Dirección'],
                datasets: [{
                    label: 'Documentos',
                    data: cd.stepData ?? [0,0,0,0],
                    backgroundColor: [
                        'rgba(26,66,138,0.75)',
                        'rgba(26,66,138,0.60)',
                        'rgba(26,66,138,0.45)',
                        'rgba(26,66,138,0.30)',
                    ],
                    borderRadius: 4,
                }],
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: ctx => ` ${ctx.parsed.x} documento${ctx.parsed.x !== 1 ? 's' : ''}`,
                        },
                    },
                },
                scales: {
                    x: {
                        beginAtZero: true,
                        ticks: { precision: 0, font: { size: 11 } },
                        grid: { color: '#F3F4F6' },
                    },
                    y: { ticks: { font: { size: 11 } }, grid: { display: false } },
                },
            },
        });

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
