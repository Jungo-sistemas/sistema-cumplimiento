<x-layouts.vigia title="Inicio · Auditorías">
    @php
        $cards = [
            ['label' => 'Auditorías en ejecución', 'valor' => $inProgress,   'texto' => 'Programadas y en captura del checklist durante la llamada de Teams.', 'boton' => 'Ver auditorías',       'url' => route('auditorias.audits.index', ['tab' => 'ejecucion'])],
            ['label' => 'Hallazgos abiertos',      'valor' => $openFindings, 'texto' => $toReview === 1 ? 'Tienes 1 causa raíz o evidencia por revisar.' : "Tienes {$toReview} causas raíz o evidencias por revisar.", 'boton' => 'Ver hallazgos', 'url' => route('auditorias.findings.index')],
            ['label' => 'Auditorías finalizadas',  'valor' => $completed,    'texto' => 'Resultados publicados de tus auditorías.', 'boton' => 'Consultar auditorías', 'url' => route('auditorias.audits.index', ['tab' => 'finalizadas'])],
        ];
    @endphp

    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Continúa las auditorías que tienes asignadas.',
    ])

    <div class="grid grid-cols-1 gap-6 md:grid-cols-3">
        @foreach($cards as $card)
            <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-7">
                <h2 class="text-lg font-semibold text-gray-900">{{ $card['label'] }}</h2>
                <div class="mt-4 text-5xl font-extrabold text-brand-ink">{{ $card['valor'] }}</div>
                <p class="mt-5 text-[15px] text-gray-600">{{ $card['texto'] }}</p>
                <div class="mt-6">
                    <a href="{{ $card['url'] }}" class="inline-flex rounded-lg bg-brand-50 px-5 py-3 text-[15px] font-semibold text-brand hover:bg-brand-100">{{ $card['boton'] }}</a>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-7">
        <h2 class="text-xl font-semibold text-gray-900">Continuar auditoría</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($pending as $audit)
                <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ $audit->regulation?->name }}</div>
                        <div class="mt-2 text-[15px] text-gray-600">{{ $audit->auditorStage() }}{{ $audit->scheduled_at ? ' · ' . $audit->scheduledLabel() : '' }}</div>
                    </div>
                    <a href="{{ route('auditorias.audits.checklist', $audit) }}" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">{{ $audit->auditorStage() === \App\Models\Audit::STAGE_CAPTURE ? 'Continuar checklist' : 'Consultar' }}</a>
                </div>
            @empty
                <p class="mt-5 text-[15px] text-gray-600">No tienes auditorías pendientes.</p>
            @endforelse
        </div>
    </div>
</x-layouts.vigia>
