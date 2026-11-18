<x-layouts.vigia :title="'Tablero'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Tablero</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-brand">Tablero de cumplimiento</h1>
        </div>

        {{-- Nivel de cumplimiento: general, por impacto y por empresa / tipo de activo --}}
        @php
            $lvStates = \App\Application\Compliance\ComplianceLevelService::STATES;
            $lvImpacts = \App\Application\Compliance\ComplianceLevelService::IMPACTS;
            $lvOverall = $level['overall'];
            $lvBreakdown = $levelFilters['company_id'] ? $level['by_type'] : $level['by_company'];
            $lvFiltered = $levelFilters['company_id'] || $levelFilters['asset_type_id'] || $levelFilters['impact'];
            $lvQuery = array_filter([
                'empresa' => $levelFilters['company_id'],
                'tipo_activo' => $levelFilters['asset_type_id'],
                'impacto' => $levelFilters['impact'],
            ]);
            $lvColor = fn ($pct) => $pct === null ? '#D1D5DB' : ($pct >= 80 ? '#22C55E' : ($pct >= 50 ? '#FFB529' : '#DB0000'));
        @endphp

        <section class="mt-6 border rounded-xl p-4 md:p-5">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-gray-800">Nivel de cumplimiento</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        Requerimientos de los activos. Cumplido = completado con su documento vigente; no cuentan los cancelados.
                    </p>
                </div>
                <a href="{{ route('dashboard.level.export', $lvQuery) }}"
                   class="inline-flex items-center gap-2 px-3 py-2 rounded-md bg-brand text-white text-sm font-semibold hover:opacity-90">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16"/></svg>
                    Exportar Excel
                </a>
            </div>

            <form method="GET" action="{{ route('dashboard') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-3 lg:grid-cols-4 gap-3 items-end">
                <label class="text-xs font-medium text-gray-600">
                    Empresa
                    <select name="empresa" onchange="this.form.submit()" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:ring-brand focus:border-brand">
                        <option value="">Todas</option>
                        @foreach($companies as $c)
                            <option value="{{ $c->id }}" @selected($levelFilters['company_id'] === $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-gray-600">
                    Tipo de activo
                    <select name="tipo_activo" onchange="this.form.submit()" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:ring-brand focus:border-brand">
                        <option value="">Todos</option>
                        @foreach($assetTypes as $t)
                            <option value="{{ $t->id }}" @selected($levelFilters['asset_type_id'] === $t->id)>{{ $t->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="text-xs font-medium text-gray-600">
                    Nivel de impacto
                    <select name="impacto" onchange="this.form.submit()" class="mt-1 w-full rounded-md border-gray-300 text-sm focus:ring-brand focus:border-brand">
                        <option value="">Todos</option>
                        @foreach($lvImpacts as $key => $label)
                            <option value="{{ $key }}" @selected($levelFilters['impact'] === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <noscript><button class="px-3 py-2 rounded-md border text-sm">Filtrar</button></noscript>
                @if($lvFiltered)
                    <a href="{{ route('dashboard') }}" class="text-sm text-brand hover:underline py-2">Quitar filtros</a>
                @endif
            </form>

            @if($lvOverall['total'] === 0)
                <div class="mt-5 p-6 text-center text-sm text-gray-500 border rounded-lg">
                    No hay requerimientos con estos filtros.
                </div>
            @else
                <div class="mt-5 grid grid-cols-1 lg:grid-cols-5 gap-6">
                    {{-- General --}}
                    <div class="lg:col-span-2 flex flex-col items-center">
                        <div class="text-sm font-semibold text-gray-700 self-start">General</div>
                        <div class="relative w-full mt-2" style="max-width: 240px">
                            <canvas id="chartLevelOverall"></canvas>
                            <div class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                <span class="text-3xl font-bold text-gray-800">{{ number_format($lvOverall['pct'], 1) }}%</span>
                                <span class="text-xs text-gray-500">cumplido</span>
                            </div>
                        </div>
                        <div class="mt-3 text-sm text-gray-600">
                            {{ number_format($lvOverall['cumplido']) }} de {{ number_format($lvOverall['total']) }} requerimientos
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-x-6 gap-y-1 text-xs text-gray-600">
                            @foreach(['cumplido' => '#22C55E', 'en_tramite' => '#5B1690', 'pendiente' => '#FFB529', 'vencido' => '#DB0000'] as $k => $color)
                                <div class="flex items-center gap-2">
                                    <span class="w-2.5 h-2.5 rounded-sm" style="background: {{ $color }}"></span>
                                    {{ $lvStates[$k] }}: <span class="font-semibold">{{ number_format($lvOverall[$k]) }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Por impacto --}}
                    <div class="lg:col-span-3">
                        <div class="text-sm font-semibold text-gray-700">Por nivel de impacto</div>
                        <div class="mt-2" style="height: {{ max(160, count($level['by_impact']) * 56) }}px">
                            <canvas id="chartLevelImpact"></canvas>
                        </div>
                        <div class="mt-3 grid grid-cols-2 sm:grid-cols-4 gap-2">
                            @foreach($level['by_impact'] as $row)
                                <div class="border rounded-lg px-3 py-2">
                                    <div class="text-xs text-gray-500 truncate">{{ $row['name'] }}</div>
                                    <div class="text-lg font-bold text-gray-800">{{ $row['pct'] === null ? '—' : number_format($row['pct'], 1) . '%' }}</div>
                                    <div class="text-xs text-gray-500">{{ number_format($row['cumplido']) }} / {{ number_format($row['total']) }}</div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- Por empresa (o por tipo de activo si ya se eligió una empresa) --}}
                <div class="mt-6">
                    <div class="text-sm font-semibold text-gray-700 mb-2">
                        {{ $levelFilters['company_id'] ? 'Por tipo de activo' : 'Por empresa' }}
                    </div>
                    <div class="border rounded-lg overflow-x-auto max-h-80 overflow-y-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50 text-xs text-gray-500 sticky top-0">
                                <tr>
                                    <th class="text-left font-medium px-3 py-2">{{ $levelFilters['company_id'] ? 'Tipo de activo' : 'Empresa' }}</th>
                                    <th class="text-right font-medium px-3 py-2">Requerimientos</th>
                                    @foreach($lvStates as $label)
                                        <th class="text-right font-medium px-3 py-2 hidden md:table-cell">{{ $label }}</th>
                                    @endforeach
                                    <th class="text-left font-medium px-3 py-2 w-48">Cumplimiento</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y">
                                @foreach($lvBreakdown as $row)
                                    <tr>
                                        <td class="px-3 py-2 text-gray-800">{{ $row['name'] }}</td>
                                        <td class="px-3 py-2 text-right tabular-nums">{{ number_format($row['total']) }}</td>
                                        @foreach(array_keys($lvStates) as $k)
                                            <td class="px-3 py-2 text-right tabular-nums text-gray-600 hidden md:table-cell">{{ number_format($row[$k]) }}</td>
                                        @endforeach
                                        <td class="px-3 py-2">
                                            <div class="flex items-center gap-2">
                                                <div class="flex-1 h-2 rounded-full bg-gray-100 overflow-hidden">
                                                    <div class="h-full" style="width: {{ $row['pct'] ?? 0 }}%; background: {{ $lvColor($row['pct']) }}"></div>
                                                </div>
                                                <span class="text-xs font-semibold tabular-nums w-12 text-right">{{ $row['pct'] === null ? '—' : number_format($row['pct'], 1) . '%' }}</span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif
        </section>

        {{-- Próximos a vencer (mismos filtros), de 20 en 20 --}}
        <section id="proximos" class="mt-6 border rounded-xl p-4 md:p-5">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <h2 class="text-lg font-semibold text-gray-800">Próximos a vencer</h2>
                @if($upcoming->total() > 0)
                    <span class="text-xs text-gray-500">
                        {{ number_format($upcoming->firstItem()) }}–{{ number_format($upcoming->lastItem()) }} de {{ number_format($upcoming->total()) }}
                    </span>
                @endif
            </div>
            <p class="text-xs text-gray-500 mt-0.5">
                Vencimiento del documento si ya está cumplido, o fecha límite si sigue pendiente. No incluye vencidos ni en trámite.
            </p>

            @if($upcoming->isEmpty())
                <div class="mt-4 p-6 text-center text-sm text-gray-500 border rounded-lg">
                    No hay requerimientos próximos a vencer con estos filtros.
                </div>
            @else
                <div class="mt-4 border rounded-lg overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50 text-xs text-gray-500">
                            <tr>
                                <th class="text-left font-medium px-3 py-2">Vence</th>
                                <th class="text-left font-medium px-3 py-2">Requerimiento</th>
                                <th class="text-left font-medium px-3 py-2">Activo</th>
                                <th class="text-left font-medium px-3 py-2 hidden md:table-cell">Empresa</th>
                                <th class="text-left font-medium px-3 py-2 hidden lg:table-cell">Tipo de activo</th>
                                <th class="text-left font-medium px-3 py-2 hidden md:table-cell">Impacto</th>
                                <th class="px-3 py-2"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y">
                            @foreach($upcoming as $r)
                                @php
                                    $vence = \Illuminate\Support\Carbon::parse($r->vence);
                                    $dias = (int) today()->diffInDays($vence, false);
                                    $badge = $dias <= 30 ? 'background:#FEE2E2;color:#B91C1C' : ($dias <= 60 ? 'background:#FEF3C7;color:#92400E' : 'background:#F3F4F6;color:#374151');
                                @endphp
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="text-gray-800">{{ $vence->format('d/m/Y') }}</div>
                                        <span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-xs font-medium" style="{{ $badge }}">
                                            {{ $dias === 0 ? 'Hoy' : ($dias === 1 ? 'Mañana' : "En {$dias} días") }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-gray-800">
                                        {{ $r->template_name ?? $r->type ?? 'Requerimiento' }}
                                        @if($r->status?->value === 'completed')
                                            <span class="block text-xs text-gray-500">Cumplido · vence el documento</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-2 text-gray-700">{{ $r->asset_name }}</td>
                                    <td class="px-3 py-2 text-gray-600 hidden md:table-cell">{{ $r->company_name }}</td>
                                    <td class="px-3 py-2 text-gray-600 hidden lg:table-cell">{{ $r->asset_type_name ?? '—' }}</td>
                                    <td class="px-3 py-2 text-gray-600 hidden md:table-cell">{{ $lvImpacts[$r->impact] ?? $lvImpacts['sin'] }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <a href="{{ route('assets.requirements.show', [$r->asset_id, $r->id]) }}" class="text-brand font-medium hover:underline">Abrir</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($upcoming->hasPages())
                    <div class="mt-4">
                        {{ $upcoming->fragment('proximos')->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>

    {{-- Gráficas del nivel de cumplimiento --}}
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        (function () {
            const level = @json($level);
            const STATES = @json(\App\Application\Compliance\ComplianceLevelService::STATES);
            const COLORS = { cumplido: '#22C55E', en_tramite: '#5B1690', pendiente: '#FFB529', vencido: '#DB0000' };
            const keys = Object.keys(COLORS);
            const fmt = n => Number(n).toLocaleString('es-MX');

            const overall = document.getElementById('chartLevelOverall');
            if (overall && level.overall.total > 0) {
                new Chart(overall, {
                    type: 'doughnut',
                    data: {
                        labels: keys.map(k => STATES[k]),
                        datasets: [{
                            data: keys.map(k => level.overall[k]),
                            backgroundColor: keys.map(k => COLORS[k]),
                            borderColor: '#fff',
                            borderWidth: 2,
                        }],
                    },
                    options: {
                        cutout: '72%',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ` ${ctx.label}: ${fmt(ctx.parsed)} (${(ctx.parsed * 100 / level.overall.total).toFixed(1)}%)`,
                                },
                            },
                        },
                    },
                });
            }

            const impact = document.getElementById('chartLevelImpact');
            if (impact && level.by_impact.length) {
                // Barras al 100%: qué parte de cada nivel de impacto está en cada estado.
                new Chart(impact, {
                    type: 'bar',
                    data: {
                        labels: level.by_impact.map(r => r.name),
                        datasets: keys.map(k => ({
                            label: STATES[k],
                            data: level.by_impact.map(r => r.total ? +(r[k] * 100 / r.total).toFixed(1) : 0),
                            counts: level.by_impact.map(r => r[k]),
                            backgroundColor: COLORS[k],
                            borderRadius: 3,
                            barThickness: 26,
                        })),
                    },
                    options: {
                        indexAxis: 'y',
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            x: { stacked: true, max: 100, ticks: { callback: v => v + '%' } },
                            y: { stacked: true, grid: { display: false } },
                        },
                        plugins: {
                            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } },
                            tooltip: {
                                callbacks: {
                                    label: ctx => ` ${ctx.dataset.label}: ${ctx.parsed.x}% (${fmt(ctx.dataset.counts[ctx.dataIndex])})`,
                                },
                            },
                        },
                    },
                });
            }
        })();
    </script>
</x-layouts.vigia>