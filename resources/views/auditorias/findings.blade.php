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
            ['codigo' => 'HAL-001', 'titulo' => 'Bitácora de maniobras sin firma del supervisor', 'empresa' => 'Asesorías Daval', 'responsable' => 'Laura Méndez',  'severidad' => 'Mayor',   'seguimiento' => 'Causa raíz por revisar', 'compromiso' => '25 sep 2026'],
            ['codigo' => 'HAL-002', 'titulo' => 'Equipo de protección incompleto en patio',       'empresa' => 'Asesorías Daval', 'responsable' => 'Laura Méndez',  'severidad' => 'Crítico', 'seguimiento' => 'Por enviar',             'compromiso' => '18 sep 2026'],
            ['codigo' => 'HAL-003', 'titulo' => 'Registro de temperatura sin capturar',           'empresa' => 'Vigía',           'responsable' => 'Jorge Salinas', 'severidad' => 'Menor',   'seguimiento' => 'Evidencia por revisar',  'compromiso' => '30 sep 2026'],
            ['codigo' => 'HAL-004', 'titulo' => 'Contenedores de residuos sin etiqueta',          'empresa' => 'Asesorías Daval', 'responsable' => 'Ana Torres',    'severidad' => 'Mayor',   'seguimiento' => 'Rechazada',              'compromiso' => '10 sep 2026'],
            ['codigo' => 'HAL-005', 'titulo' => 'Capacitación de montacarguistas vencida',        'empresa' => 'Vigía',           'responsable' => 'Jorge Salinas', 'severidad' => 'Crítico', 'seguimiento' => 'Sin cargar',             'compromiso' => '15 oct 2026'],
            ['codigo' => 'HAL-006', 'titulo' => 'Versión obsoleta del procedimiento en planta',   'empresa' => 'Vigía',           'responsable' => 'Luis Herrera',  'severidad' => 'Menor',   'seguimiento' => 'Cerrado',                'compromiso' => '05 sep 2026'],
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
            ));

        $severidades = [
            'Crítico' => 'bg-red-100 text-red-800',
            'Mayor'   => 'bg-amber-100 text-amber-800',
            'Menor'   => 'bg-purple-100 text-purple-800',
        ];

        $seguimientos = [
            'Por enviar'             => 'bg-gray-100 text-gray-700',
            'Causa raíz por revisar' => 'bg-blue-100 text-blue-800',
            'Rechazada'              => 'bg-red-100 text-red-800',
            'Sin cargar'             => 'bg-amber-100 text-amber-800',
            'Evidencia por revisar'  => 'bg-blue-100 text-blue-800',
            'Cerrado'                => 'bg-green-100 text-green-800',
        ];

        // El botón de cada fila cambia según lo que le toca hacer al auditor; si no le toca nada, "Abrir".
        $acciones = [
            'Causa raíz por revisar' => 'Revisar causa raíz',
            'Evidencia por revisar'  => 'Revisar evidencia',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow p-6 space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-[#1A428A]">Hallazgos</h1>
                <p class="mt-1 text-sm text-gray-600">Causas raíz, acciones y evidencias de cada hallazgo en un solo seguimiento.</p>
            </div>
            {{-- La ventana para registrar se construye más adelante; por ahora el botón no abre nada --}}
            <button type="button" class="rounded-md bg-[#1A428A] px-4 py-2 text-sm font-medium text-white hover:bg-[#15366f]">+ Registrar hallazgo</button>
        </div>

        {{-- Pestañas --}}
        <div class="flex flex-wrap gap-6 border-b">
            @foreach($tabs as $key => $label)
                <a href="{{ route('auditorias.findings.index', ['tab' => $key] + $filtros) }}"
                   class="-mb-px border-b-2 pb-2 text-sm font-medium {{ $tab === $key ? 'border-[#1A428A] text-[#1A428A]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Filtros --}}
        <form method="GET" action="{{ route('auditorias.findings.index') }}" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div>
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="empresa" class="rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($empresas as $empresa)
                        <option value="{{ $empresa }}" @selected(($filtros['empresa'] ?? '') === $empresa)>{{ $empresa }}</option>
                    @endforeach
                </select>
            </div>

            @if($tab === 'todos')
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Seguimiento</label>
                    <select name="seguimiento" class="rounded-md border-gray-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(array_keys($seguimientos) as $seguimiento)
                            <option value="{{ $seguimiento }}" @selected(($filtros['seguimiento'] ?? '') === $seguimiento)>{{ $seguimiento }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Código o título del hallazgo" class="w-full rounded-md border-gray-300 text-sm">
            </div>

            <button type="submit" class="rounded-md bg-[#1A428A] px-4 py-2 text-sm font-medium text-white hover:bg-[#15366f]">Filtrar</button>

            @if($filtros)
                <a href="{{ route('auditorias.findings.index', ['tab' => $tab]) }}" class="py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto border rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Hallazgo</th>
                        <th class="px-4 py-3">Responsable</th>
                        <th class="px-4 py-3">Severidad</th>
                        <th class="px-4 py-3">Seguimiento</th>
                        <th class="px-4 py-3">Compromiso</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($filas as $h)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $h['titulo'] }}</div>
                                <div class="text-xs text-gray-500">{{ $h['empresa'] }} · {{ $h['codigo'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $h['responsable'] }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $severidades[$h['severidad']] }}">{{ $h['severidad'] }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <span class="whitespace-nowrap rounded-full px-2.5 py-0.5 text-xs font-medium {{ $seguimientos[$h['seguimiento']] }}">{{ $h['seguimiento'] }}</span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700">{{ $h['compromiso'] }}</td>
                            <td class="px-4 py-3 text-right">
                                {{-- El detalle y las revisiones se construyen más adelante; al crearlos se cambia el "#" --}}
                                <a href="#" class="whitespace-nowrap rounded-md border border-[#1A428A] px-3 py-1.5 text-xs font-medium text-[#1A428A] hover:bg-blue-50">{{ $acciones[$h['seguimiento']] ?? 'Abrir' }}</a>
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
</x-layouts.vigia>
