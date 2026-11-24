@use('App\Http\Controllers\AuditorController')
<x-layouts.vigia title="Resultado · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index', ['tab' => 'finalizadas']) }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Resultado de auditoría</span>
    </x-slot>

    @php
        $procedimiento = trim(($audit->regulation?->code ? $audit->regulation->code . ' · ' : '') . ($audit->regulation?->name ?? ''));
        $incumplimientos = $items->filter(fn ($item) => in_array($item->result, \App\Models\AuditChecklistItem::NEEDS_FINDING, true));
        $asistieron = collect($people)->filter(fn ($p) => in_array($p['id'], $audit->attendeeIds(), true))->count();
        $resultados = [
            'cumple'    => 'bg-gray-100 text-gray-700',
            'parcial'   => 'bg-amber-50 text-amber-700',
            'no_cumple' => 'bg-red-50 text-red-700',
            'no_aplica' => 'border border-gray-200 bg-white text-gray-500',
        ];
        $severidades = AuditorController::SEVERITY_COLORS;
    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index', ['tab' => 'finalizadas']) }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        <span class="rounded-full bg-green-50 px-3 py-1 text-xs font-semibold text-green-700">Finalizada</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Resultado de auditoría',
        'subtitle' => $procedimiento . ' · ' . $audit->auditees->pluck('name')->join(', ') . ' · ' . $audit->shortDateLabel(),
    ])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Calificación final</div>
            <div class="mt-2 text-3xl font-extrabold text-brand-ink">{{ $audit->result ?? '—' }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Criterios con incumplimientos</div>
            <div class="mt-2 text-3xl font-extrabold text-brand-ink">{{ $incumplimientos->count() }}</div>
        </div>
        <div class="rounded-2xl border border-gray-200 bg-white p-6">
            <div class="text-sm text-gray-600">Auditor</div>
            <div class="mt-2 text-xl font-bold text-brand-ink">{{ $audit->auditor?->name ?? '—' }}</div>
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Qué hay que corregir</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($incumplimientos as $i => $criterio)
                <div class="flex flex-wrap items-start justify-between gap-4 py-5">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $i + 1 }}. {{ $criterio->text }}</div>
                        @if($criterio->observations)
                            <p class="mt-1 whitespace-pre-line text-[15px] text-gray-700">{{ $criterio->observations }}</p>
                        @endif
                        <p class="mt-3 text-sm text-gray-500">Calificación del criterio: {{ $criterio->points() }} / 100</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $resultados[$criterio->result] }}">{{ $criterio->resultLabel() }}</span>
                </div>
            @empty
                <p class="py-5 text-[15px] text-gray-600">No hubo criterios con Parcial o No cumple.</p>
            @endforelse
        </div>
    </div>

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Hallazgos registrados</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($findings as $hallazgo)
                <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $hallazgo['titulo'] }}</div>
                        <p class="mt-1 text-sm text-gray-500">{{ $hallazgo['codigo'] }} · {{ $hallazgo['responsable'] }} · {{ $hallazgo['seguimiento'] }}</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $severidades[$hallazgo['severidad']] ?? 'bg-gray-100 text-gray-700' }}">{{ $hallazgo['severidad'] }}</span>
                        <button type="button" @click="$dispatch('abrir-hallazgo', @js($hallazgo))"
                            class="whitespace-nowrap rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">Ver hallazgo</button>
                    </div>
                </div>
            @empty
                <p class="py-5 text-[15px] text-gray-600">No se registraron hallazgos en esta auditoría.</p>
            @endforelse
        </div>
    </div>

    @if(filled($audit->conclusions))
        <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
            <h2 class="text-lg font-semibold text-gray-900">Conclusiones del auditor</h2>
            <p class="mt-3 whitespace-pre-line text-[15px] text-gray-700">{{ $audit->conclusions }}</p>
        </div>
    @endif

    <div class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-gray-900">Lista de asistencia</h2>
            <span class="text-sm font-semibold text-brand">{{ $asistieron }} de {{ count($people) }} asistieron</span>
        </div>

        <div class="mt-3 divide-y divide-gray-200">
            @foreach($people as $participante)
                @php $asistio = in_array($participante['id'], $audit->attendeeIds(), true); @endphp
                <div class="flex flex-wrap items-center justify-between gap-4 py-4">
                    <div>
                        <div class="font-semibold text-gray-900">{{ $participante['nombre'] }}</div>
                        <p class="mt-1 text-sm text-gray-500">{{ $participante['puesto'] }} · Equipo {{ $participante['equipo'] }}</p>
                    </div>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $asistio ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-600' }}">{{ $asistio ? 'Asistió' : 'No asistió' }}</span>
                </div>
            @endforeach
        </div>
    </div>

    @if($items->isNotEmpty())
        <details class="mt-6 rounded-2xl border border-gray-200 bg-white p-6">
            <summary class="cursor-pointer text-base font-semibold text-brand">Consultar evaluación completa</summary>

            <div class="mt-4 overflow-x-auto rounded-xl border border-gray-200">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-4 py-4">Criterio</th>
                            <th class="px-4 py-4">Resultado</th>
                            <th class="px-4 py-4 text-right">Calificación</th>
                            <th class="px-4 py-4">Observación del auditor</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        @foreach($items as $criterio)
                            <tr>
                                <td class="px-4 py-4 text-gray-900">
                                    {{ $criterio->text }}
                                    @foreach($criterio->files as $file)
                                        <a href="{{ route('auditorias.support.download', $file) }}" class="mt-1 block text-xs font-semibold text-brand hover:underline">{{ $file->original_name }}</a>
                                    @endforeach
                                </td>
                                <td class="px-4 py-4">
                                    @if($criterio->result)
                                        <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $resultados[$criterio->result] }}">{{ $criterio->resultLabel() }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right text-gray-900">{{ $criterio->points() ?? '—' }}</td>
                                <td class="px-4 py-4 whitespace-pre-line text-gray-700">{{ $criterio->observations }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </details>
    @endif

    @include('auditorias.partials.finding-detail-modal')
</x-layouts.vigia>
