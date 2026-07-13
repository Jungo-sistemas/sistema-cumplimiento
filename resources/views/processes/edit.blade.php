<x-layouts.vigia :title="'Editar prompt · ' . $regulation->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
        <span class="mx-2 text-gray-400">/</span>
        <a href="{{ route('processes.show', $regulation) }}" class="text-gray-500 hover:underline">
            <x-truncate max="max-w-[260px]">{{ $regulation->name }}</x-truncate>
        </a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">Editar prompt</span>
    </x-slot>

    @php
        $d = $regulation->details ?? [];
        $hasPrompt = !empty($d['resultado_esperado']) || !empty($d['problema_resuelve']);

        $initialForm = [
            'process_type_id'             => (string) ($regulation->process_type_id ?? ''),
            'document_type'               => $regulation->document_type ?? '',
            'nombre'                      => $regulation->name ?? '',
            'codigo'                      => $regulation->code ?? '',
            'quien_elabora'               => $d['quien_elabora'] ?? '',
            'quien_aprueba'               => $d['quien_aprueba'] ?? '',
            'fecha_vigencia'              => $d['fecha_vigencia'] ?? '',
            'problema_resuelve'           => $d['problema_resuelve'] ?? '',
            'resultado_esperado'          => $d['resultado_esperado'] ?? '',
            'areas_aplica'                => $d['areas_aplica'] ?? '',
            'fuera_alcance'               => $d['fuera_alcance'] ?? '',
            'indicador_proceso'           => $d['indicador_proceso'] ?? '',
            'indicador_resultado'         => $d['indicador_resultado'] ?? '',
            'meta_valor'                  => $d['meta_valor'] ?? '',
            'frecuencia_medicion'         => $d['frecuencia_medicion'] ?? '',
            'que_detona'                  => $d['que_detona'] ?? '',
            'lista_actividades'           => $d['lista_actividades'] ?? '',
            'areas_ejecutan'              => $d['areas_ejecutan'] ?? '',
            'decisiones_control'          => $d['decisiones_control'] ?? '',
            'documentos_usados'           => $d['documentos_usados'] ?? '',
            'resultado_entregable'        => $d['resultado_entregable'] ?? '',
            'areas_roles_mapa'            => $d['areas_roles_mapa'] ?? '',
            'procedimientos_relacionados' => $d['procedimientos_relacionados'] ?? '',
            'proveedores_clientes'        => $d['proveedores_clientes'] ?? '',
            'terminos_abreviaturas'       => $d['terminos_abreviaturas'] ?? '',
            'riesgos_errores'             => $d['riesgos_errores'] ?? '',
            'requerimientos_normativos'   => $d['requerimientos_normativos'] ?? '',
        ];

        $blockFields = [
            1 => ['process_type_id', 'document_type', 'nombre', 'codigo', 'quien_elabora', 'quien_aprueba', 'fecha_vigencia'],
            2 => ['problema_resuelve', 'resultado_esperado', 'areas_aplica', 'fuera_alcance'],
            3 => ['indicador_proceso', 'indicador_resultado', 'meta_valor', 'frecuencia_medicion'],
            4 => ['que_detona', 'lista_actividades', 'areas_ejecutan', 'decisiones_control', 'documentos_usados', 'resultado_entregable'],
            5 => ['areas_roles_mapa', 'procedimientos_relacionados', 'proveedores_clientes'],
            6 => ['terminos_abreviaturas', 'riesgos_errores', 'requerimientos_normativos'],
        ];
    @endphp

    @if(!$hasPrompt)
    <div class="mb-5 rounded-xl border border-blue-200 bg-blue-50 px-5 py-4 flex items-start gap-3">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-blue-400 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <p class="text-sm text-blue-800">
            Este proceso fue cargado sin prompt. Llena los bloques de contenido para generar uno nuevo.
        </p>
    </div>
    @endif

    @if($errors->first('ai'))
        <div class="mb-4 rounded-md bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm">
            {{ $errors->first('ai') }}
        </div>
    @endif

    <div
        x-data="wizardApp({{ Js::from($initialForm) }})"
        x-init="init()"
        class="pb-12"
    >
        {{-- ===== OVERLAY: GENERANDO CON IA ===== --}}
        <div x-show="submitting" style="display: none;"
             class="fixed inset-0 bg-black/50 z-50 flex items-center justify-center">
            <div class="bg-white rounded-lg px-8 py-6 shadow-xl text-center max-w-sm">
                <svg class="animate-spin h-8 w-8 text-[#1A428A] mx-auto mb-3" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                </svg>
                <p class="text-gray-700 font-medium">Generando la vista previa con IA…</p>
                <p class="text-gray-500 text-sm mt-1">Esto puede tardar uno o dos minutos. No cierres esta ventana. Podrás revisar el documento antes de guardar los cambios.</p>
            </div>
        </div>

        {{-- ===== TOP PROGRESS BAR ===== --}}
        <div class="mb-6">
            <div class="flex items-center justify-between mb-1">
                <h1 class="text-2xl font-semibold text-[#1A428A]">Editar Prompt</h1>
                <span class="text-sm text-gray-500" x-text="Math.round(overallProgress()) + '% completado'"></span>
            </div>
            <div class="h-2 bg-gray-200 rounded-full overflow-hidden">
                <div class="h-full bg-[#1A428A] rounded-full transition-all duration-300"
                     :style="'width:' + overallProgress() + '%'"></div>
            </div>
        </div>

        <div class="flex gap-6 items-start">

            {{-- ===== SIDEBAR ===== --}}
            <aside class="hidden lg:block w-64 shrink-0">
                <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                    <div class="px-4 py-3 border-b">
                        <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Bloques</p>
                    </div>
                    <nav class="py-2">
                        <template x-for="b in [1,2,3,4,5,6]" :key="b">
                            <button
                                type="button"
                                @click="goToBlock(b)"
                                :class="[
                                    'w-full flex items-center gap-3 px-4 py-3 text-left transition-colors cursor-pointer',
                                    currentBlock === b ? 'font-semibold' : 'text-gray-600 hover:bg-gray-50'
                                ]"
                                :style="currentBlock === b ? 'color:' + blockColor(b) + '; background:' + blockColorBg(b) : ''"
                            >
                                <span class="flex-shrink-0 w-7 h-7 rounded-full flex items-center justify-center text-xs font-bold border-2 transition-colors"
                                      :style="currentBlock === b
                                          ? 'border-color:' + blockColor(b) + '; background:' + blockColor(b) + '; color:white'
                                          : (isBlockComplete(b) ? 'border-color:#22c55e; background:#22c55e; color:white' : 'border-color:#d1d5db; color:#6b7280; background:white')"
                                >
                                    <template x-if="isBlockComplete(b) && currentBlock !== b">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                        </svg>
                                    </template>
                                    <template x-if="!(isBlockComplete(b) && currentBlock !== b)">
                                        <span x-text="b"></span>
                                    </template>
                                </span>

                                <div class="flex-1 min-w-0">
                                    <p class="text-sm leading-tight truncate" x-text="blockName(b)"></p>
                                    <p class="text-xs mt-0.5" :class="currentBlock === b ? 'opacity-70' : 'text-gray-400'"
                                       x-text="blockProgress(b).filled + '/' + blockProgress(b).total + ' campos'"></p>
                                </div>
                            </button>
                        </template>
                    </nav>
                </div>
            </aside>

            {{-- ===== MAIN FORM ===== --}}
            <div class="flex-1 min-w-0">
                <form
                    id="wizard-form"
                    method="POST"
                    action="{{ route('processes.preview.generateEdit', $regulation) }}"
                >
                    @csrf

                    {{-- ==================== BLOQUE 1: Identificación ==================== --}}
                    <div x-show="currentBlock === 1" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 1 — Identificación</h2>
                                <p class="text-sm text-white mt-0.5">Datos básicos del documento</p>
                            </div>
                            <div class="p-6 space-y-5">

                                {{-- Empresa: sólo lectura --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Empresa</label>
                                    <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                        {{ $regulation->company->name ?? '—' }}
                                    </div>
                                </div>

                                {{-- process_type_id --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Tipo de proceso <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        name="process_type_id"
                                        x-model="form.process_type_id"
                                        :class="errors.process_type_id ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                        <option value="">— Seleccionar —</option>
                                        @foreach($processTypes as $pt)
                                            <option value="{{ $pt->id }}">{{ $pt->name }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="errors.process_type_id" x-text="errors.process_type_id" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- document_type --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Tipo de documento <span class="text-red-500">*</span>
                                    </label>
                                    <select
                                        name="document_type"
                                        x-model="form.document_type"
                                        :class="errors.document_type ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                        <option value="">— Seleccionar —</option>
                                        @foreach($documentTypes as $dt)
                                            <option value="{{ $dt }}">{{ $dt }}</option>
                                        @endforeach
                                    </select>
                                    <p x-show="errors.document_type" x-text="errors.document_type" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- nombre --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Nombre del documento <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="nombre"
                                        x-model="form.nombre"
                                        :class="errors.nombre ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                    <p x-show="errors.nombre" x-text="errors.nombre" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- codigo --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">Clave única del documento</p>
                                    <input
                                        type="text"
                                        name="codigo"
                                        x-model="form.codigo"
                                        :class="errors.codigo ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                    <p x-show="errors.codigo" x-text="errors.codigo" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- quien_elabora --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Quién elabora <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="quien_elabora"
                                        x-model="form.quien_elabora"
                                        :class="errors.quien_elabora ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                    <p x-show="errors.quien_elabora" x-text="errors.quien_elabora" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- quien_aprueba --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Quién aprueba <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="text"
                                        name="quien_aprueba"
                                        x-model="form.quien_aprueba"
                                        :class="errors.quien_aprueba ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                    <p x-show="errors.quien_aprueba" x-text="errors.quien_aprueba" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                {{-- fecha_vigencia --}}
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fecha de vigencia <span class="text-red-500">*</span>
                                    </label>
                                    <input
                                        type="date"
                                        name="fecha_vigencia"
                                        x-model="form.fecha_vigencia"
                                        :class="errors.fecha_vigencia ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"
                                    >
                                    <p x-show="errors.fecha_vigencia" x-text="errors.fecha_vigencia" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ==================== BLOQUE 2: Objetivo y Alcance ==================== --}}
                    <div x-show="currentBlock === 2" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 2 — Objetivo y Alcance</h2>
                                <p class="text-sm text-white mt-0.5">Define el propósito y los límites del proceso</p>
                            </div>
                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Problema que resuelve <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Qué falla hoy? ¿Qué se pierde o confunde?</p>
                                    <textarea name="problema_resuelve" x-model="form.problema_resuelve" rows="4"
                                        :class="errors.problema_resuelve ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.problema_resuelve" x-text="errors.problema_resuelve" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Resultado esperado <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Cómo se ve el éxito cuando el procedimiento funciona bien?</p>
                                    <textarea name="resultado_esperado" x-model="form.resultado_esperado" rows="4"
                                        :class="errors.resultado_esperado ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.resultado_esperado" x-text="errors.resultado_esperado" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Áreas que aplica <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿A quién aplica este documento?</p>
                                    <textarea name="areas_aplica" x-model="form.areas_aplica" rows="4"
                                        :class="errors.areas_aplica ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.areas_aplica" x-text="errors.areas_aplica" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Fuera de alcance <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Qué queda expresamente excluido?</p>
                                    <textarea name="fuera_alcance" x-model="form.fuera_alcance" rows="4"
                                        :class="errors.fuera_alcance ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.fuera_alcance" x-text="errors.fuera_alcance" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ==================== BLOQUE 3: Indicadores ==================== --}}
                    <div x-show="currentBlock === 3" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 3 — Indicadores</h2>
                                <p class="text-sm text-white mt-0.5">Métricas para medir el desempeño del proceso</p>
                            </div>
                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Indicador de proceso <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Qué mides para saber que se ejecuta bien?</p>
                                    <textarea name="indicador_proceso" x-model="form.indicador_proceso" rows="4"
                                        :class="errors.indicador_proceso ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.indicador_proceso" x-text="errors.indicador_proceso" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Indicador de resultado <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Qué mides para saber que el objetivo se logró?</p>
                                    <textarea name="indicador_resultado" x-model="form.indicador_resultado" rows="4"
                                        :class="errors.indicador_resultado ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.indicador_resultado" x-text="errors.indicador_resultado" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Meta / Valor objetivo <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="meta_valor" x-model="form.meta_valor"
                                        :class="errors.meta_valor ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <p x-show="errors.meta_valor" x-text="errors.meta_valor" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Frecuencia de medición <span class="text-red-500">*</span>
                                    </label>
                                    <input type="text" name="frecuencia_medicion" x-model="form.frecuencia_medicion"
                                        :class="errors.frecuencia_medicion ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <p x-show="errors.frecuencia_medicion" x-text="errors.frecuencia_medicion" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ==================== BLOQUE 4: Actividades ==================== --}}
                    <div x-show="currentBlock === 4" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 4 — Actividades</h2>
                                <p class="text-sm text-white mt-0.5">Desglose operativo del proceso</p>
                            </div>
                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        ¿Qué lo detona? <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">¿Qué evento o condición inicia el proceso?</p>
                                    <textarea name="que_detona" x-model="form.que_detona" rows="4"
                                        :class="errors.que_detona ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.que_detona" x-text="errors.que_detona" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Lista de actividades <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">Una por línea numerada. Indica quién ejecuta + qué hace + con qué herramienta.</p>
                                    <textarea name="lista_actividades" x-model="form.lista_actividades" rows="6"
                                        :class="errors.lista_actividades ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.lista_actividades" x-text="errors.lista_actividades" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Áreas que ejecutan <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="areas_ejecutan" x-model="form.areas_ejecutan" rows="4"
                                        :class="errors.areas_ejecutan ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.areas_ejecutan" x-text="errors.areas_ejecutan" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Decisiones y puntos de control <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="decisiones_control" x-model="form.decisiones_control" rows="4"
                                        :class="errors.decisiones_control ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.decisiones_control" x-text="errors.decisiones_control" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Documentos y herramientas usados <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="documentos_usados" x-model="form.documentos_usados" rows="4"
                                        :class="errors.documentos_usados ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.documentos_usados" x-text="errors.documentos_usados" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Resultado / Entregable <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="resultado_entregable" x-model="form.resultado_entregable" rows="4"
                                        :class="errors.resultado_entregable ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.resultado_entregable" x-text="errors.resultado_entregable" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ==================== BLOQUE 5: Mapa de Proceso ==================== --}}
                    <div x-show="currentBlock === 5" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 5 — Mapa de Proceso</h2>
                                <p class="text-sm text-white mt-0.5">Relaciones y actores del proceso</p>
                            </div>
                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Áreas y roles del mapa <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">Actores que participan en el proceso</p>
                                    <input type="text" name="areas_roles_mapa" x-model="form.areas_roles_mapa"
                                        :class="errors.areas_roles_mapa ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600">
                                    <p x-show="errors.areas_roles_mapa" x-text="errors.areas_roles_mapa" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Procedimientos relacionados <span class="text-red-500">*</span>
                                    </label>
                                    <p class="text-xs text-gray-400 mt-0.5 mb-1">Formato: CÓDIGO | Nombre (uno por línea)</p>
                                    <textarea name="procedimientos_relacionados" x-model="form.procedimientos_relacionados" rows="4"
                                        :class="errors.procedimientos_relacionados ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.procedimientos_relacionados" x-text="errors.procedimientos_relacionados" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Proveedores y clientes del proceso <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="proveedores_clientes" x-model="form.proveedores_clientes" rows="4"
                                        :class="errors.proveedores_clientes ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.proveedores_clientes" x-text="errors.proveedores_clientes" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ==================== BLOQUE 6: Contexto ==================== --}}
                    <div x-show="currentBlock === 6" x-transition.opacity>
                        <div class="bg-white border rounded-xl shadow-sm overflow-hidden">
                            <div class="px-6 py-4 border-b" style="background-color: #1A428A;">
                                <h2 class="text-lg font-semibold text-white">Bloque 6 — Contexto</h2>
                                <p class="text-sm text-white mt-0.5">Glosario, riesgos y normativa aplicable</p>
                            </div>
                            <div class="p-6 space-y-5">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Términos y abreviaturas <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="terminos_abreviaturas" x-model="form.terminos_abreviaturas" rows="4"
                                        :class="errors.terminos_abreviaturas ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.terminos_abreviaturas" x-text="errors.terminos_abreviaturas" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Riesgos y errores frecuentes <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="riesgos_errores" x-model="form.riesgos_errores" rows="4"
                                        :class="errors.riesgos_errores ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.riesgos_errores" x-text="errors.riesgos_errores" class="text-sm text-red-600 mt-1"></p>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">
                                        Requerimientos normativos <span class="text-red-500">*</span>
                                    </label>
                                    <textarea name="requerimientos_normativos" x-model="form.requerimientos_normativos" rows="4"
                                        :class="errors.requerimientos_normativos ? 'border-red-400' : 'border-gray-300'"
                                        class="w-full rounded-md text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                                    <p x-show="errors.requerimientos_normativos" x-text="errors.requerimientos_normativos" class="text-sm text-red-600 mt-1"></p>
                                </div>

                            </div>
                        </div>
                    </div>

                    {{-- ===== NAVIGATION BUTTONS ===== --}}
                    <div class="mt-6 flex items-center justify-between">

                        <template x-if="currentBlock === 1">
                            <a href="{{ route('processes.show', $regulation) }}"
                               class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Cancelar
                            </a>
                        </template>
                        <template x-if="currentBlock > 1">
                            <button type="button" @click="prev()"
                                    class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm font-semibold text-gray-700 hover:bg-gray-50">
                                Anterior
                            </button>
                        </template>

                        <div class="flex items-center gap-3">
                            <span class="text-sm text-gray-500 lg:hidden" x-text="'Bloque ' + currentBlock + ' de 6'"></span>

                            <template x-if="currentBlock < 6">
                                <button type="button" @click="next()"
                                        class="px-6 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                    Siguiente
                                </button>
                            </template>
                            <template x-if="currentBlock === 6">
                                <button type="button" @click="submitWizard()"
                                        class="px-6 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                    Generar Vista Previa
                                </button>
                            </template>
                        </div>
                    </div>

                </form>
            </div>
        </div>
    </div>

    <script>
    function wizardApp(initialForm) {
        return {
            currentBlock: 1,

            submitting: false,

            form: initialForm,

            errors: {},

            blockFieldMap: {
                1: ['process_type_id', 'document_type', 'nombre', 'codigo', 'quien_elabora', 'quien_aprueba', 'fecha_vigencia'],
                2: ['problema_resuelve', 'resultado_esperado', 'areas_aplica', 'fuera_alcance'],
                3: ['indicador_proceso', 'indicador_resultado', 'meta_valor', 'frecuencia_medicion'],
                4: ['que_detona', 'lista_actividades', 'areas_ejecutan', 'decisiones_control', 'documentos_usados', 'resultado_entregable'],
                5: ['areas_roles_mapa', 'procedimientos_relacionados', 'proveedores_clientes'],
                6: ['terminos_abreviaturas', 'riesgos_errores', 'requerimientos_normativos'],
            },

            blockNames: {
                1: 'Identificación',
                2: 'Objetivo y Alcance',
                3: 'Indicadores',
                4: 'Actividades',
                5: 'Mapa de Proceso',
                6: 'Contexto',
            },

            init() {},

            blockColor(b) { return '#1A428A'; },
            blockColorBg(b) { return '#eff6ff'; },
            blockName(b) { return this.blockNames[b] || ''; },

            blockProgress(b) {
                const fields = this.blockFieldMap[b] || [];
                const filled = fields.filter(f => String(this.form[f] || '').trim() !== '').length;
                return { filled, total: fields.length };
            },

            isBlockComplete(b) {
                const p = this.blockProgress(b);
                return p.total > 0 && p.filled === p.total;
            },

            overallProgress() {
                let totalFields = 0, filledFields = 0;
                for (let b = 1; b <= 6; b++) {
                    const p = this.blockProgress(b);
                    totalFields += p.total;
                    filledFields += p.filled;
                }
                return totalFields === 0 ? 0 : Math.round((filledFields / totalFields) * 100);
            },

            validateBlock(blockNum) {
                const skipOptional = ['codigo'];
                const fields = (this.blockFieldMap[blockNum] || []).filter(f => !skipOptional.includes(f));
                let valid = true;
                fields.forEach(field => {
                    if (String(this.form[field] || '').trim() === '') {
                        this.errors[field] = 'Este campo es obligatorio.';
                        valid = false;
                    } else {
                        delete this.errors[field];
                    }
                });
                return valid;
            },

            next() {
                if (this.currentBlock < 6) {
                    this.currentBlock++;
                    this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                }
            },

            prev() {
                if (this.currentBlock > 1) {
                    this.currentBlock--;
                    this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                }
            },

            goToBlock(b) {
                this.currentBlock = b;
                this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
            },

            submitWizard() {
                let firstInvalid = 0;
                for (let b = 1; b <= 6; b++) {
                    if (!this.validateBlock(b) && firstInvalid === 0) firstInvalid = b;
                }
                if (firstInvalid > 0) {
                    this.currentBlock = firstInvalid;
                    this.$nextTick(() => window.scrollTo({ top: 0, behavior: 'smooth' }));
                    return;
                }
                this.submitting = true;
                document.getElementById('wizard-form').submit();
            },
        };
    }
    </script>

</x-layouts.vigia>
