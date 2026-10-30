<x-layouts.vigia title="Inicio · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Inicio</span>
    </x-slot>

    @php
        // Datos de ejemplo para la demo. Cuando existan las tablas, esto lo manda el controlador.
        $cards = [
            ['label' => 'Auditorías en ejecución', 'valor' => 2, 'boton' => 'Ver auditorías',       'url' => route('auditorias.audits.index', ['tab' => 'ejecucion'])],
            ['label' => 'Hallazgos abiertos',      'valor' => 5, 'boton' => 'Ver hallazgos',        'url' => route('auditorias.findings.index')],
            ['label' => 'Auditorías finalizadas',  'valor' => 2, 'boton' => 'Consultar auditorías', 'url' => route('auditorias.audits.index', ['tab' => 'finalizadas'])],
        ];

        $pendientes = [
            ['procedimiento' => 'Control de maniobras y seguridad', 'empresa' => 'Asesorías Daval', 'estado' => 'En captura'],
            ['procedimiento' => 'Recepción de materia prima',       'empresa' => 'Vigía',           'estado' => 'Programada'],
        ];
    @endphp

    <div class="bg-white rounded-xl shadow p-6 space-y-8">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">Hola, {{ auth()->user()->name }}</h1>
            <p class="mt-1 text-sm text-gray-600">Continúa las auditorías que tienes asignadas.</p>
        </div>

        {{-- Tarjetas resumen --}}
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($cards as $card)
                <div class="border rounded-lg shadow-sm p-4 flex flex-col">
                    <div class="text-sm font-semibold text-[#1A428A]">{{ $card['label'] }}</div>
                    <div class="mt-2 text-3xl font-bold text-gray-800">{{ $card['valor'] }}</div>
                    <a href="{{ $card['url'] }}" class="mt-4 text-sm font-medium text-[#1A428A] hover:underline">{{ $card['boton'] }} →</a>
                </div>
            @endforeach
        </div>

        {{-- Continuar auditoría --}}
        <div>
            <h2 class="text-lg font-semibold text-gray-800 mb-3">Continuar auditoría</h2>

            <div class="border rounded-lg divide-y">
                @forelse($pendientes as $audit)
                    <div class="flex items-center justify-between gap-4 p-4">
                        <div>
                            <div class="font-medium text-gray-800">{{ $audit['procedimiento'] }}</div>
                            <div class="text-xs text-gray-500">{{ $audit['empresa'] }}</div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $audit['estado'] === 'En captura' ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-700' }}">{{ $audit['estado'] }}</span>
                            {{-- Todavía no existe la pantalla del checklist; al crearla se cambia el "#" por su ruta --}}
                            <a href="#" class="rounded-md bg-[#1A428A] px-3 py-1.5 text-sm font-medium text-white hover:bg-[#15366f]">Continuar checklist</a>
                        </div>
                    </div>
                @empty
                    <p class="p-4 text-sm text-gray-500">No tienes auditorías pendientes.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-layouts.vigia>
