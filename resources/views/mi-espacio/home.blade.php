<x-layouts.vigia title="Inicio">
    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Estos son tus pendientes.',
    ])

    @php
        $cards = [
            ['label' => 'Mis auditorías activas', 'valor' => $activeAuditsCount, 'texto' => 'Checklists y correcciones de tus auditorías.',   'boton' => 'Ver mis auditorías',  'url' => route('mi-espacio.audits')],
            ['label' => 'Mis actividades',        'valor' => $activitiesCount,   'texto' => 'Acciones correctivas que requieren seguimiento.', 'boton' => 'Ver mis actividades', 'url' => route('mi-espacio.activities')],
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

        @forelse($nextItems as $next)
            {{-- Se llena cuando existan las auditorías y actividades (AUD-1). --}}
        @empty
            <p class="mt-5 text-[15px] text-gray-600">No tienes pendientes asignados por atender.</p>
        @endforelse
    </div>
</x-layouts.vigia>
