<x-layouts.vigia :title="$regulation->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-600 hover:underline">Procesos</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">
            <x-truncate max="max-w-[400px]">{{ $regulation->name }}</x-truncate>
        </span>
    </x-slot>

    @php
        $color    = $regulation->statusColor();
        $daysLeft = $regulation->daysUntilExpiry();
    @endphp

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6 flex-wrap">
            <div class="space-y-1 min-w-0 flex-1">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-bold text-[#1A428A]">{{ $regulation->name }}</h1>

                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded border
                        {{ $color === 'green'  ? 'bg-green-50 text-green-700 border-green-200' : '' }}
                        {{ $color === 'yellow' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : '' }}
                        {{ $color === 'red'    ? 'bg-red-50 text-red-700 border-red-200' : '' }}">
                        <span class="h-2 w-2 rounded-full
                            {{ $color === 'green'  ? 'bg-green-500' : '' }}
                            {{ $color === 'yellow' ? 'bg-yellow-400' : '' }}
                            {{ $color === 'red'    ? 'bg-red-500' : '' }}">
                        </span>
                        {{ $regulation->statusLabel() }}
                        @if($daysLeft !== null)
                            @if($color === 'red')
                                · {{ abs($daysLeft) }} día(s) vencido
                            @elseif($color === 'yellow')
                                · vence en {{ $daysLeft }} día(s)
                            @endif
                        @endif
                    </span>
                </div>

                <div class="text-sm text-gray-500 flex flex-wrap gap-x-4 gap-y-1">
                    @if($regulation->code)
                        <span>Código: <span class="font-mono font-semibold text-gray-700">{{ $regulation->code }}</span></span>
                    @endif
                    <span>Proceso: <span class="font-semibold text-gray-700">{{ $regulation->processType->name ?? '—' }}</span></span>
                    @if($regulation->document_type)
                        <span>Tipo: <span class="font-semibold text-gray-700">{{ $regulation->document_type }}</span></span>
                    @endif
                    @if(auth()->user()->hasGroupScope() && $regulation->company)
                        <span>Empresa: <span class="font-semibold text-gray-700">{{ $regulation->company->name }}</span></span>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                    {{-- Dropdown Editar --}}
                    <div class="relative" x-data="{ open: false }">
                        <button type="button"
                                @click="open = !open"
                                @click.outside="open = false"
                                class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50 text-sm flex items-center gap-1.5">
                            Editar
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-150" :class="open ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                        <div x-show="open"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="opacity-0 -translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             class="absolute right-0 mt-1 w-48 rounded-lg border border-gray-200 bg-white shadow-lg z-20 overflow-hidden"
                             style="display:none;">
                            <a href="{{ route('processes.editBasic', $regulation) }}"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-[#1A428A]">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Info básica
                            </a>
                            <a href="{{ route('processes.edit', $regulation) }}"
                               class="flex items-center gap-2 px-4 py-2.5 text-sm text-gray-700 hover:bg-blue-50 hover:text-[#1A428A] border-t border-gray-100">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                </svg>
                                Prompt
                            </a>
                        </div>
                    </div>

                    @if($regulation->approval_status === 'approved')
                        <button type="button"
                                onclick="openShareModal('send')"
                                class="px-4 py-2 rounded-md border border-green-600 bg-white text-green-700 font-semibold hover:bg-green-50 text-sm flex items-center gap-1.5">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                            </svg>
                            Compartir
                        </button>
                    @endif
                @endif
                <a href="{{ route('processes.index', ['company_id' => $regulation->company_id]) }}"
                   class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50 text-sm">
                    Volver
                </a>
            </div>
        </div>

        {{-- Alerts --}}
        <div class="mt-4 space-y-3">
            @if(session('success'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                    {{ session('error') }}
                </div>
            @endif
        </div>


        {{-- Panel de cambios recientes --}}
        @php
            $detailsCurrent  = $regulation->details          ?? [];
            $detailsPrevious = $regulation->previous_details ?? [];

            $sections = [
                'Identificación' => [
                    'quien_elabora'  => 'Elaborado por',
                    'quien_aprueba'  => 'Aprobado por',
                    'fecha_vigencia' => 'Fecha de vigencia',
                ],
                'Objetivo y Alcance' => [
                    'resultado_esperado' => 'Resultado esperado',
                    'problema_resuelve'  => 'Problema que resuelve',
                    'areas_aplica'       => 'Áreas donde aplica',
                    'fuera_alcance'      => 'Fuera del alcance',
                ],
                'Indicadores' => [
                    'indicador_proceso'   => 'Indicador de proceso',
                    'indicador_resultado' => 'Indicador de resultado',
                    'meta_valor'          => 'Meta / Valor objetivo',
                    'frecuencia_medicion' => 'Frecuencia de medición',
                ],
                'Actividades' => [
                    'que_detona'           => 'Detonante del proceso',
                    'lista_actividades'    => 'Lista de actividades',
                    'areas_ejecutan'       => 'Áreas que ejecutan',
                    'decisiones_control'   => 'Decisiones y puntos de control',
                    'documentos_usados'    => 'Documentos y herramientas',
                    'resultado_entregable' => 'Resultado / Entregable',
                ],
                'Mapa de Proceso' => [
                    'areas_roles_mapa'            => 'Áreas y roles del mapa',
                    'procedimientos_relacionados'  => 'Procedimientos relacionados',
                    'proveedores_clientes'         => 'Proveedores y clientes',
                ],
                'Contexto' => [
                    'terminos_abreviaturas'    => 'Términos y abreviaturas',
                    'riesgos_errores'          => 'Riesgos y errores frecuentes',
                    'requerimientos_normativos' => 'Requerimientos normativos',
                ],
            ];

            $changedBySections = [];
            $totalChangedCount = 0;

            if (!empty($detailsPrevious)) {
                foreach ($sections as $sectionName => $fields) {
                    foreach ($fields as $key => $label) {
                        $oldVal = trim($detailsPrevious[$key] ?? '');
                        $newVal = trim($detailsCurrent[$key]  ?? '');
                        if (array_key_exists($key, $detailsPrevious) && $oldVal !== $newVal) {
                            $changedBySections[$sectionName][] = [
                                'label' => $label,
                                'old'   => $oldVal,
                                'new'   => $newVal,
                            ];
                            $totalChangedCount++;
                        }
                    }
                }
            }
        @endphp

        @if($totalChangedCount > 0)
        <div class="mt-6" x-data="{ open: false }">
            <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-3.5 rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 transition text-left">
                <div class="flex items-center gap-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="text-sm font-semibold text-amber-800">
                        Última edición modificó {{ $totalChangedCount }} {{ $totalChangedCount === 1 ? 'campo' : 'campos' }}
                        en {{ count($changedBySections) }} {{ count($changedBySections) === 1 ? 'sección' : 'secciones' }}
                    </span>
                    <span class="text-xs text-amber-600 font-normal">— haz clic para ver qué cambió</span>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 shrink-0 transition-transform duration-200"
                     :class="open ? 'rotate-180' : ''"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 translate-y-0"
                 x-transition:leave-end="opacity-0 -translate-y-1"
                 class="mt-1 rounded-xl border border-amber-200 overflow-hidden">

                @foreach($changedBySections as $sectionName => $fields)
                    {{-- Encabezado de sección --}}
                    <div class="px-5 py-2 bg-amber-50 border-b border-amber-100 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-amber-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                        </svg>
                        <span class="text-xs font-bold text-amber-700 uppercase tracking-wide">{{ $sectionName }}</span>
                        <span class="text-xs text-amber-500">· {{ count($fields) }} {{ count($fields) === 1 ? 'cambio' : 'cambios' }}</span>
                    </div>

                    {{-- Campos cambiados dentro de la sección --}}
                    @foreach($fields as $info)
                    <div class="px-5 py-4 bg-white border-b border-amber-100 last:border-b-0">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">{{ $info['label'] }}</p>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <p class="text-xs text-red-400 font-medium mb-1">Antes</p>
                                <p class="text-sm text-gray-600 bg-red-50 border border-red-100 rounded px-2.5 py-2 whitespace-pre-line leading-snug min-h-[2rem]">{{ $info['old'] ?: '(vacío)' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-green-600 font-medium mb-1">Ahora</p>
                                <p class="text-sm text-gray-800 bg-green-50 border border-green-100 rounded px-2.5 py-2 whitespace-pre-line leading-snug min-h-[2rem] font-medium">{{ $info['new'] ?: '(vacío)' }}</p>
                            </div>
                        </div>
                    </div>
                    @endforeach
                @endforeach

            </div>
        </div>
        @endif

        @if(false) {{-- Vista del documento movida a processes.print --}}
        <div class="mt-8">
            {{-- Encabezado de sección con botón imprimir --}}
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-base font-semibold text-gray-700">Vista del documento</h2>
                <button onclick="window.print()"
                        class="px-3 py-1.5 rounded-md border border-gray-300 text-sm text-gray-600 hover:bg-gray-50 flex items-center gap-1.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
                    </svg>
                    Imprimir
                </button>
            </div>

            {{-- Documento --}}
            <div id="doc-preview" class="border border-gray-300 rounded-xl overflow-hidden text-sm text-gray-900 bg-white print:border-0 print:rounded-none">

                {{-- ── Encabezado tabla superior ── --}}
                <table class="w-full border-collapse text-xs">
                    <tr>
                        <td class="border border-gray-400 p-3 text-center w-1/4 align-middle text-gray-500 italic">
                            <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">LOGO EMPRESA</div>
                            <div class="text-xs text-gray-400">(insertar logotipo)</div>
                        </td>
                        <td class="border border-gray-400 p-3 text-center align-middle">
                            <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">{{ strtoupper($regulation->document_type ?? 'PROCEDIMIENTO') }}</div>
                            <div class="font-semibold text-gray-800 text-sm">{{ $regulation->name }}</div>
                        </td>
                        <td class="border border-gray-400 p-3 text-center w-[120px] align-middle">
                            <div class="text-xs font-bold text-gray-500 uppercase">CÓDIGO</div>
                            <div class="font-mono font-bold text-[#1A428A] text-sm mt-0.5">{{ $regulation->code ?? '—' }}</div>
                        </td>
                        <td class="border border-gray-400 p-3 text-center w-[80px] align-middle">
                            <div class="text-xs font-bold text-gray-500 uppercase">VERSIÓN</div>
                            <div class="font-bold text-gray-800 text-sm mt-0.5">{{ str_pad($vNum, 2, '0', STR_PAD_LEFT) }}</div>
                        </td>
                    </tr>
                    <tr class="bg-gray-100">
                        <td class="border border-gray-400 p-2">
                            <div class="text-xs font-bold text-gray-600 uppercase">ELABORADO POR:</div>
                            <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_elabora'] ?? '—' }}</div>
                        </td>
                        <td class="border border-gray-400 p-2">
                            <div class="text-xs font-bold text-gray-600 uppercase">APROBADO POR:</div>
                            <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_aprueba'] ?? '—' }}</div>
                        </td>
                        <td class="border border-gray-400 p-2">
                            <div class="text-xs font-bold text-gray-600 uppercase">Fecha efectividad:</div>
                            <div class="text-xs text-gray-700 mt-0.5">{{ $fechaFmt }}</div>
                        </td>
                        <td class="border border-gray-400 p-2 text-center">
                            <div class="text-xs font-bold text-gray-600 uppercase">Página:</div>
                            <div class="text-xs text-gray-700 mt-0.5">1 de 2</div>
                        </td>
                    </tr>
                </table>

                <div class="p-6 space-y-6">

                    {{-- OBJETIVO --}}
                    <div>
                        <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">OBJETIVO</h3>
                        <div class="bg-gray-50 border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['resultado_esperado'] ?? '' }}</div>
                    </div>

                    {{-- ALCANCE --}}
                    <div>
                        <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">ALCANCE</h3>
                        @if(!empty($d['problema_resuelve']))
                            <div class="bg-gray-50 border border-gray-200 rounded p-3 text-sm text-gray-700 mb-3 leading-relaxed whitespace-pre-line">{{ $d['problema_resuelve'] }}</div>
                        @endif

                        @if(!empty($d['areas_aplica']))
                            <p class="font-semibold text-gray-800 text-sm mb-1">Este procedimiento aplica a:</p>
                            <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 mb-3 ml-2">
                                @foreach($parseLines($d['areas_aplica']) as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif

                        @if(!empty($d['fuera_alcance']))
                            <p class="font-semibold text-gray-800 text-sm mb-1">Queda fuera del alcance:</p>
                            <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 ml-2">
                                @foreach($parseLines($d['fuera_alcance']) as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- TÓPICOS --}}
                    @if(!empty($d['requerimientos_normativos']))
                    <div>
                        <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">TÓPICOS</h3>
                        <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 ml-2">
                            @foreach($parseLines($d['requerimientos_normativos']) as $line)
                                <li>{{ $line }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    {{-- INDICADORES --}}
                    @if(!empty($d['indicador_proceso']) || !empty($d['indicador_resultado']))
                    <div>
                        <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">INDICADORES</h3>
                        <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 ml-2">
                            @if(!empty($d['indicador_proceso']))
                                <li><span class="font-medium">Proceso:</span> {{ $d['indicador_proceso'] }}</li>
                            @endif
                            @if(!empty($d['indicador_resultado']))
                                <li><span class="font-medium">Resultado:</span> {{ $d['indicador_resultado'] }}</li>
                            @endif
                            @if(!empty($d['meta_valor']))
                                <li><span class="font-medium">Meta:</span> {{ $d['meta_valor'] }}</li>
                            @endif
                            @if(!empty($d['frecuencia_medicion']))
                                <li><span class="font-medium">Frecuencia:</span> {{ $d['frecuencia_medicion'] }}</li>
                            @endif
                        </ul>
                    </div>
                    @endif

                    {{-- DEFINICIONES Y ABREVIATURAS --}}
                    @if(!empty($d['terminos_abreviaturas']))
                    <div>
                        <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">DEFINICIONES Y ABREVIATURAS</h3>
                        @php $terms = $parseTerms($d['terminos_abreviaturas']); @endphp
                        <table class="w-full border-collapse text-sm">
                            <thead>
                                <tr class="bg-[#e8eef8]">
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800 w-1/3">Término / Abreviatura</th>
                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Definición</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($terms as $row)
                                <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }}">
                                    <td class="border border-gray-300 px-3 py-2 font-medium text-gray-800">{{ $row['term'] }}</td>
                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $row['def'] }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @endif

                    {{-- Pie de página 1 --}}
                    <div class="border-t border-gray-300 pt-3 text-center text-xs text-gray-400 italic">
                        Documento controlado — Prohibida su reproducción parcial o total sin autorización | Versión <em>impresa no controlada</em>. Verifique vigencia en el sistema.
                    </div>
                </div>

                {{-- ── Segunda "página": encabezado + descripción + historial + anexos ── --}}
                <div class="border-t-4 border-[#1A428A]">
                    {{-- Repetir encabezado --}}
                    <table class="w-full border-collapse text-xs">
                        <tr>
                            <td class="border border-gray-400 p-3 text-center w-1/4 align-middle text-gray-400 italic">
                                <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">LOGO EMPRESA</div>
                                <div class="text-xs">(insertar logotipo)</div>
                            </td>
                            <td class="border border-gray-400 p-3 text-center align-middle">
                                <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">{{ strtoupper($regulation->document_type ?? 'PROCEDIMIENTO') }}</div>
                                <div class="font-semibold text-gray-800 text-sm">{{ $regulation->name }}</div>
                            </td>
                            <td class="border border-gray-400 p-3 text-center w-[120px] align-middle">
                                <div class="text-xs font-bold text-gray-500 uppercase">CÓDIGO</div>
                                <div class="font-mono font-bold text-[#1A428A] text-sm mt-0.5">{{ $regulation->code ?? '—' }}</div>
                            </td>
                            <td class="border border-gray-400 p-3 text-center w-[80px] align-middle">
                                <div class="text-xs font-bold text-gray-500 uppercase">VERSIÓN</div>
                                <div class="font-bold text-gray-800 text-sm mt-0.5">{{ str_pad($vNum, 2, '0', STR_PAD_LEFT) }}</div>
                            </td>
                        </tr>
                        <tr class="bg-gray-100">
                            <td class="border border-gray-400 p-2">
                                <div class="text-xs font-bold text-gray-600 uppercase">ELABORADO POR:</div>
                                <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_elabora'] ?? '—' }}</div>
                            </td>
                            <td class="border border-gray-400 p-2">
                                <div class="text-xs font-bold text-gray-600 uppercase">APROBADO POR:</div>
                                <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_aprueba'] ?? '—' }}</div>
                            </td>
                            <td class="border border-gray-400 p-2">
                                <div class="text-xs font-bold text-gray-600 uppercase">Fecha efectividad:</div>
                                <div class="text-xs text-gray-700 mt-0.5">{{ $fechaFmt }}</div>
                            </td>
                            <td class="border border-gray-400 p-2 text-center">
                                <div class="text-xs font-bold text-gray-600 uppercase">Página:</div>
                                <div class="text-xs text-gray-700 mt-0.5">2 de 2</div>
                            </td>
                        </tr>
                    </table>

                    <div class="p-6 space-y-6">

                        {{-- DESCRIPCIÓN DEL PROCESO / ACTIVIDADES --}}
                        @if(!empty($d['lista_actividades']) || !empty($d['que_detona']))
                        <div>
                            <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">DESCRIPCIÓN DEL PROCESO / ACTIVIDADES</h3>
                            @if(!empty($d['que_detona']))
                                <p class="font-medium text-gray-700 text-sm mb-1">Detonante:</p>
                                <div class="bg-gray-50 border border-gray-200 rounded p-3 text-sm text-gray-700 mb-3 whitespace-pre-line">{{ $d['que_detona'] }}</div>
                            @endif
                            @if(!empty($d['lista_actividades']))
                                <div class="bg-gray-50 border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['lista_actividades'] }}</div>
                            @endif
                            @if(!empty($d['decisiones_control']))
                                <p class="font-medium text-gray-700 text-sm mt-3 mb-1">Decisiones y puntos de control:</p>
                                <div class="text-sm text-gray-700 whitespace-pre-line ml-2">{{ $d['decisiones_control'] }}</div>
                            @endif
                            @if(!empty($d['resultado_entregable']))
                                <p class="font-medium text-gray-700 text-sm mt-3 mb-1">Resultado / Entregable:</p>
                                <div class="text-sm text-gray-700 whitespace-pre-line ml-2">{{ $d['resultado_entregable'] }}</div>
                            @endif
                        </div>
                        @endif

                        {{-- HISTORIAL DE REVISIONES Y CAMBIOS --}}
                        <div>
                            <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">HISTORIAL DE REVISIONES Y CAMBIOS</h3>
                            <table class="w-full border-collapse text-sm">
                                <thead>
                                    <tr class="bg-[#e8eef8]">
                                        <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800 w-16">Versión</th>
                                        <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800 w-24">Fecha</th>
                                        <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Elaboró</th>
                                        <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Descripción del cambio</th>
                                        <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Aprobó</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse($versionHistory as $v)
                                    <tr class="{{ $loop->odd ? 'bg-white' : 'bg-gray-50' }}">
                                        <td class="border border-gray-300 px-3 py-2 text-center font-medium">{{ str_pad($v->version_number, 2, '0', STR_PAD_LEFT) }}</td>
                                        <td class="border border-gray-300 px-3 py-2">{{ $v->issued_at?->format('d/m/Y') ?? $v->created_at->format('d/m/Y') }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $v->uploader?->name ?? $d['quien_elabora'] ?? '—' }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $v->change_description ?: ($v->version_number == 1 ? 'Creación inicial del documento' : '—') }}</td>
                                        <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $d['quien_aprueba'] ?? '—' }}</td>
                                    </tr>
                                    @empty
                                    <tr>
                                        <td colspan="5" class="border border-gray-300 px-3 py-2 text-gray-400 text-center italic">Sin versiones registradas</td>
                                    </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        {{-- ANEXOS --}}
                        @if(!empty($d['procedimientos_relacionados']) || !empty($d['documentos_usados']))
                        <div>
                            <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">ANEXOS</h3>
                            <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2">
                                @foreach($parseLines($d['procedimientos_relacionados'] ?? '') as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                                @foreach($parseLines($d['documentos_usados'] ?? '') as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ol>
                        </div>
                        @endif

                        {{-- AVISO DE CONTROL --}}
                        @if(!empty($d['riesgos_errores']))
                        <div class="border-t border-gray-300 pt-3">
                            <p class="text-sm text-gray-700"><span class="font-bold text-[#1A428A]">AVISO DE CONTROL:</span> {{ $d['riesgos_errores'] }}</p>
                        </div>
                        @endif

                        {{-- Pie de página 2 --}}
                        <div class="border-t border-gray-300 pt-3 text-center text-xs text-gray-400 italic">
                            Documento controlado — Prohibida su reproducción parcial o total sin autorización | Versión <em>impresa no controlada</em>. Verifique vigencia en el sistema.
                        </div>
                    </div>
                </div>

            </div>
        </div>
        @endif

        {{-- ===== FLUJO DE APROBACIÓN — acceso rápido ===== --}}
        @if($regulation->impact_level)
            @php $apColor = $regulation->approvalStatusColor(); @endphp

            {{-- Alerta si el usuario tiene una aprobación pendiente --}}
            @if($pendingApprovalForUser)
                <div class="mt-6 flex items-center gap-3 rounded-xl border border-yellow-200 bg-yellow-50 px-5 py-4">
                    <span class="h-2.5 w-2.5 rounded-full bg-yellow-400 shrink-0"></span>
                    <p class="text-sm font-medium text-yellow-800 flex-1">
                        Tienes una aprobación pendiente en este documento (paso {{ $pendingApprovalForUser->step_number }}).
                    </p>
                    <a href="{{ route('processes.flow', $regulation) }}"
                       class="shrink-0 px-4 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                        Ir al flujo
                    </a>
                </div>
            @endif

            {{-- Tarjeta de estado del flujo + botón "Ver flujo" --}}
            <div class="mt-{{ $pendingApprovalForUser ? '3' : '8' }} flex items-center justify-between rounded-xl border border-gray-200 bg-gray-50 px-5 py-4">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-sm font-semibold text-gray-700">Flujo de aprobación</span>
                    <span class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded border
                        {{ $apColor === 'green'  ? 'bg-green-50 text-green-700 border-green-200' : '' }}
                        {{ $apColor === 'yellow' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : '' }}
                        {{ $apColor === 'red'    ? 'bg-red-50 text-red-700 border-red-200' : '' }}
                        {{ $apColor === 'blue'   ? 'bg-blue-50 text-blue-700 border-blue-200' : '' }}">
                        <span class="h-2 w-2 rounded-full
                            {{ $apColor === 'green'  ? 'bg-green-500' : '' }}
                            {{ $apColor === 'yellow' ? 'bg-yellow-400' : '' }}
                            {{ $apColor === 'red'    ? 'bg-red-500' : '' }}
                            {{ $apColor === 'blue'   ? 'bg-blue-500' : '' }}">
                        </span>
                        {{ $regulation->approvalStatusLabel() }}
                    </span>
                    <span class="text-xs text-gray-400">Impacto: {{ $regulation->impactLevelLabel() }}</span>
                </div>
                <a href="{{ route('processes.flow', $regulation) }}"
                   class="shrink-0 flex items-center gap-1.5 px-4 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    Ver flujo
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                    </svg>
                </a>
            </div>
        @elseif($regulation->approval_status === 'approved' && !$regulation->flow_locked)
            {{-- Documento cargado y aprobado externamente (sin flujo interno) --}}
            <div class="mt-8 flex items-start gap-4 rounded-xl border border-green-200 bg-green-50 px-5 py-4">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-green-500 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <div>
                    <p class="text-sm font-semibold text-green-800">Documento aprobado externamente</p>
                    <p class="text-xs text-green-700 mt-0.5">
                        Aprobado por: <span class="font-medium">{{ $regulation->details['quien_aprueba'] ?? '—' }}</span>
                        @if(!empty($regulation->details['quien_elabora']))
                            · Elaborado por: <span class="font-medium">{{ $regulation->details['quien_elabora'] }}</span>
                        @endif
                    </p>
                    <p class="text-xs text-green-600 mt-1">Este documento fue cargado con aprobación previa y no requiere flujo interno.</p>
                </div>
            </div>
        @endif
        {{-- ===== FIN FLUJO DE APROBACIÓN ===== --}}

        {{-- Columnas: subir + versión actual --}}
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">

            {{-- Subir nueva versión --}}
            @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                <div class="bg-white border rounded-xl overflow-hidden">
                    <div class="p-5 border-b">
                        <div class="font-semibold text-[#1A428A]">
                            {{ $currentVersion ? 'Subir nueva versión' : 'Subir documento' }}
                        </div>
                        <div class="text-sm text-gray-500">
                            Sube un archivo. Quedará registrado como nueva versión y el historial se conserva.
                        </div>
                    </div>

                    <div class="p-5">
                        <form method="POST"
                              action="{{ route('processes.versions.store', $regulation) }}"
                              enctype="multipart/form-data"
                              class="space-y-4">
                            @csrf

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Archivo</label>
                                <input type="file"
                                       name="file"
                                       class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                                       required>
                                @error('file')
                                    <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                                @enderror
                                <p class="text-xs text-gray-500 mt-1">PDF, Word, Excel o PowerPoint. Máximo 10 MB.</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Cambio / Modificación
                                </label>
                                <textarea name="change_description"
                                          rows="2"
                                          placeholder="Describe brevemente qué cambió en esta versión..."
                                          class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">{{ old('change_description') }}</textarea>
                            </div>

                            <p class="text-xs text-gray-400">La vigencia se asigna automáticamente: 1 año a partir de la fecha de subida.</p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Responsable</label>
                                <select name="responsible_name"
                                        class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <option value="">— Seleccionar —</option>
                                    @foreach($users as $u)
                                        <option value="{{ $u->name }}"
                                                @selected(old('responsible_name') === $u->name)>
                                            {{ $u->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <button type="submit"
                                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                                {{ $currentVersion ? 'Subir nueva versión' : 'Subir documento' }}
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Versión actual --}}
            <div class="bg-white border rounded-xl overflow-hidden">
                <div class="p-5 border-b">
                    <div class="font-semibold text-[#1A428A]">Versión actual</div>
                    <div class="text-sm text-gray-500">
                        La versión vigente. Las anteriores permanecen en el historial.
                    </div>
                </div>

                <div class="p-5">
                    @if($currentVersion)
                        <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="font-semibold text-gray-900 truncate">
                                    {{ $currentVersion->original_name ?? basename($currentVersion->file_path) }}
                                </div>
                                <div class="text-sm text-gray-500 mt-1 space-y-0.5">
                                    <div>Versión: <span class="font-medium text-gray-700">{{ $currentVersion->version_number }}</span> · <span class="text-green-700 font-medium">Actual</span></div>
                                    @if($currentVersion->change_description)
                                        <div>Cambio: {{ $currentVersion->change_description }}</div>
                                    @endif
                                    @if($currentVersion->responsible_name)
                                        <div>Responsable: <span class="font-medium text-gray-700">{{ $currentVersion->responsible_name }}</span></div>
                                    @endif
                                    @if($currentVersion->issued_at)
                                        <div>Emisión: {{ $currentVersion->issued_at->format('d/m/Y') }}</div>
                                    @endif
                                    @if($currentVersion->valid_until)
                                        <div class="{{ $currentVersion->isExpired() ? 'text-red-600 font-medium' : ($currentVersion->isNearExpiration() ? 'text-yellow-600 font-medium' : '') }}">
                                            Vigente hasta: {{ $currentVersion->valid_until->format('d/m/Y') }}
                                            @if($currentVersion->isExpired()) <span class="text-xs">(Vencido)</span>
                                            @elseif($currentVersion->isNearExpiration()) <span class="text-xs">(Por vencer)</span>
                                            @endif
                                        </div>
                                    @endif
                                    <div>Subido por: {{ $currentVersion->uploader?->name ?? '—' }} · {{ $currentVersion->created_at->format('d/m/Y H:i') }}</div>
                                </div>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="{{ route('regulation-versions.preview', $currentVersion) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>
                                <a href="{{ route('regulation-versions.download', $currentVersion) }}"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Descargar
                                </a>
                                @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                    <button type="button"
                                            onclick="openDeleteModal(
                                                '{{ route('regulation-versions.destroy', [$regulation, $currentVersion]) }}',
                                                @js($currentVersion->original_name ?? basename($currentVersion->file_path)),
                                                '{{ $currentVersion->version_number }}'
                                            )"
                                            class="px-3 py-2 rounded-md font-semibold text-sm bg-[#DB0000] text-white hover:bg-red-700">
                                        Eliminar
                                    </button>
                                @endif
                            </div>
                        </div>
                    @else
                        <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                            Aún no hay archivo subido para este documento.
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- Historial de versiones --}}
        <div class="mt-8 bg-white border rounded-xl overflow-hidden">
            <div class="p-5 border-b">
                <div class="font-semibold text-[#1A428A]">Histórico de versiones</div>
                <div class="text-sm text-gray-500">
                    Se conservan todas las versiones registradas del documento.
                </div>
            </div>

            <div class="p-5">
                @if($versionHistory->isNotEmpty())
                    <div class="space-y-3">
                        @foreach($versionHistory as $v)
                            <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="font-semibold text-gray-900 truncate">
                                        {{ $v->original_name ?? basename($v->file_path) }}
                                    </div>
                                    <div class="text-sm text-gray-500 mt-1 space-y-0.5">
                                        <div>
                                            Versión: <span class="font-medium text-gray-700">{{ $v->version_number }}</span>
                                            ·
                                            @if($v->is_current)
                                                <span class="text-green-700 font-medium">Actual</span>
                                            @else
                                                <span class="text-gray-500">Reemplazada</span>
                                            @endif
                                        </div>
                                        @if($v->change_description)
                                            <div>{{ $v->change_description }}</div>
                                        @endif
                                        @if($v->responsible_name)
                                            <div>Responsable: <span class="font-medium text-gray-700">{{ $v->responsible_name }}</span></div>
                                        @endif
                                        @if($v->issued_at)
                                            <div>Emisión: {{ $v->issued_at->format('d/m/Y') }}</div>
                                        @endif
                                        @if($v->valid_until)
                                            <div>Vigente hasta: {{ $v->valid_until->format('d/m/Y') }}</div>
                                        @endif
                                        <div>Subido por: {{ $v->uploader?->name ?? '—' }} · {{ $v->created_at->format('d/m/Y H:i') }}</div>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <a href="{{ route('regulation-versions.preview', $v) }}"
                                       target="_blank"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Ver
                                    </a>
                                    <a href="{{ route('regulation-versions.download', $v) }}"
                                       class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                        Descargar
                                    </a>
                                    @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                        <button type="button"
                                                onclick="openDeleteModal(
                                                    '{{ route('regulation-versions.destroy', [$regulation, $v]) }}',
                                                    @js($v->original_name ?? basename($v->file_path)),
                                                    '{{ $v->version_number }}'
                                                )"
                                                class="px-3 py-2 rounded-md font-semibold text-sm bg-[#DB0000] text-white hover:bg-red-700">
                                            Eliminar
                                        </button>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                        Aún no hay versiones registradas.
                    </div>
                @endif
            </div>
        </div>

    </div>

    {{-- Modal: Compartir (2 pestañas: Enviar + Quién lo vio) --}}
    @if($regulation->approval_status === 'approved' && (auth()->user()->isAdmin() || auth()->user()->isOperative()))
    <div id="shareModal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4"
         x-data="shareModalApp({{ Js::from($shareableUsers) }}, {{ Js::from($shareRecipients->map(fn($s) => [
             'name'      => $s->recipient?->name ?? '—',
             'email'     => $s->recipient?->email ?? '',
             'sender'    => $s->sender?->name ?? '—',
             'sent_at'   => $s->sent_at?->format('d/m/Y H:i') ?? '—',
             'viewed_at' => $s->viewed_at?->format('d/m/Y H:i'),
         ])->values()) }})">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl flex flex-col max-h-[90vh]">

            {{-- Header --}}
            <div class="p-5 border-b flex items-center justify-between shrink-0">
                <h3 class="text-base font-bold text-gray-900">Compartir documento</h3>
                <button type="button" onclick="closeShareModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Pestañas --}}
            <div class="flex border-b shrink-0">
                <button type="button"
                        @click="tab = 'send'"
                        :class="tab === 'send' ? 'border-b-2 border-[#1A428A] text-[#1A428A] font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="px-5 py-2.5 text-sm transition">
                    Enviar notificación
                </button>
                <button type="button"
                        @click="tab = 'track'"
                        :class="tab === 'track' ? 'border-b-2 border-[#1A428A] text-[#1A428A] font-semibold' : 'text-gray-500 hover:text-gray-700'"
                        class="px-5 py-2.5 text-sm transition flex items-center gap-1.5">
                    Quién lo vio
                    <span class="text-xs rounded-full px-1.5 py-0.5 font-medium"
                          :class="viewedCount > 0 ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                          x-text="`${viewedCount}/${recipients.length}`"></span>
                </button>
            </div>

            {{-- Pestaña: Enviar --}}
            <div x-show="tab === 'send'" class="flex flex-col overflow-hidden">
                <form method="POST" action="{{ route('processes.share', $regulation) }}" class="flex flex-col overflow-hidden">
                    @csrf
                    <div class="p-5 space-y-4 overflow-y-auto">
                        <p class="text-xs text-gray-500">Las personas seleccionadas recibirán un correo con enlace de acceso al documento.</p>

                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1 uppercase tracking-wide">Buscar persona</label>
                            <input type="text"
                                   x-model="search"
                                   placeholder="Nombre o correo…"
                                   class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                        </div>

                        <div class="border rounded-lg divide-y">
                            <template x-for="u in filtered()" :key="u.id">
                                <label class="flex items-center gap-3 px-4 py-2.5 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox"
                                           name="user_ids[]"
                                           :value="u.id"
                                           x-model="selected"
                                           class="rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A]">
                                    <div>
                                        <div class="text-sm font-medium text-gray-800" x-text="u.name"></div>
                                        <div class="text-xs text-gray-400" x-text="u.email"></div>
                                    </div>
                                </label>
                            </template>
                            <template x-if="filtered().length === 0">
                                <div class="px-4 py-3 text-sm text-gray-400 italic">Sin resultados</div>
                            </template>
                        </div>

                        <p x-show="showHint" class="text-xs text-gray-400">
                            Mostrando 5 de <span x-text="users.length"></span>. Busca por nombre o correo para ver más.
                        </p>
                        <p class="text-xs text-gray-400" x-show="selected.length > 0" x-text="`${selected.length} persona(s) seleccionada(s)`"></p>
                    </div>

                    <div class="p-5 border-t shrink-0 flex items-center justify-end gap-3">
                        <button type="button" onclick="closeShareModal()"
                                class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </button>
                        <button type="submit"
                                :disabled="selected.length === 0"
                                :class="selected.length > 0 ? 'bg-[#1A428A] hover:bg-[#15356d] text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                class="px-5 py-2 rounded-md text-sm font-semibold transition">
                            Enviar notificación
                        </button>
                    </div>
                </form>
            </div>

            {{-- Pestaña: Quién lo vio --}}
            <div x-show="tab === 'track'" class="flex flex-col overflow-hidden">
                <div class="p-5 overflow-y-auto">
                    <template x-if="recipients.length === 0">
                        <p class="text-sm text-gray-400">Aún no se han enviado notificaciones para este documento.</p>
                    </template>
                    <template x-if="recipients.length > 0">
                        <div>
                            <p class="text-xs text-gray-500 mb-3"
                               x-text="`${viewedCount} de ${recipients.length} persona(s) abrieron el enlace`"></p>
                            <div class="divide-y border rounded-lg overflow-hidden">
                                <template x-for="(r, i) in recipients" :key="i">
                                    <div class="flex items-center justify-between px-4 py-3 bg-white hover:bg-gray-50">
                                        <div>
                                            <div class="text-sm font-medium text-gray-800" x-text="r.name"></div>
                                            <div class="text-xs text-gray-400" x-text="r.email"></div>
                                            <div class="text-xs text-gray-400 mt-0.5" x-text="`Enviado: ${r.sent_at} · por ${r.sender}`"></div>
                                        </div>
                                        <div class="shrink-0 ml-4">
                                            <template x-if="r.viewed_at">
                                                <span class="inline-flex items-center gap-1.5 text-xs text-green-700 font-medium">
                                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>
                                                    <span x-text="r.viewed_at"></span>
                                                </span>
                                            </template>
                                            <template x-if="!r.viewed_at">
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                                                    <span class="h-2 w-2 rounded-full bg-gray-300"></span>
                                                    No abierto
                                                </span>
                                            </template>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="p-5 border-t shrink-0 flex justify-end">
                    <button type="button" onclick="closeShareModal()"
                            class="px-4 py-2 rounded-md border border-gray-300 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                        Cerrar
                    </button>
                </div>
            </div>

        </div>
    </div>

    <script>
    function openShareModal(tab) {
        const m = document.getElementById('shareModal');
        m.classList.remove('hidden');
        m.classList.add('flex');
        const comp = m._x_dataStack?.[0];
        if (comp) comp.tab = tab || 'send';
    }
    function closeShareModal() {
        const m = document.getElementById('shareModal');
        m.classList.add('hidden');
        m.classList.remove('flex');
    }
    function shareModalApp(users, recipients) {
        return {
            tab: 'send',
            users,
            recipients,
            search: '',
            selected: [],
            get viewedCount() { return this.recipients.filter(r => r.viewed_at).length; },
            filtered() {
                const q = this.search.toLowerCase().trim();
                if (!q) return this.users.slice(0, 5);
                return this.users.filter(u =>
                    u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
                );
            },
            get showHint() {
                return !this.search.trim() && this.users.length > 5;
            },
        };
    }
    </script>
    @endif

    {{-- Modal: Confirmar eliminación de versión --}}
    <div id="deleteModal"
         class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 px-4">
        <div class="w-full max-w-lg rounded-xl bg-white shadow-2xl">
            <div class="p-6 border-b">
                <h3 class="text-lg font-bold text-gray-900">Confirmar eliminación</h3>
                <p class="mt-2 text-sm text-gray-600">
                    Esta acción eliminará la versión del historial permanentemente. Úsala solo si el archivo fue cargado por error.
                </p>
            </div>

            <div class="p-6 space-y-4">
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-sm text-gray-700">
                    <div><span class="font-semibold">Archivo:</span> <span id="deleteFileName">—</span></div>
                    <div class="mt-1"><span class="font-semibold">Versión:</span> <span id="deleteVersionNumber">—</span></div>
                </div>

                <div>
                    <label for="delete_confirmation" class="block text-sm font-medium text-gray-700 mb-1">
                        Escribe <span class="font-bold">ELIMINAR</span> para confirmar
                    </label>
                    <input id="delete_confirmation"
                           type="text"
                           class="block w-full rounded-md border-gray-300 focus:border-red-600 focus:ring-red-600 text-sm"
                           placeholder="ELIMINAR"
                           oninput="validateDeleteConfirmation()">
                </div>

                <form id="deleteForm" method="POST" class="flex items-center justify-end gap-3">
                    @csrf
                    @method('DELETE')

                    <button type="button"
                            onclick="closeDeleteModal()"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>

                    <button id="deleteSubmitButton"
                            type="submit"
                            disabled
                            class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold opacity-50 cursor-not-allowed">
                        Confirmar eliminación
                    </button>
                </form>
            </div>
        </div>
    </div>

    @if(auth()->user()->isAdmin() && request('review_flow') && $regulation->flow_locked)
    {{-- Datos PHP → JS sin tocar atributos HTML (evita que @json rompa x-data="") --}}
    <script>
    window.__rfData = {
        usersByPosition: @json($usersByPosition),
        flowDefs:        @json($flowDefinitions),
        positionLabels:  @json($positionLabels),
        positionSortOrders: @json($positionSortOrders),
        impactLevels:    @json(\App\Models\Regulation::IMPACT_LEVELS),
    };
    </script>

    {{-- Modal unificado: detectar cambios → ¿mantener o cambiar flujo? → nivel + personas --}}
    <div x-data="reviewFlowModal('{{ $regulation->impact_level }}')">

        {{-- Formulario oculto para enviar a setFlow --}}
        <form x-ref="flowForm" method="POST" action="{{ route('processes.setFlow', $regulation) }}">
            @csrf
            @method('PATCH')
            <input type="hidden" name="impact_level" :value="selectedLevel">
            <template x-for="pos in positions" :key="pos.slug">
                <template x-for="u in (selected[pos.slug] || [])" :key="u.id">
                    <input type="hidden" :name="`users[${pos.slug}][]`" :value="u.id">
                </template>
            </template>
        </form>

        {{-- Backdrop --}}
        <div x-show="show"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4"
             style="display:none;">

            <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto overflow-hidden">

                {{-- Header --}}
                <div class="bg-[#1A428A] px-6 py-4 flex items-center justify-between">
                    <div>
                        <h3 class="text-white font-semibold text-base">Flujo de aprobación</h3>
                        <p class="text-blue-200 text-xs mt-0.5">Se detectaron cambios en el documento</p>
                    </div>
                    <a href="{{ route('processes.show', $regulation) }}"
                       class="text-blue-200 hover:text-white transition"
                       title="Cerrar y mantener flujo">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </a>
                </div>

                {{-- Etapa 1: ¿Mantener o cambiar? --}}
                <div x-show="stage === 1" class="px-6 py-6">
                    <p class="text-sm text-gray-700 leading-relaxed">
                        El flujo de aprobación fue configurado antes de esta edición.
                        ¿Deseas mantenerlo igual o reconfigurarlo para reflejar los cambios?
                    </p>
                    <div class="mt-6 flex justify-end gap-3">
                        <a href="{{ route('processes.show', $regulation) }}"
                           class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 font-medium hover:bg-gray-100 transition">
                            Mantener flujo actual
                        </a>
                        <button type="button" @click="goStage2()"
                                class="px-4 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d] transition">
                            Cambiar flujo
                        </button>
                    </div>
                </div>

                {{-- Etapa 2: Nivel + personas --}}
                <div x-show="stage === 2">
                    <div class="px-6 py-5 space-y-5 max-h-[60vh] overflow-y-auto">

                        {{-- Selector de nivel --}}
                        <div>
                            <label class="block text-xs font-semibold uppercase tracking-wide text-gray-500 mb-1.5">Nivel de impacto</label>
                            <select x-model="selectedLevel" @change="buildPositions()"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:border-[#1A428A] focus:ring-1 focus:ring-[#1A428A]">
                                @foreach(\App\Models\Regulation::IMPACT_LEVELS as $lvlKey => $lvlLabel)
                                    <option value="{{ $lvlKey }}">{{ $lvlLabel }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Personas por puesto --}}
                        <template x-for="pos in positions" :key="pos.slug">
                            <div>
                                <div class="flex items-center gap-1.5 mb-1.5">
                                    <label class="text-xs font-semibold text-gray-700" x-text="pos.label"></label>
                                    <span x-show="!pos.requiresAll" class="text-xs text-gray-400 font-normal">(cualquiera basta)</span>
                                    <span class="ml-auto text-xs"
                                          :class="(selected[pos.slug]||[]).length > 0 ? 'text-green-600' : 'text-red-400'"
                                          x-text="(selected[pos.slug]||[]).length > 0 ? (selected[pos.slug]||[]).length + ' asignado(s)' : 'Requerido'"></span>
                                </div>
                                <div x-show="(selected[pos.slug]||[]).length > 0" class="flex flex-wrap gap-1.5 mb-2">
                                    <template x-for="u in (selected[pos.slug]||[])" :key="u.id">
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-100 text-blue-800 text-xs font-medium">
                                            <span x-text="u.name"></span>
                                            <button type="button" @click="removeUser(pos.slug, u.id)" class="ml-0.5 text-blue-500 hover:text-blue-800">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </span>
                                    </template>
                                </div>
                                <div class="relative">
                                    <input type="text"
                                           :placeholder="`Buscar persona para ${pos.label}…`"
                                           x-model="search[pos.slug]"
                                           @focus="open[pos.slug] = true"
                                           @input="open[pos.slug] = true"
                                           @keydown.escape="open[pos.slug] = false; search[pos.slug] = ''"
                                           class="w-full rounded-lg border border-gray-300 text-sm px-3 py-2 focus:outline-none focus:border-[#1A428A] focus:ring-1 focus:ring-[#1A428A]">
                                    <div x-show="open[pos.slug] && filtered(pos.slug).length > 0"
                                         @click.outside="open[pos.slug] = false"
                                         class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-40 overflow-y-auto">
                                        <template x-for="u in filtered(pos.slug)" :key="u.id">
                                            <button type="button" @click="selectUser(pos.slug, u)"
                                                    class="w-full text-left px-3 py-2 text-sm hover:bg-blue-50 flex items-center gap-2">
                                                <span class="h-6 w-6 rounded-full bg-[#1A428A] text-white text-xs flex items-center justify-center shrink-0"
                                                      x-text="u.name.charAt(0).toUpperCase()"></span>
                                                <div>
                                                    <div class="font-medium text-gray-800" x-text="u.name"></div>
                                                    <div class="text-xs text-gray-400" x-text="u.email"></div>
                                                </div>
                                            </button>
                                        </template>
                                        <div x-show="filtered(pos.slug).length === 0"
                                             class="px-3 py-2 text-sm text-gray-400 italic">Sin coincidencias</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>

                    {{-- Footer etapa 2 --}}
                    <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between">
                        <button type="button" @click="stage = 1"
                                class="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1 transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                            </svg>
                            Atrás
                        </button>
                        <div class="flex gap-3">
                            <a href="{{ route('processes.show', $regulation) }}"
                               class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 font-medium hover:bg-gray-100 transition">
                                Cancelar
                            </a>
                            <button type="button" @click="confirmFlow()"
                                    :disabled="!canConfirm"
                                    :class="canConfirm ? 'bg-[#1A428A] hover:bg-[#15356d] text-white' : 'bg-gray-200 text-gray-400 cursor-not-allowed'"
                                    class="px-4 py-2 rounded-lg text-sm font-semibold transition">
                                Confirmar flujo
                            </button>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
    @endif

    <script>
        function openDeleteModal(actionUrl, fileName, versionNumber) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('deleteForm').action = actionUrl;
            document.getElementById('deleteFileName').textContent = fileName || '—';
            document.getElementById('deleteVersionNumber').textContent = versionNumber || '—';
            document.getElementById('delete_confirmation').value = '';
            const btn = document.getElementById('deleteSubmitButton');
            btn.disabled = true;
            btn.classList.add('opacity-50', 'cursor-not-allowed');
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.getElementById('delete_confirmation').focus();
        }

        function closeDeleteModal() {
            const modal = document.getElementById('deleteModal');
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }

        function validateDeleteConfirmation() {
            const valid = document.getElementById('delete_confirmation').value.trim() === 'ELIMINAR';
            const btn = document.getElementById('deleteSubmitButton');
            btn.disabled = !valid;
            btn.classList.toggle('opacity-50', !valid);
            btn.classList.toggle('cursor-not-allowed', !valid);
        }

        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeDeleteModal(); });
    </script>

    <script>
    function reviewFlowModal(currentLevel) {
        const d = window.__rfData || {};
        return {
            show: true,
            stage: 1,
            selectedLevel: currentLevel,
            impactLevels:      d.impactLevels      || {},
            usersByPosition:   d.usersByPosition   || {},
            flowDefs:          d.flowDefs          || {},
            positionLabels:    d.positionLabels    || {},
            positionSortOrders: d.positionSortOrders || {},
            positions: [],
            selected:  {},
            search:    {},
            open:      {},

            get canConfirm() {
                return this.positions.length > 0 &&
                       this.positions.every(p => (this.selected[p.slug] || []).length > 0);
            },

            buildPositions() {
                this.positions = []; this.selected = {}; this.search = {}; this.open = {};
                if (!this.selectedLevel || !this.flowDefs[this.selectedLevel]) return;
                const steps = this.flowDefs[this.selectedLevel];
                const seen  = new Set();
                Object.entries(steps).forEach(([step, stepDef]) => {
                    stepDef.positions.forEach(slug => {
                        if (!seen.has(slug)) {
                            seen.add(slug);
                            this.positions.push({
                                slug,
                                label: this.positionLabels[slug] || slug,
                                step: parseInt(step),
                                requiresAll: stepDef.requires_all,
                            });
                            this.selected[slug] = [];
                            this.search[slug]   = '';
                            this.open[slug]     = false;
                        }
                    });
                });
                this.positions.sort((a, b) =>
                    (this.positionSortOrders[a.slug] || 99) - (this.positionSortOrders[b.slug] || 99)
                );
            },

            goStage2() { this.buildPositions(); this.stage = 2; },

            filtered(slug) {
                const users = this.usersByPosition[slug] || [];
                const ids   = (this.selected[slug] || []).map(u => u.id);
                const avail = users.filter(u => !ids.includes(u.id));
                const q     = (this.search[slug] || '').toLowerCase();
                return q ? avail.filter(u =>
                    u.name.toLowerCase().includes(q) || u.email.toLowerCase().includes(q)
                ) : avail;
            },

            selectUser(slug, user) {
                if (!this.selected[slug]) this.selected[slug] = [];
                if (!this.selected[slug].find(u => u.id === user.id))
                    this.selected[slug] = [...this.selected[slug], user];
                this.search[slug] = '';
                this.open[slug]   = false;
            },

            removeUser(slug, uid) {
                this.selected[slug] = (this.selected[slug] || []).filter(u => u.id !== uid);
            },

            confirmFlow() {
                if (this.canConfirm) this.$refs.flowForm.submit();
            },
        };
    }
    </script>

</x-layouts.vigia>
