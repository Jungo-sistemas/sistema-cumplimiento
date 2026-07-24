<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $regulation->code }} — {{ $regulation->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        body { background: #f3f4f6; }
    </style>
</head>
<body class="text-sm text-gray-900">

    {{-- Toolbar --}}
    <div class="no-print sticky top-0 z-10 bg-[#1A428A] text-white px-6 py-3 flex items-center justify-between shadow">
        <div class="flex items-center gap-3">
            <a href="{{ route('processes.index', ['company_id' => $regulation->company_id]) }}"
               class="text-white/80 hover:text-white text-sm">Volver</a>
            <span class="text-white/40">|</span>
            <span class="font-semibold text-sm">{{ $regulation->code }} — {{ $regulation->name }}</span>
        </div>
        <button onclick="window.print()"
                class="px-4 py-1.5 rounded bg-white text-[#1A428A] text-sm font-semibold hover:bg-blue-50">
            Imprimir / Guardar PDF
        </button>
    </div>

    @php
        $d    = $regulation->details ?? [];
        $prev = $regulation->previous_details ?? [];
        $vNum = $currentVersion?->version_number ?? '01';

        // Returns true when the field value changed since the previous edit
        $chg = fn(string $field) => !empty($prev)
            && array_key_exists($field, $prev)
            && trim($prev[$field] ?? '') !== trim($d[$field] ?? '');

        // CSS classes applied to content blocks that have changed
        $chgBlock = fn(string ...$fields) =>
            collect($fields)->some(fn($f) => $chg($f))
                ? 'ring-2 ring-yellow-400 bg-yellow-50'
                : 'bg-gray-50';

        $parseLines = fn(?string $text) => array_filter(
            array_map('trim', explode("\n", $text ?? '')),
            fn($l) => $l !== ''
        );

        $parseTerms = function(?string $text) {
            $rows = [];
            foreach (array_filter(array_map('trim', explode("\n", $text ?? '')), fn($l) => $l !== '') as $line) {
                if (preg_match('/^([^:=—–]+)[:=—–](.+)$/', $line, $m)) {
                    $rows[] = ['term' => trim($m[1]), 'def' => trim($m[2])];
                } else {
                    $rows[] = ['term' => $line, 'def' => ''];
                }
            }
            return $rows;
        };

        $fechaVig = $d['fecha_vigencia'] ?? null;
        $fechaFmt = $fechaVig ? \Carbon\Carbon::parse($fechaVig)->format('d/m/Y') : 'DD/MM/AAAA';
    @endphp

    @if(!empty($prev))
    <div class="no-print max-w-4xl mx-auto mt-4 mb-0 px-2">
        <div class="rounded-lg border border-yellow-300 bg-yellow-50 px-4 py-3 text-sm text-yellow-800 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 shrink-0 text-yellow-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
            </svg>
            <span><strong>Vista de cambios:</strong> Los campos resaltados en amarillo fueron modificados en la última edición.</span>
        </div>
    </div>
    @endif

    <div class="max-w-4xl mx-auto my-8 bg-white shadow-xl print:shadow-none print:my-0 print:max-w-none">

        {{-- ══ PÁGINA 1 ══ --}}
        {{-- Encabezado --}}
        <table class="w-full border-collapse text-xs">
            <tr>
                <td class="border border-gray-400 p-3 text-center w-1/4 align-middle">
                    <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">LOGO EMPRESA</div>
                    <div class="text-xs text-gray-400 italic">(insertar logotipo)</div>
                </td>
                <td class="border border-gray-400 p-3 text-center align-middle">
                    <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">{{ \Illuminate\Support\Str::upper($regulation->document_type ?? 'PROCEDIMIENTO') }}</div>
                    <div class="font-semibold text-gray-800 text-sm">{{ $regulation->name }}</div>
                </td>
                <td class="border border-gray-400 p-3 text-center w-28 align-middle">
                    <div class="text-xs font-bold text-gray-500 uppercase">CÓDIGO</div>
                    <div class="font-mono font-bold text-[#1A428A] text-sm mt-0.5">{{ $regulation->code ?? '—' }}</div>
                </td>
                <td class="border border-gray-400 p-3 text-center w-20 align-middle">
                    <div class="text-xs font-bold text-gray-500 uppercase">VERSIÓN</div>
                    <div class="font-bold text-gray-800 text-sm mt-0.5">{{ str_pad($vNum, 2, '0', STR_PAD_LEFT) }}</div>
                </td>
            </tr>
            <tr class="bg-gray-100">
                <td class="border border-gray-400 p-2 {{ $chg('quien_elabora') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                    <div class="text-xs font-bold text-gray-600 uppercase">ELABORADO POR:</div>
                    <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_elabora'] ?? '—' }}</div>
                </td>
                <td class="border border-gray-400 p-2 {{ $chg('quien_aprueba') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                    <div class="text-xs font-bold text-gray-600 uppercase">APROBADO POR:</div>
                    <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_aprueba'] ?? '—' }}</div>
                </td>
                <td class="border border-gray-400 p-2 {{ $chg('fecha_vigencia') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                    <div class="text-xs font-bold text-gray-600 uppercase">Fecha efectividad:</div>
                    <div class="text-xs text-gray-700 mt-0.5">{{ $fechaFmt }}</div>
                </td>
                <td class="border border-gray-400 p-2 text-center">
                    <div class="text-xs font-bold text-gray-600 uppercase">Página:</div>
                    <div class="text-xs text-gray-700 mt-0.5">1 de 2</div>
                </td>
            </tr>
        </table>

        <div class="p-8 space-y-6">

            {{-- OBJETIVO --}}
            <div>
                <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">OBJETIVO</h3>
                <div class="{{ $chgBlock('resultado_esperado') }} border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['resultado_esperado'] ?? '' }}</div>
            </div>

            {{-- ALCANCE --}}
            <div>
                <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">ALCANCE</h3>
                @if(!empty($d['problema_resuelve']))
                    <div class="{{ $chgBlock('problema_resuelve') }} border border-gray-200 rounded p-3 text-sm text-gray-700 mb-3 leading-relaxed whitespace-pre-line">{{ $d['problema_resuelve'] }}</div>
                @endif
                @if(!empty($d['areas_aplica']))
                    <p class="font-semibold text-gray-800 text-sm mb-1">Este procedimiento aplica a:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 mb-3 ml-2 {{ $chg('areas_aplica') ? 'rounded ring-2 ring-yellow-400 bg-yellow-50 px-2 py-1' : '' }}">
                        @foreach($parseLines($d['areas_aplica']) as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                @endif
                @if(!empty($d['fuera_alcance']))
                    <p class="font-semibold text-gray-800 text-sm mb-1">Queda fuera del alcance:</p>
                    <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 ml-2 {{ $chg('fuera_alcance') ? 'rounded ring-2 ring-yellow-400 bg-yellow-50 px-2 py-1' : '' }}">
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
                <ul class="list-disc list-inside space-y-0.5 text-sm text-gray-700 ml-2 {{ $chg('requerimientos_normativos') ? 'rounded ring-2 ring-yellow-400 bg-yellow-50 px-2 py-1' : '' }}">
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
                        <li class="{{ $chg('indicador_proceso') ? 'rounded bg-yellow-100 px-1' : '' }}"><span class="font-medium">Proceso:</span> {{ $d['indicador_proceso'] }}</li>
                    @endif
                    @if(!empty($d['indicador_resultado']))
                        <li class="{{ $chg('indicador_resultado') ? 'rounded bg-yellow-100 px-1' : '' }}"><span class="font-medium">Resultado:</span> {{ $d['indicador_resultado'] }}</li>
                    @endif
                    @if(!empty($d['meta_valor']))
                        <li class="{{ $chg('meta_valor') ? 'rounded bg-yellow-100 px-1' : '' }}"><span class="font-medium">Meta:</span> {{ $d['meta_valor'] }}</li>
                    @endif
                    @if(!empty($d['frecuencia_medicion']))
                        <li class="{{ $chg('frecuencia_medicion') ? 'rounded bg-yellow-100 px-1' : '' }}"><span class="font-medium">Frecuencia:</span> {{ $d['frecuencia_medicion'] }}</li>
                    @endif
                </ul>
            </div>
            @endif

            {{-- DEFINICIONES Y ABREVIATURAS --}}
            @if(!empty($d['terminos_abreviaturas']))
            <div>
                <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">DEFINICIONES Y ABREVIATURAS</h3>
                @php $terms = $parseTerms($d['terminos_abreviaturas']); @endphp
                <div class="{{ $chg('terminos_abreviaturas') ? 'ring-2 ring-yellow-400 rounded' : '' }}">
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
            </div>
            @endif

            {{-- Pie página 1 --}}
            <div class="border-t border-gray-300 pt-3 text-center text-xs text-gray-400 italic">
                Documento controlado — Prohibida su reproducción parcial o total sin autorización | Versión <em>impresa no controlada</em>. Verifique vigencia en el sistema.
            </div>
        </div>

        {{-- ══ PÁGINA 2 ══ --}}
        <div class="page-break border-t-4 border-[#1A428A]">
            <table class="w-full border-collapse text-xs">
                <tr>
                    <td class="border border-gray-400 p-3 text-center w-1/4 align-middle">
                        <div class="text-xs font-semibold text-gray-400 uppercase tracking-wide mb-1">LOGO EMPRESA</div>
                        <div class="text-xs text-gray-400 italic">(insertar logotipo)</div>
                    </td>
                    <td class="border border-gray-400 p-3 text-center align-middle">
                        <div class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1">{{ \Illuminate\Support\Str::upper($regulation->document_type ?? 'PROCEDIMIENTO') }}</div>
                        <div class="font-semibold text-gray-800 text-sm">{{ $regulation->name }}</div>
                    </td>
                    <td class="border border-gray-400 p-3 text-center w-28 align-middle">
                        <div class="text-xs font-bold text-gray-500 uppercase">CÓDIGO</div>
                        <div class="font-mono font-bold text-[#1A428A] text-sm mt-0.5">{{ $regulation->code ?? '—' }}</div>
                    </td>
                    <td class="border border-gray-400 p-3 text-center w-20 align-middle">
                        <div class="text-xs font-bold text-gray-500 uppercase">VERSIÓN</div>
                        <div class="font-bold text-gray-800 text-sm mt-0.5">{{ str_pad($vNum, 2, '0', STR_PAD_LEFT) }}</div>
                    </td>
                </tr>
                <tr class="bg-gray-100">
                    <td class="border border-gray-400 p-2 {{ $chg('quien_elabora') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                        <div class="text-xs font-bold text-gray-600 uppercase">ELABORADO POR:</div>
                        <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_elabora'] ?? '—' }}</div>
                    </td>
                    <td class="border border-gray-400 p-2 {{ $chg('quien_aprueba') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                        <div class="text-xs font-bold text-gray-600 uppercase">APROBADO POR:</div>
                        <div class="text-xs text-gray-700 mt-0.5">{{ $d['quien_aprueba'] ?? '—' }}</div>
                    </td>
                    <td class="border border-gray-400 p-2 {{ $chg('fecha_vigencia') ? 'bg-yellow-50 ring-1 ring-yellow-400' : '' }}">
                        <div class="text-xs font-bold text-gray-600 uppercase">Fecha efectividad:</div>
                        <div class="text-xs text-gray-700 mt-0.5">{{ $fechaFmt }}</div>
                    </td>
                    <td class="border border-gray-400 p-2 text-center">
                        <div class="text-xs font-bold text-gray-600 uppercase">Página:</div>
                        <div class="text-xs text-gray-700 mt-0.5">2 de 2</div>
                    </td>
                </tr>
            </table>

            <div class="p-8 space-y-6">

                {{-- DESCRIPCIÓN DEL PROCESO / ACTIVIDADES --}}
                @if(!empty($d['lista_actividades']) || !empty($d['que_detona']))
                <div>
                    <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">DESCRIPCIÓN DEL PROCESO / ACTIVIDADES</h3>
                    @if(!empty($d['que_detona']))
                        <p class="font-medium text-gray-700 text-sm mb-1">Detonante:</p>
                        <div class="{{ $chgBlock('que_detona') }} border border-gray-200 rounded p-3 text-sm text-gray-700 mb-3 whitespace-pre-line">{{ $d['que_detona'] }}</div>
                    @endif
                    @if(!empty($d['lista_actividades']))
                        <div class="{{ $chgBlock('lista_actividades') }} border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['lista_actividades'] }}</div>
                    @endif
                    @if(!empty($d['decisiones_control']))
                        <p class="font-medium text-gray-700 text-sm mt-3 mb-1">Decisiones y puntos de control:</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line ml-2 {{ $chg('decisiones_control') ? 'rounded bg-yellow-50 ring-2 ring-yellow-400 px-2 py-1' : '' }}">{{ $d['decisiones_control'] }}</div>
                    @endif
                    @if(!empty($d['resultado_entregable']))
                        <p class="font-medium text-gray-700 text-sm mt-3 mb-1">Resultado / Entregable:</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line ml-2 {{ $chg('resultado_entregable') ? 'rounded bg-yellow-50 ring-2 ring-yellow-400 px-2 py-1' : '' }}">{{ $d['resultado_entregable'] }}</div>
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
                                <td colspan="5" class="border border-gray-300 px-3 py-4 text-gray-400 text-center italic">Sin versiones registradas</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- ANEXOS --}}
                @if(!empty($d['procedimientos_relacionados']) || !empty($d['documentos_usados']))
                <div>
                    <h3 class="font-bold text-[#1A428A] uppercase text-sm border-b-2 border-[#1A428A] pb-0.5 mb-2">ANEXOS</h3>
                    <ol class="list-decimal list-inside space-y-1 text-sm text-gray-700 ml-2 {{ $chg('procedimientos_relacionados') || $chg('documentos_usados') ? 'rounded ring-2 ring-yellow-400 bg-yellow-50 px-2 py-1' : '' }}">
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
                <div class="border {{ $chg('riesgos_errores') ? 'border-yellow-400 bg-yellow-50 ring-2 ring-yellow-400' : 'border-[#1A428A] bg-blue-50' }} rounded p-3">
                    <p class="text-sm text-gray-800"><span class="font-bold text-[#1A428A]">AVISO DE CONTROL:</span> {{ $d['riesgos_errores'] }}</p>
                </div>
                @endif

                {{-- Pie página 2 --}}
                <div class="border-t border-gray-300 pt-3 text-center text-xs text-gray-400 italic">
                    Documento controlado — Prohibida su reproducción parcial o total sin autorización | Versión <em>impresa no controlada</em>. Verifique vigencia en el sistema.
                </div>
            </div>
        </div>

    </div>

    <script>
        // Auto-print when opened directly (optional — comment out if not desired)
        // window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
