<x-layouts.vigia title="Mis hallazgos">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis hallazgos</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis hallazgos',
        'subtitle' => 'Lo que el auditor anotó en tus auditorías y donde te toca mostrar evidencia.',
    ])

    @include('mi-espacio.partials.tabs', [
        'tabs'  => ['por-atender' => 'Por atender', 'concluidas' => 'Concluidas'],
        'route' => 'mi-espacio.findings',
    ])

    <form method="GET" action="{{ route('mi-espacio.findings') }}" class="mb-7">
        <input type="hidden" name="tab" value="{{ $tab }}">
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por punto, proceso o código..."
               class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
    </form>

    @if($findings->isEmpty())
        <div class="rounded-2xl border border-gray-200 bg-white p-8">
            <div class="flex flex-col items-center py-8 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand">
                    <x-icon name="check" class="h-5 w-5" />
                </span>
                <p class="mt-4 text-[15px] text-gray-600">
                    {{ $tab === 'por-atender' ? 'No tienes hallazgos pendientes.' : 'No hay hallazgos concluidos.' }}
                </p>
            </div>
        </div>
    @else
        <div class="space-y-4">
            @foreach($findings as $finding)
                @php $regulation = $finding->audit->regulation; @endphp
                <a href="{{ route('mi-espacio.findings.show', $finding) }}"
                   class="block rounded-2xl border bg-white p-6 transition hover:border-brand hover:shadow-sm {{ $finding->needsEvidence() ? 'border-gray-200' : 'border-gray-100' }}">
                    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                @if($regulation?->code)
                                    <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">{{ $regulation->code }}</span>
                                @endif
                                @if($finding->severityLabel())
                                    <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand">Severidad {{ $finding->severityLabel() }}</span>
                                @endif
                            </div>
                            <h2 class="mt-2 text-lg font-semibold text-gray-900">{{ $finding->point }}</h2>
                            <p class="mt-1 line-clamp-2 text-[15px] text-gray-600">{{ $finding->description }}</p>
                            <p class="mt-2 text-sm text-gray-500">
                                {{ $regulation?->name }} · Auditor: {{ $finding->audit->auditor?->name ?? '—' }}
                            </p>
                            @if($finding->status === \App\Models\AuditFinding::STATUS_REJECTED && $finding->latestSubmission?->review_comment)
                                <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">
                                    <span class="font-semibold">Motivo del rechazo:</span> {{ $finding->latestSubmission->review_comment }}
                                </p>
                            @endif
                        </div>

                        <div class="shrink-0 md:text-right">
                            @include('mi-espacio.partials.finding-status', ['finding' => $finding])
                            @if($tab === 'concluidas')
                                <p class="mt-2 text-sm text-gray-500">Cerrado el {{ $finding->closed_at?->format('d/m/Y') }}</p>
                            @else
                                <p class="mt-2 text-sm {{ $finding->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">
                                    {{ $finding->due_at ? 'Fecha límite: ' . $finding->due_at->format('d/m/Y') : 'Fecha límite por definir' }}
                                    @if($finding->isOverdue()) · Vencido @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
</x-layouts.vigia>
