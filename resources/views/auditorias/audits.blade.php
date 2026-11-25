@use('App\Models\Audit')
<x-layouts.vigia title="Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Auditorías</span>
    </x-slot>

    @php
        $colores = [
            Audit::STAGE_UNSCHEDULED => 'bg-gray-100 text-gray-700',
            Audit::STAGE_SCHEDULED   => 'bg-brand-50 text-brand',
            Audit::STAGE_READY       => 'bg-blue-50 text-blue-700',
            Audit::STAGE_CAPTURE     => 'bg-amber-50 text-amber-700',
            Audit::STAGE_DONE        => 'bg-green-50 text-green-700',
        ];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        @include('mi-espacio.partials.header', [
            'title'    => 'Auditorías',
            'subtitle' => 'Programa la fecha, captura y califica el checklist durante la llamada de Microsoft Teams.',
        ])
        @if($canCreate)
            <button type="button" @click="$dispatch('nueva-auditoria')"
                    class="rounded-lg bg-brand px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Programar auditoría</button>
        @endif
    </div>

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mb-7 flex gap-6 border-b border-gray-200">
        @foreach(['ejecucion' => 'En ejecución', 'finalizadas' => 'Finalizadas'] as $key => $label)
            <a href="{{ route('auditorias.audits.index', ['tab' => $key] + $filters) }}"
                class="-mb-px border-b-2 pb-3 text-base font-semibold {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </div>

    @include('auditorias.partials.filters', [
        'action'      => route('auditorias.audits.index'),
        'placeholder' => 'Buscar por procedimiento o persona auditada...',
        'selects'     => $tab === 'ejecucion'
            ? [['name' => 'estado', 'label' => 'Estado', 'all' => 'Todos los estados', 'options' => collect([Audit::STAGE_UNSCHEDULED, Audit::STAGE_SCHEDULED, Audit::STAGE_READY, Audit::STAGE_CAPTURE])->mapWithKeys(fn ($s) => [$s => $s])->all()]]
            : [],
    ])

    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-8">
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-5">Procedimiento</th>
                    <th class="px-4 py-5">Persona auditada</th>
                    <th class="px-4 py-5">Estado</th>
                    <th class="px-4 py-5">Calificación</th>
                    <th class="px-4 py-5"></th>
                </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $audit)
                        @php $stage = $audit->auditorStage(); @endphp
                        <tr>
                            <td class="px-4 py-5">
                                <div class="font-semibold text-gray-900">{{ $audit->regulation?->code ? $audit->regulation->code . ' · ' : '' }}{{ $audit->regulation?->name }}</div>
                                <div class="mt-1 text-sm text-gray-500">
                                    {{ $audit->company?->procesos_name ?? $audit->company?->name }} · {{ $tab === 'finalizadas' ? $audit->shortDateLabel() : ($audit->scheduledLabel() ?? 'Sin fecha') }}
                                    @if(auth()->user()->isAdmin() && $audit->auditor) · Auditor: {{ $audit->auditor->name }}@endif
                                </div>
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $audit->auditees->pluck('name')->join(', ') }}</td>
                            <td class="px-4 py-5">
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $colores[$stage] }}">{{ $stage }}</span>
                                @if($stage === Audit::STAGE_SCHEDULED && $audit->notice_sent_at)
                                    <div class="mt-1 text-xs text-gray-500">Auditados avisados</div>
                                @endif
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $audit->result ?? '—' }}</td>
                            <td class="px-4 py-5 text-right">
                                <div class="flex justify-end gap-2">
                                    @if($tab === 'ejecucion' && $audit->isRunBy(auth()->user()) && $audit->canStart())
                                        @include('auditorias.partials.start-button', ['audit' => $audit, 'size' => 'sm'])
                                    @endif
                                    @if($tab === 'ejecucion' && $audit->isRunBy(auth()->user()) && ! $audit->started_at)
                                        <button type="button"
                                                @click="$dispatch('programar-fecha', @js(['url' => route('auditorias.audits.schedule', $audit), 'proceso' => $audit->regulation?->name, 'auditados' => $audit->auditees->pluck('name')->join(', '), 'actual' => $audit->scheduledLabel(), 'fecha' => $audit->scheduled_at?->toDateString(), 'hora' => $audit->scheduled_at?->format('H:i')]))"
                                                class="whitespace-nowrap rounded-lg border-2 border-gray-300 px-3 py-1.5 text-sm font-semibold text-brand-ink hover:bg-gray-50">{{ $audit->scheduled_at ? 'Cambiar fecha' : 'Poner fecha' }}</button>
                                    @endif
                                    <a href="{{ $tab === 'finalizadas' ? route('auditorias.audits.result', $audit) : route('auditorias.audits.checklist', $audit) }}"
                                       class="whitespace-nowrap inline-flex rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">{{ $tab === 'finalizadas' ? 'Ver resultado' : ($stage === Audit::STAGE_CAPTURE && $audit->isRunBy(auth()->user()) ? 'Capturar checklist' : 'Consultar') }}</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ $filters ? 'Ninguna auditoría coincide con los filtros.' : 'No hay auditorías en esta pestaña.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('auditorias.partials.reschedule-modal')
    @if($canCreate)
        @include('auditorias.partials.new-audit-modal')
    @endif
</x-layouts.vigia>
