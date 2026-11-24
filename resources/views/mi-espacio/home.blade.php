<x-layouts.vigia title="Inicio">
    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Estos son tus pendientes.',
    ])

    @php
        $cards = [
            ['label' => 'Mis auditorías activas', 'valor' => $activeAuditsCount, 'texto' => 'Tus próximas auditorías, con su fecha y auditor.', 'boton' => 'Ver mis auditorías',  'url' => route('mi-espacio.audits')],
            ['label' => 'Mis hallazgos',          'valor' => $findingsCount,     'texto' => 'Hallazgos donde te toca subir evidencia.', 'boton' => 'Ver mis hallazgos', 'url' => route('mi-espacio.findings')],
            ['label' => 'Mis documentos',         'valor' => $releasedDocsCount, 'texto' => 'Procesos relacionados con tu trabajo.',          'boton' => 'Consultar documentos', 'url' => route('mi-espacio.documents')],
        ];
    @endphp

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
        <h2 class="text-xl font-semibold text-gray-900">Siguiente por atender</h2>

        @php
            $tones = [
                'red'   => 'bg-red-50 text-red-700',
                'amber' => 'bg-amber-50 text-amber-700',
                'brand' => 'bg-brand-50 text-brand',
            ];
        @endphp
        @if($nextItems->isNotEmpty())
            <ul class="mt-5 divide-y divide-gray-100">
                @foreach($nextItems as $next)
                    <li class="flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $tones[$next['tone']] }}">{{ $next['tag'] }}</span>
                            <p class="mt-1 truncate text-[15px] font-semibold text-gray-900">{{ $next['title'] }}</p>
                            <p class="text-sm text-gray-500">{{ $next['meta'] }}</p>
                        </div>
                        <a href="{{ $next['url'] }}"
                           class="inline-flex shrink-0 rounded-lg bg-brand-50 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-100">{{ $next['cta'] }}</a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="mt-5 text-[15px] text-gray-600">No tienes pendientes asignados por atender.</p>
        @endif
    </div>
</x-layouts.vigia>
