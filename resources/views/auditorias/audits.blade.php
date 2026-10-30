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
            'Programada' => 'bg-gray-100 text-gray-700',
            'En captura' => 'bg-amber-100 text-amber-800',
            'Finalizada' => 'bg-green-100 text-green-800',
        ];
    @endphp

    <div class="bg-white rounded-xl shadow p-6 space-y-6">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">Auditorías</h1>
            <p class="mt-1 text-sm text-gray-600">Captura y califica el checklist durante la llamada de Microsoft Teams.</p>
        </div>

        {{-- Pestañas: son enlaces normales; cada una recarga la página con su ?tab= --}}
        <div class="flex gap-6 border-b">
            @foreach(['ejecucion' => 'En ejecución', 'finalizadas' => 'Finalizadas'] as $key => $label)
                <a href="{{ route('auditorias.audits.index', ['tab' => $key] + $filtros) }}"
                   class="-mb-px border-b-2 pb-2 text-sm font-medium {{ $tab === $key ? 'border-[#1A428A] text-[#1A428A]' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
            @endforeach
        </div>

        {{-- Filtros: formulario GET; al enviarlo, los valores viajan en la dirección igual que ?tab= --}}
        <form method="GET" action="{{ route('auditorias.audits.index') }}" class="flex flex-wrap items-end gap-3">
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

            @if($tab === 'ejecucion')
                <div>
                    <label class="block text-xs text-gray-500 mb-1">Estado</label>
                    <select name="estado" class="rounded-md border-gray-300 text-sm">
                        <option value="">Todos</option>
                        @foreach(['Programada', 'En captura'] as $estado)
                            <option value="{{ $estado }}" @selected(($filtros['estado'] ?? '') === $estado)>{{ $estado }}</option>
                        @endforeach
                    </select>
                </div>
            @endif

            <div class="flex-1 min-w-[200px]">
                <label class="block text-xs text-gray-500 mb-1">Buscar</label>
                <input type="search" name="q" value="{{ $filtros['q'] ?? '' }}" placeholder="Procedimiento o persona auditada" class="w-full rounded-md border-gray-300 text-sm">
            </div>

            <button type="submit" class="rounded-md bg-[#1A428A] px-4 py-2 text-sm font-medium text-white hover:bg-[#15366f]">Filtrar</button>

            @if($filtros)
                <a href="{{ route('auditorias.audits.index', ['tab' => $tab]) }}" class="py-2 text-sm text-gray-500 hover:underline">Limpiar</a>
            @endif
        </form>

        <div class="overflow-x-auto border rounded-lg">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs text-gray-500">
                    <tr>
                        <th class="px-4 py-3">Procedimiento</th>
                        <th class="px-4 py-3">Persona auditada</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Calificación</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($filas as $audit)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-800">{{ $audit['procedimiento'] }}</div>
                                <div class="text-xs text-gray-500">{{ $audit['empresa'] }} · {{ $audit['fecha'] }}</div>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $audit['auditado'] }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-medium {{ $colores[$audit['estado']] }}">{{ $audit['estado'] }}</span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $audit['calificacion'] !== null ? $audit['calificacion'].' / 100' : '—' }}</td>
                            <td class="px-4 py-3 text-right">
                                {{-- Checklist y Resultado todavía no existen; al crearlos se cambia el "#" por su ruta --}}
                                <a href="#" class="rounded-md border border-[#1A428A] px-3 py-1.5 text-xs font-medium text-[#1A428A] hover:bg-blue-50">{{ $tab === 'finalizadas' ? 'Ver resultado' : 'Consultar' }}</a>
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
