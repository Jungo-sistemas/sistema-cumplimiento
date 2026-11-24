<x-layouts.vigia title="Mis auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis auditorías</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis auditorías',
        'subtitle' => 'La fecha de tus auditorías. Prepara tus evidencias con el checklist del proceso.',
    ])

    @include('mi-espacio.partials.tabs', [
        'tabs'  => ['activas' => 'Activas', 'historial' => 'Historial'],
        'route' => 'mi-espacio.audits',
    ])

    <form method="GET" action="{{ route('mi-espacio.audits') }}" class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @include('mi-espacio.partials.company-filter', ['selectedCompanyId' => request()->integer('company_id') ?: null])
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre del proceso..."
               class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
    </form>

    @if($audits->isEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-8">
            <div class="flex flex-col items-center py-8 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand">
                    <x-icon name="check" class="h-5 w-5" />
                </span>
                <p class="mt-4 text-[15px] text-gray-600">
                    {{ $tab === 'activas' ? 'No tienes auditorías próximas.' : 'Todavía no tienes auditorías realizadas.' }}
                </p>
                @if($tab === 'activas')
                    <p class="mt-1 text-sm text-gray-500">Te llegará un aviso {{ \App\Models\Audit::noticeDays() }} días antes de la fecha que fije el auditor.</p>
                @endif
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($audits as $audit)
                @php
                    $regulation = $audit->regulation;
                    $canOpenProcess = $regulation && $regulation->publishedVersion;
                    $impact = \App\Models\Regulation::IMPACT_LEVELS[$regulation?->impact_level ?? ''] ?? null;
                @endphp
                <div class="rounded-2xl border border-gray-200 bg-white p-6">
                    <div class="flex flex-col gap-5 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($regulation?->code)
                                    <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">{{ $regulation->code }}</span>
                                @endif
                                @if($impact)
                                    <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand">Impacto {{ $impact }}</span>
                                @endif
                            </div>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ $regulation?->name ?? 'Proceso no disponible' }}</h2>
                            <dl class="mt-3 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                                <div><dt class="inline text-gray-400">Empresa:</dt> <dd class="inline">{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</dd></div>
                                <div>
                                    <dt class="inline text-gray-400">Auditor:</dt>
                                    <dd class="inline">
                                        {{ $audit->auditor?->name ?? 'Por asignar' }}
                                        @if($audit->auditor?->email)
                                            <a href="mailto:{{ $audit->auditor->email }}" class="text-brand hover:underline">{{ $audit->auditor->email }}</a>
                                        @endif
                                    </dd>
                                </div>
                            </dl>
                        </div>

                        <div class="shrink-0 md:text-right">
                            @if($audit->status === \App\Models\Audit::STATUS_PLANNED)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">
                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>Programada
                                </span>
                                <p class="mt-2 text-lg font-bold text-gray-900">{{ $audit->scheduledLabel() ?? ucfirst($audit->plannedMonthLabel()) }}</p>
                                <p class="text-xs text-gray-500">En vivo por Teams</p>
                            @elseif($audit->status === \App\Models\Audit::STATUS_COMPLETED)
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand">
                                    <span class="h-2 w-2 rounded-full bg-brand"></span>Realizada
                                </span>
                                <p class="mt-2 text-lg font-bold text-gray-900">{{ $audit->scheduledLabel() ?? ucfirst($audit->plannedMonthLabel()) }}</p>
                                @if($audit->result)
                                    <p class="text-sm text-gray-600">Calificación: <span class="font-semibold text-gray-900">{{ $audit->result }}</span></p>
                                @endif
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-500">
                                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>Cancelada
                                </span>
                                <p class="mt-2 text-sm text-gray-500">{{ $audit->scheduledLabel() ?? ucfirst($audit->plannedMonthLabel()) }}</p>
                            @endif
                        </div>
                    </div>

                    @if($canOpenProcess || $audit->findings_count > 0)
                        <div class="mt-5 flex flex-wrap items-center gap-3 border-t border-gray-100 pt-4">
                            @if($canOpenProcess)
                                <a href="{{ route('mi-espacio.documents.show', $regulation) }}"
                                   class="inline-flex items-center gap-2 rounded-lg bg-brand-50 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-100">
                                    <x-icon name="document" class="h-4 w-4" />
                                    Ver proceso
                                </a>
                            @endif
                            @if($audit->findings_count > 0)
                                <span class="text-sm text-gray-600">
                                    {{ $audit->findings_count }} {{ $audit->findings_count === 1 ? 'hallazgo' : 'hallazgos' }}
                                    @if($audit->open_findings_count > 0)
                                        · <span class="font-semibold text-amber-700">{{ $audit->open_findings_count }} {{ $audit->open_findings_count === 1 ? 'abierto' : 'abiertos' }}</span>
                                    @endif
                                </span>
                                @if($audit->my_open_findings_count > 0)
                                    <a href="{{ route('mi-espacio.findings') }}" class="text-sm font-semibold text-brand hover:underline">
                                        Ver mis hallazgos ({{ $audit->my_open_findings_count }})
                                    </a>
                                @endif
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</x-layouts.vigia>
