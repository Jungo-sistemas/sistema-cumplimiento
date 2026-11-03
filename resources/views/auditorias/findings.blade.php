@use('Illuminate\Support\Carbon')
<x-layouts.vigia title="Hallazgos · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Hallazgos</span>
    </x-slot>

    @php
        $tabs = [
            'todos'      => 'Todos',
            'causas'     => 'Causas raíz por revisar',
            'evidencias' => 'Evidencias por revisar',
        ];
        // Pestaña activa (?tab=causas o ?tab=evidencias). Si no viene o no se reconoce, se abre "Todos".
        $tab = in_array(request('tab'), ['causas', 'evidencias'], true) ? request('tab') : 'todos';

        // Datos de ejemplo para la demo. Cuando existan las tablas, esto lo manda el controlador.
        $hallazgos = [
            ['codigo' => 'HAL-001', 'titulo' => 'Bitácora de maniobras sin firma del supervisor', 'empresa' => 'Asesorías Daval', 'responsable' => 'Laura Méndez',  'severidad' => 'Mayor',   'seguimiento' => 'Causa raíz por revisar', 'compromiso' => '2026-11-25'],
            ['codigo' => 'HAL-002', 'titulo' => 'Equipo de protección incompleto en patio',       'empresa' => 'Asesorías Daval', 'responsable' => 'Laura Méndez',  'severidad' => 'Crítico', 'seguimiento' => 'Por enviar',             'compromiso' => '2026-12-20'],
            ['codigo' => 'HAL-003', 'titulo' => 'Registro de temperatura sin capturar',           'empresa' => 'Vigía',           'responsable' => 'Jorge Salinas', 'severidad' => 'Menor',   'seguimiento' => 'Evidencia por revisar',  'compromiso' => '2026-09-30'],
            ['codigo' => 'HAL-004', 'titulo' => 'Contenedores de residuos sin etiqueta',          'empresa' => 'Asesorías Daval', 'responsable' => 'Ana Torres',    'severidad' => 'Mayor',   'seguimiento' => 'Rechazada',              'compromiso' => '2026-09-10'],
            ['codigo' => 'HAL-005', 'titulo' => 'Capacitación de montacarguistas vencida',        'empresa' => 'Vigía',           'responsable' => 'Jorge Salinas', 'severidad' => 'Crítico', 'seguimiento' => 'Sin cargar',             'compromiso' => '2026-10-15'],
            ['codigo' => 'HAL-006', 'titulo' => 'Versión obsoleta del procedimiento en planta',   'empresa' => 'Vigía',           'responsable' => 'Luis Herrera',  'severidad' => 'Menor',   'seguimiento' => 'Cerrado',                'compromiso' => '2026-09-05'],
        ];

        $filtros  = array_filter(request()->only(['empresa', 'seguimiento', 'q']));
        $empresas = collect($hallazgos)->pluck('empresa')->unique()->sort();

        // Qué seguimiento muestra cada pestaña ("Todos" no filtra).
        $porPestana = ['causas' => 'Causa raíz por revisar', 'evidencias' => 'Evidencia por revisar'];

        $filas = collect($hallazgos)
            ->when($porPestana[$tab] ?? null, fn ($c, $seguimiento) => $c->where('seguimiento', $seguimiento))
            ->when($filtros['empresa'] ?? null, fn ($c, $empresa) => $c->where('empresa', $empresa))
            ->when($tab === 'todos' ? ($filtros['seguimiento'] ?? null) : null, fn ($c, $seguimiento) => $c->where('seguimiento', $seguimiento))
            ->when($filtros['q'] ?? null, fn ($c, $q) => $c->filter(
                fn ($h) => str_contains(mb_strtolower($h['codigo'].' '.$h['titulo']), mb_strtolower($q))
            ))
            // Vencido: sigue abierto y su fecha compromiso ya pasó (antes de hoy).
            ->map(fn ($h) => $h + ['vencido' => $h['seguimiento'] !== 'Cerrado' && Carbon::parse($h['compromiso'])->lt(today())]);

        $severidades = [
            'Crítico' => 'bg-red-50 text-red-700',
            'Mayor'   => 'bg-amber-50 text-amber-700',
            'Menor'   => 'bg-brand-50 text-brand',
        ];

        $seguimientos = [
            'Por enviar'             => 'bg-gray-100 text-gray-700',
            'Causa raíz por revisar' => 'bg-brand-50 text-brand',
            'Rechazada'              => 'bg-red-50 text-red-700',
            'Sin cargar'             => 'bg-amber-50 text-amber-700',
            'Evidencia por revisar'  => 'bg-brand-50 text-brand',
            'Cerrado'                => 'bg-green-50 text-green-700',
        ];

        // El botón de cada fila cambia según lo que le toca hacer al auditor; si no le toca nada, "Abrir".
        $acciones = [
            'Causa raíz por revisar' => 'Revisar causa raíz',
            'Evidencia por revisar'  => 'Revisar evidencia',
        ];
    @endphp

    <div class="flex flex-wrap items-start justify-between gap-4">
        @include('mi-espacio.partials.header', [
            'title'    => 'Hallazgos',
            'subtitle' => 'Causas raíz, acciones y evidencias de cada hallazgo en un solo seguimiento.',
        ])
        <button type="button" @click="$dispatch('registrar-hallazgo')" class="rounded-lg bg-brand px-5 py-3 text-[15px] font-semibold text-white hover:bg-brand-dark">+ Registrar hallazgo</button>
    </div>

    {{-- Pestañas --}}
    <div class="mb-7 flex flex-wrap gap-6 border-b border-gray-200">
        @foreach($tabs as $key => $label)
            <a href="{{ route('auditorias.findings.index', ['tab' => $key] + $filtros) }}"
                class="-mb-px border-b-2 pb-3 text-base font-semibold {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </div>

    {{-- Filtros: las listas se envían solas al cambiar (onchange) y la búsqueda con Enter --}}
    <form method="GET" action="{{ route('auditorias.findings.index') }}" class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">

        <select name="empresa" onchange="this.form.submit()"
                class="h-14 shrink-0 rounded-xl border-gray-200 pr-10 text-[15px] focus:border-brand focus:ring-brand sm:w-64">
            <option value="">Todas</option>
            @foreach($empresas as $empresa)
                <option value="{{ $empresa }}" @selected(($filtros['empresa'] ?? '') === $empresa)>{{ $empresa }}</option>
            @endforeach
        </select>

        <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Buscar por código o título..."
               class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">

        @if($tab === 'todos')
            <select name="seguimiento" onchange="this.form.submit()"
                    class="h-14 shrink-0 rounded-xl border-gray-200 pr-10 text-[15px] focus:border-brand focus:ring-brand sm:w-64">
                <option value="">Todos</option>
                @foreach(array_keys($seguimientos) as $seguimiento)
                    <option value="{{ $seguimiento }}" @selected(($filtros['seguimiento'] ?? '') === $seguimiento)>{{ $seguimiento }}</option>
                @endforeach
            </select>
        @endif

        @if($filtros)
            <a href="{{ route('auditorias.findings.index', ['tab' => $tab]) }}" class="shrink-0 text-sm font-semibold text-brand hover:underline">Limpiar</a>
        @endif
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-8">
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-5">Hallazgo</th>
                        <th class="px-4 py-5">Responsable</th>
                        <th class="px-4 py-5">Severidad</th>
                        <th class="px-4 py-5">Seguimiento</th>
                        <th class="px-4 py-5">Compromiso</th>
                        <th class="px-4 py-5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($filas as $h)
                        <tr>
                            <td class="px-4 py-5">
                                <div class="font-semibold text-gray-900">{{ $h['titulo'] }}</div>
                                <div class="mt-1 text-sm text-gray-500">{{ $h['empresa'] }} · {{ $h['codigo'] }}</div>
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $h['responsable'] }}</td>
                            <td class="px-4 py-5">
                                <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $severidades[$h['severidad']] }}">{{ $h['severidad'] }}</span>
                            </td>
                            <td class="px-4 py-5">
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $seguimientos[$h['seguimiento']] }}">{{ $h['seguimiento'] }}</span>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                <div class="{{ $h['vencido'] ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ Carbon::parse($h['compromiso'])->locale('es')->isoFormat('DD MMM YYYY') }}</div>
                                @if($h['vencido'])
                                    <span class="mt-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Vencido</span>
                                @endif
                            </td>
                            <td class="px-4 py-5 text-right">
                                <button type="button" @click="$dispatch('abrir-hallazgo', @js($h))" class="whitespace-nowrap inline-flex rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">{{ $acciones[$h['seguimiento']] ?? 'Abrir' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ $filtros ? 'Ningún hallazgo coincide con los filtros.' : 'No hay hallazgos en esta pestaña.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('auditorias.partials.register-finding-modal')
    @include('auditorias.partials.finding-detail-modal')
</x-layouts.vigia>
