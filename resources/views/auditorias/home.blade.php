<x-layouts.vigia title="Inicio · Auditorías">
    @php
        // Datos de ejemplo para la demo. Cuando existan las tablas, esto lo manda el controlador.
        $cards = [
            ['label' => 'Auditorías en ejecución', 'valor' => 2, 'texto' => 'Captura del checklist durante la llamada de Teams.',  'boton' => 'Ver auditorías',       'url' => route('auditorias.audits.index', ['tab' => 'ejecucion'])],
            ['label' => 'Hallazgos abiertos',      'valor' => 5, 'texto' => 'Seguimiento de causas raíz y acciones correctivas.', 'boton' => 'Ver hallazgos',        'url' => route('auditorias.findings.index')],
            ['label' => 'Auditorías finalizadas',  'valor' => 2, 'texto' => 'Resultados publicados de tus auditorías asignadas.', 'boton' => 'Consultar auditorías', 'url' => route('auditorias.audits.index', ['tab' => 'finalizadas'])],
        ];

        $pendientes = [
            ['procedimiento' => 'Control de maniobras y seguridad', 'estado' => 'En captura'],
            ['procedimiento' => 'Recepción de materia prima',       'estado' => 'Programada'],
        ];
    @endphp

    {{-- Mismo título de página que usa la vista de auditado --}}
    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Continúa las auditorías que tienes asignadas.',
    ])

    {{-- Tarjetas resumen --}}
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

    {{-- Continuar auditoría --}}
    <div class="mt-8 rounded-2xl border border-gray-200 bg-white p-7">
        <h2 class="text-xl font-semibold text-gray-900">Continuar auditoría</h2>

        <div class="mt-3 divide-y divide-gray-200">
            @forelse($pendientes as $audit)
                <div class="flex flex-wrap items-center justify-between gap-4 py-5">
                    <div>
                        <div class="text-lg font-semibold text-gray-900">{{ $audit['procedimiento'] }}</div>
                        <div class="mt-2 text-[15px] text-gray-600">{{ $audit['estado'] }}</div>
                    </div>
                    {{-- Todavía no existe la pantalla del checklist; al crearla se cambia el "#" por su ruta --}}
                    <a href="#" class="rounded-lg bg-brand px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-dark">Continuar checklist</a>
                </div>
            @empty
                <p class="mt-5 text-[15px] text-gray-600">No tienes auditorías pendientes.</p>
            @endforelse
        </div>
    </div>
</x-layouts.vigia>
