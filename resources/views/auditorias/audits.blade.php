<x-layouts.vigia title="Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Auditorías</span>
    </x-slot>

    @php
        // Pestaña activa: viene en la dirección (?tab=finalizadas). Si no viene, se abre "En ejecución".
        $tab = request('tab') === 'finalizadas' ? 'finalizadas' : 'ejecucion';

        // Datos de ejemplo para la demo. Cuando existan las tablas, esto lo manda el controlador.
        $auditorias = [
            ['procedimiento' => 'Control de maniobras y seguridad', 'empresa' => 'Asesorías Daval', 'fecha' => '11 sep 2026', 'auditado' => 'Laura Méndez',  'estado' => 'En captura', 'calificacion' => null],
            ['procedimiento' => 'Recepción de materia prima',       'empresa' => 'Vigía',           'fecha' => '14 sep 2026', 'auditado' => 'Jorge Salinas', 'estado' => 'Programada', 'calificacion' => null],
            ['procedimiento' => 'Manejo de residuos peligrosos',    'empresa' => 'Asesorías Daval', 'fecha' => '28 ago 2026', 'auditado' => 'Ana Torres',    'estado' => 'Finalizada', 'calificacion' => 85],
            ['procedimiento' => 'Control de documentos',            'empresa' => 'Vigía',           'fecha' => '20 ago 2026', 'auditado' => 'Luis Herrera',  'estado' => 'Finalizada', 'calificacion' => 92],
        ];

        // Filtros que vienen en la dirección (?empresa=...&estado=...&q=...). array_filter quita los vacíos.
        $filtros  = array_filter(request()->only(['empresa', 'estado', 'q']));
        $empresas = collect($auditorias)->pluck('empresa')->unique()->sort();

        // Cada pestaña muestra solo sus auditorías, y luego se aplica cada filtro que venga lleno.
        $filas = collect($auditorias)
            ->filter(fn ($a) => $tab === 'finalizadas'
                ? $a['estado'] === 'Finalizada'
                : $a['estado'] !== 'Finalizada')
            ->when($filtros['empresa'] ?? null, fn ($c, $empresa) => $c->where('empresa', $empresa))
            ->when($tab === 'ejecucion' ? ($filtros['estado'] ?? null) : null, fn ($c, $estado) => $c->where('estado', $estado))
            ->when($filtros['q'] ?? null, fn ($c, $q) => $c->filter(
                fn ($a) => str_contains(mb_strtolower($a['procedimiento'].' '.$a['auditado']), mb_strtolower($q))
            ));

        $colores = [
            'Programada' => 'bg-brand-50 text-brand',
            'En captura' => 'bg-amber-50 text-amber-700',
            'Finalizada' => 'bg-green-50 text-green-700',
        ];
    @endphp

    @include('mi-espacio.partials.header', [
        'title'    => 'Auditorías',
        'subtitle' => 'Captura y califica el checklist durante la llamada de Microsoft Teams.',
    ])

    {{-- Pestañas: son enlaces normales; cada una recarga la página con su ?tab= --}}
    <div class="mb-7 flex gap-6 border-b border-gray-200">
        @foreach(['ejecucion' => 'En ejecución', 'finalizadas' => 'Finalizadas'] as $key => $label)
            <a href="{{ route('auditorias.audits.index', ['tab' => $key] + $filtros) }}"
                class="-mb-px border-b-2 pb-3 text-base font-semibold {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Filtros: las listas se envían solas al cambiar (onchange) y la búsqueda con Enter --}}
    <form method="GET" action="{{ route('auditorias.audits.index') }}" class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <select name="empresa" onchange="this.form.submit()"
                class="h-14 shrink-0 rounded-xl border-gray-200 pr-10 text-[15px] focus:border-brand focus:ring-brand sm:w-64">
            <option value="">Todas</option>
            @foreach($empresas as $empresa)
                <option value="{{ $empresa }}" @selected(($filtros['empresa'] ?? '') === $empresa)>{{ $empresa }}</option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar por procedimiento o persona..."
                class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">

        @if($tab === 'ejecucion')
            <select name="estado" onchange="this.form.submit()"
                    class="h-14 shrink-0 rounded-xl border-gray-200 pr-10 text-[15px] focus:border-brand focus:ring-brand sm:w-64">
                <option value="">Todos</option>
                @foreach(['Programada', 'En captura'] as $estado)
                    <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>
                @endforeach
            </select>
        @endif

        @if($filtros)
            <a href="{{ route('auditorias.audits.index', ['tab' => $tab]) }}" class="shrink-0 text-sm font-semibold text-brand hover:underline">Limpiar</a>
        @endif
    </form>

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
                    @forelse($filas as $audit)
                        <tr>
                            <td class="px-4 py-5">
                                <div class="font-semibold text-gray-900">{{ $audit['procedimiento'] }}</div>
                                <div class="mt-1 text-sm text-gray-500">{{ $audit['empresa'] }} · {{ $audit['fecha'] }}</div>
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $audit['auditado'] }}</td>
                            <td class="px-4 py-5">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $colores[$audit['estado']] }}">{{ $audit['estado'] }}</span>
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $audit['calificacion'] !== null ? $audit['calificacion'].' / 100' : '—' }}</td>
                            <td class="px-4 py-5 text-right">
                                {{-- Checklist y Resultado todavía no existen; al crearlos se cambia el "#" por su ruta --}}
                                <a href="#" class="inline-flex rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">{{ $tab === 'finalizadas' ? 'Ver resultado' : 'Consultar' }}</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-6 text-center text-gray-500">{{ $filtros ? 'Ninguna auditoría coincide con los filtros.' : 'No hay auditorías en esta pestaña.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.vigia>
