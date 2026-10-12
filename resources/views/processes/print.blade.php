<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $regulation->code }} — {{ $regulation->name }}</title>
    @vite(['resources/css/app.css'])
    <link rel="stylesheet" href="{{ asset('css/document-pagination.css') }}">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .page-break { page-break-before: always; }
        }
        body { background: #f3f4f6; }
        .doc-page-content { padding: 2rem; }
    </style>
</head>
<body class="text-sm text-gray-900">

    {{-- Toolbar --}}
    <div class="no-print sticky top-0 z-10 bg-[#002060] text-white px-6 py-3 flex items-center justify-between shadow">
        <div class="flex items-center gap-3">
            <a href="{{ route('processes.index', ['company_id' => $regulation->company_id]) }}"
               class="text-white/80 hover:text-white text-sm">Volver</a>
            <span class="text-white/40">|</span>
            <span class="font-semibold text-sm">{{ $regulation->code }} — {{ $regulation->name }}</span>
        </div>
        <button onclick="window.print()"
                class="px-4 py-1.5 rounded bg-white text-[#002060] text-sm font-semibold hover:bg-blue-50">
            Imprimir / Guardar PDF
        </button>
    </div>

    @php
        $d    = $regulation->details ?? [];
        $prev = $regulation->previous_details ?? [];
        $vNum = str_pad((string) ($currentVersion?->version_number ?? 1), 2, '0', STR_PAD_LEFT);

        // Returns true when the field value changed since the previous edit
        $chg = fn(string $field) => !empty($prev)
            && array_key_exists($field, $prev)
            && trim($prev[$field] ?? '') !== trim($d[$field] ?? '');

        $parseLines = fn(?string $text) => array_filter(
            array_map('trim', explode("\n", $text ?? '')),
            fn($l) => $l !== ''
        );

        $fechaVig = $d['fecha_vigencia'] ?? null;
        $fechaFmt = $fechaVig ? \Carbon\Carbon::parse($fechaVig)->format('d/m/Y') : 'DD/MM/AAAA';

        $headerMeta = [
            'nombre'                   => $regulation->name,
            'codigo'                   => $regulation->code ?? '—',
            'version'                  => $vNum,
            'quien_elabora'            => $d['quien_elabora'] ?? '—',
            'quien_aprueba'            => $d['quien_aprueba'] ?? '—',
            'fecha_vigencia_formatted' => $fechaFmt,
        ];
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

    <div class="max-w-4xl mx-auto my-8 bg-white shadow-xl print:shadow-none print:my-0 print:max-w-none doc-page">

        {{-- Encabezado — mismo contenido/colores que RegulationDocxHeaderBuilder (el que arma el .docx).
             Un anexo no es un procedimiento en sí, así que no lleva esta tabla formal. --}}
        @unless($regulation->is_annex)
            @include('processes.partials.header-table', ['meta' => $headerMeta])
        @endunless

        @if($currentVersion?->body_html)
            {{-- Documento generado/editado dentro del sistema: mismo HTML exacto usado para el .docx — "Ver" y "Descargar" quedan idénticos. --}}
            <div class="doc-page-content">
                {!! $currentVersion->body_html !!}
            </div>
        @else
            {{-- Documento subido manualmente (sin body_html): aproximación a partir de los campos capturados. --}}
            <div class="doc-page-content space-y-6">

                @if(!empty($d['resultado_esperado']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Objetivo</h3>
                    <div class="{{ $chg('resultado_esperado') ? 'ring-2 ring-yellow-400 bg-yellow-50' : 'bg-gray-50' }} border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['resultado_esperado'] }}</div>
                </div>
                @endif

                @if(!empty($d['areas_aplica']) || !empty($d['fuera_alcance']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Alcance</h3>
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
                @endif

                @if(!empty($d['indicador_proceso']) || !empty($d['indicador_resultado']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Indicadores</h3>
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

                @if(!empty($d['terminos_abreviaturas']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Definiciones y Abreviaturas</h3>
                    <div class="{{ $chg('terminos_abreviaturas') ? 'ring-2 ring-yellow-400 rounded' : '' }} text-sm text-gray-700 whitespace-pre-line">{{ $d['terminos_abreviaturas'] }}</div>
                </div>
                @endif

                @if(!empty($d['que_detona']) || !empty($d['lista_actividades']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Descripción del Proceso / Actividades</h3>
                    @if(!empty($d['que_detona']))
                        <p class="font-medium text-gray-700 text-sm mb-1">Detonante:</p>
                        <div class="{{ $chg('que_detona') ? 'ring-2 ring-yellow-400 bg-yellow-50' : 'bg-gray-50' }} border border-gray-200 rounded p-3 text-sm text-gray-700 mb-3 whitespace-pre-line">{{ $d['que_detona'] }}</div>
                    @endif
                    @if(!empty($d['lista_actividades']))
                        <div class="{{ $chg('lista_actividades') ? 'ring-2 ring-yellow-400 bg-yellow-50' : 'bg-gray-50' }} border border-gray-200 rounded p-3 text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $d['lista_actividades'] }}</div>
                    @endif
                    @if(!empty($d['resultado_entregable']))
                        <p class="font-medium text-gray-700 text-sm mt-3 mb-1">Resultado / Entregable:</p>
                        <div class="text-sm text-gray-700 whitespace-pre-line ml-2 {{ $chg('resultado_entregable') ? 'rounded bg-yellow-50 ring-2 ring-yellow-400 px-2 py-1' : '' }}">{{ $d['resultado_entregable'] }}</div>
                    @endif
                </div>
                @endif

                @if(!empty($d['riesgos_errores']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Riesgos conocidos y errores frecuentes</h3>
                    <div class="text-sm text-gray-700 whitespace-pre-line {{ $chg('riesgos_errores') ? 'rounded bg-yellow-50 ring-2 ring-yellow-400 px-2 py-1' : '' }}">{{ $d['riesgos_errores'] }}</div>
                </div>
                @endif

                @if(!empty($d['requerimientos_normativos']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Requerimientos normativos y legales</h3>
                    <div class="text-sm text-gray-700 whitespace-pre-line {{ $chg('requerimientos_normativos') ? 'rounded bg-yellow-50 ring-2 ring-yellow-400 px-2 py-1' : '' }}">{{ $d['requerimientos_normativos'] }}</div>
                </div>
                @endif

                @if(!empty($d['procedimientos_relacionados']) || !empty($d['documentos_usados']))
                <div>
                    <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Anexos</h3>
                    <ul class="list-disc list-inside space-y-1 text-sm text-gray-700 ml-2 {{ $chg('procedimientos_relacionados') || $chg('documentos_usados') ? 'rounded ring-2 ring-yellow-400 bg-yellow-50 px-2 py-1' : '' }}">
                        @foreach($parseLines($d['procedimientos_relacionados'] ?? '') as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                        @foreach($parseLines($d['documentos_usados'] ?? '') as $line)
                            <li>{{ $line }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

            </div>
        @endif

        {{-- Historial de Revisiones y Cambios — siempre desde la BD real, no depende del origen del documento --}}
        <div class="doc-page-content pt-0">
            <h3 class="font-bold text-[#1A5276] uppercase text-sm border-b-2 border-[#1A5276] pb-0.5 mb-2">Historial de Revisiones y Cambios</h3>
            <table class="w-full border-collapse text-sm">
                <thead>
                    <tr>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white w-16">Versión</th>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white w-24">Fecha</th>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white">Elaboró</th>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white">Descripción del cambio</th>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white">Justificación</th>
                        <th bgcolor="#002060" class="border border-gray-300 px-3 py-2 text-center font-semibold text-white">Aprobó</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($versionHistory as $v)
                    <tr>
                        <td class="border border-gray-300 px-3 py-2 text-center font-medium">{{ str_pad($v->version_number, 2, '0', STR_PAD_LEFT) }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-center">{{ $v->issued_at?->format('d/m/Y') ?? $v->created_at->format('d/m/Y') }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $v->uploader?->name ?? $d['quien_elabora'] ?? '—' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $v->change_description ?: ($v->version_number == 1 ? 'Creación inicial del documento' : '—') }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $v->change_justification ?: '—' }}</td>
                        <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $d['quien_aprueba'] ?? '—' }}</td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="border border-gray-300 px-3 py-4 text-gray-400 text-center italic">Sin versiones registradas</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Aviso de control — texto fijo, igual en todos los documentos --}}
        <div class="doc-page-content pt-0 pb-8">
            <p class="text-center text-xs text-gray-500 italic">
                AVISO DE CONTROL: Este documento es propiedad de {{ $regulation->company->name ?? 'la empresa' }}. Su contenido es confidencial y de uso interno.
                Cualquier copia impresa se considera NO CONTROLADA. Para la versión vigente, consultar el sistema de control de documentos.
            </p>
            <p class="text-center text-xs text-gray-400 italic mt-1">
                Documento controlado — Prohibida su reproducción parcial o total sin autorización | Versión impresa no controlada. Verifique vigencia en el sistema.
            </p>
        </div>

    </div>

    <script>
        // Auto-print when opened directly (optional — comment out if not desired)
        // window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
