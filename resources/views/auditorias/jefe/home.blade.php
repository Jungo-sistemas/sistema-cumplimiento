@use('App\Models\Audit')
{{-- Inicio del Jefe de Auditoría: avance del programa del año y auditorías del mes (datos reales). --}}
<x-layouts.vigia title="Inicio · Auditorías">
    @php
        // Los mismos colores que la lista del auditor (auditorias/audits.blade.php).
        $colores = [
            Audit::STAGE_UNSCHEDULED => 'bg-gray-100 text-gray-700',
            Audit::STAGE_SCHEDULED   => 'bg-brand-50 text-brand',
            Audit::STAGE_CAPTURE     => 'bg-amber-50 text-amber-700',
            Audit::STAGE_DONE        => 'bg-green-50 text-green-700',
        ];
    @endphp

    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Este es el avance del programa anual de auditorías.',
    ])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-7">
            <h2 class="text-lg font-semibold text-gray-900">Programa {{ $anio }}</h2>
            <div class="mt-4 text-5xl font-extrabold text-brand-ink">{{ $realizadas }} <span class="text-2xl font-semibold text-gray-400">de {{ $total }}</span></div>
            <p class="mt-5 text-[15px] text-gray-600">Auditorías realizadas del año ({{ $avance }} %). Las canceladas no cuentan.</p>
            <div class="mt-3 h-1.5 w-full rounded-full bg-gray-200">
                <div class="h-1.5 rounded-full bg-brand" style="width: {{ $avance }}%"></div>
            </div>
            <div class="mt-6">
                <a href="{{ route('auditorias.jefe.program') }}" class="inline-flex rounded-lg bg-brand-50 px-5 py-3 text-[15px] font-semibold text-brand hover:bg-brand-100">Ver programa</a>
            </div>
        </div>

        <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-7">
            <h2 class="text-lg font-semibold text-gray-900">Sin fecha acordada</h2>
            <div class="mt-4 text-5xl font-extrabold text-brand-ink">{{ $sinFecha }}</div>
            <p class="mt-5 text-[15px] text-gray-600">Auditorías del año que el auditor todavía no acuerda con el auditado.</p>
            <div class="mt-6">
                <a href="{{ route('auditorias.jefe.program') }}" class="inline-flex rounded-lg bg-brand-50 px-5 py-3 text-[15px] font-semibold text-brand hover:bg-brand-100">Ver programa</a>
            </div>
        </div>
    </div>

    <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-7">
        <h2 class="text-xl font-semibold text-gray-900">Este mes · {{ $mes }}</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($delMes as $audit)
                @php $etapa = $audit->auditorStage(); @endphp
                <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ $audit->regulation?->name }}</div>
                        <div class="mt-2 text-[15px] text-gray-600">
                            {{ collect([$audit->company?->procesos_name ?? $audit->company?->name, $audit->auditor?->name ?? 'Sin auditor asignado', $audit->scheduledLabel() ?? 'Sin fecha'])->filter()->join(' · ') }}
                        </div>
                    </div>
                    <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $colores[$etapa] }}">{{ $etapa }}</span>
                </div>
            @empty
                <p class="mt-5 text-[15px] text-gray-600">No hay auditorías este mes.</p>
            @endforelse
        </div>
    </div>
</x-layouts.vigia>
