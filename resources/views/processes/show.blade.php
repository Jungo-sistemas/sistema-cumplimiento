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
                    <a href="{{ route('processes.edit', $regulation) }}"
                       class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
                        Editar
                    </a>
                @endif
                <a href="{{ route('processes.index') }}"
                   class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50">
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

            $fieldLabels = [
                'resultado_esperado'          => 'Objetivo',
                'problema_resuelve'           => 'Alcance / Problema que resuelve',
                'areas_aplica'                => 'Áreas donde aplica',
                'fuera_alcance'               => 'Fuera del alcance',
                'quien_elabora'               => 'Elaborado por',
                'quien_aprueba'               => 'Aprobado por',
                'fecha_vigencia'              => 'Fecha de vigencia',
                'indicador_proceso'           => 'Indicador de proceso',
                'indicador_resultado'         => 'Indicador de resultado',
                'meta_valor'                  => 'Meta',
                'frecuencia_medicion'         => 'Frecuencia de medición',
                'que_detona'                  => 'Detonante',
                'lista_actividades'           => 'Actividades',
                'decisiones_control'          => 'Decisiones y puntos de control',
                'resultado_entregable'        => 'Resultado / Entregable',
                'procedimientos_relacionados' => 'Procedimientos relacionados',
                'documentos_usados'           => 'Documentos utilizados',
                'terminos_abreviaturas'       => 'Términos y abreviaturas',
                'riesgos_errores'             => 'Aviso de control',
                'requerimientos_normativos'   => 'Requerimientos normativos',
                'areas_roles_mapa'            => 'Áreas / Roles',
                'areas_ejecutan'              => 'Áreas que ejecutan',
                'proveedores_clientes'        => 'Proveedores / Clientes',
            ];

            $changedFields = [];
            if (!empty($detailsPrevious)) {
                foreach ($fieldLabels as $key => $label) {
                    $oldVal = trim($detailsPrevious[$key] ?? '');
                    $newVal = trim($detailsCurrent[$key]  ?? '');
                    if (array_key_exists($key, $detailsPrevious) && $oldVal !== $newVal) {
                        $changedFields[$key] = ['label' => $label, 'old' => $oldVal, 'new' => $newVal];
                    }
                }
            }
        @endphp

        @if(!empty($changedFields))
        <div class="mt-6" x-data="{ open: false }">
            <button @click="open = !open"
                    class="w-full flex items-center justify-between px-5 py-3.5 rounded-xl border border-amber-200 bg-amber-50 hover:bg-amber-100 transition text-left">
                <div class="flex items-center gap-2.5">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    <span class="text-sm font-semibold text-amber-800">
                        Última edición modificó {{ count($changedFields) }} {{ count($changedFields) === 1 ? 'campo' : 'campos' }}
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
                 class="mt-1 rounded-xl border border-amber-200 overflow-hidden divide-y divide-amber-100">
                @foreach($changedFields as $info)
                <div class="px-5 py-4 bg-white">
                    <p class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-2">{{ $info['label'] }}</p>
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
                                <p class="text-xs text-gray-500 mt-1">PDF, JPG o PNG. Máximo 10 MB.</p>
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

</x-layouts.vigia>
