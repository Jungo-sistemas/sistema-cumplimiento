<x-layouts.vigia title="Mis aprobaciones pendientes">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Mis aprobaciones</span>
    </x-slot>

    @php
        $previewBlocks = [
            ['title' => 'Objetivo y Alcance', 'fields' => [
                'problema_resuelve'  => 'Problema que resuelve',
                'resultado_esperado' => 'Resultado esperado',
                'areas_aplica'       => 'Áreas a las que aplica',
                'fuera_alcance'      => 'Fuera de alcance',
            ]],
            ['title' => 'Indicadores', 'fields' => [
                'indicador_proceso'   => 'Indicador de proceso',
                'indicador_resultado' => 'Indicador de resultado',
                'meta_valor'          => 'Meta / Valor',
                'frecuencia_medicion' => 'Frecuencia de medición',
            ]],
            ['title' => 'Actividades', 'fields' => [
                'que_detona'           => '¿Qué detona el proceso?',
                'lista_actividades'    => 'Lista de actividades',
                'areas_ejecutan'       => 'Áreas que ejecutan',
                'decisiones_control'   => 'Decisiones de control',
                'documentos_usados'    => 'Documentos usados',
                'resultado_entregable' => 'Resultado / Entregable',
            ]],
            ['title' => 'Mapa de Proceso', 'fields' => [
                'areas_roles_mapa'             => 'Áreas y roles',
                'procedimientos_relacionados'  => 'Procedimientos relacionados',
                'proveedores_clientes'         => 'Proveedores / Clientes',
            ]],
            ['title' => 'Contexto', 'fields' => [
                'terminos_abreviaturas'    => 'Términos y abreviaturas',
                'riesgos_errores'          => 'Riesgos y errores',
                'requerimientos_normativos'=> 'Requerimientos normativos',
            ]],
        ];
    @endphp

    <div class="max-w-4xl mx-auto space-y-6">

        {{-- Header --}}
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-[#1A428A]">Mis aprobaciones pendientes</h1>
                <p class="text-sm text-gray-500 mt-0.5">
                    {{ $pendingApprovals->count() === 0 ? 'No tienes aprobaciones pendientes.' : $pendingApprovals->count() . ' documento(s) esperan tu decisión.' }}
                </p>
            </div>
        </div>

        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        @if($pendingApprovals->isEmpty())
            <div class="bg-white rounded-xl border shadow-sm p-12 text-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto text-gray-300 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
                <p class="text-gray-500 font-medium">Todo al día</p>
                <p class="text-gray-400 text-sm mt-1">No tienes documentos pendientes de aprobación.</p>
            </div>
        @else
            @foreach($pendingApprovals as $approval)
                @php
                    $regulation = $approval->regulation;
                    $details    = $regulation->details ?? [];
                    $totalSteps = count(\App\Services\ApprovalFlowService::getFlowSteps($regulation->impact_level));
                @endphp

                <div x-data="{ showPreview: false, showReject: false, showConfirm: false }" class="bg-white rounded-xl border shadow-sm overflow-hidden">

                    {{-- Cabecera --}}
                    <div class="px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap" style="background: linear-gradient(135deg, #1A428A 0%, #1e5096 100%);">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($regulation->code)
                                    <span class="font-mono text-xs bg-white/20 text-white px-2 py-0.5 rounded">{{ $regulation->code }}</span>
                                @endif
                                <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded">{{ $regulation->document_type ?? 'Documento' }}</span>
                                <span class="text-xs bg-yellow-400/90 text-yellow-900 font-semibold px-2 py-0.5 rounded">
                                    Paso {{ $approval->step_number }}{{ $totalSteps > 1 ? ' de ' . $totalSteps : '' }}
                                </span>
                                @if($approval->requires_all)
                                    <span class="text-xs bg-white/15 text-white/80 px-2 py-0.5 rounded">Requiere unanimidad</span>
                                @endif
                            </div>
                            <h2 class="text-lg font-bold text-white mt-1.5 leading-snug">{{ $regulation->name }}</h2>
                        </div>
                        <div class="shrink-0">
                            <span class="text-xs text-white/60">Recibido</span>
                            <p class="text-sm text-white font-medium">{{ $approval->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Metadatos --}}
                    <div class="px-6 py-5">
                        <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-6 gap-y-3 text-sm">
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Empresa</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $regulation->company->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Proceso</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $regulation->processType->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Nivel de impacto</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $regulation->impactLevelLabel() }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Elaborado por</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $details['quien_elabora'] ?? $regulation->creator?->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Aprueba</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $details['quien_aprueba'] ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Vigencia</p>
                                <p class="text-gray-800 font-medium mt-0.5">
                                    @if(!empty($details['fecha_vigencia']))
                                        {{ \Carbon\Carbon::parse($details['fecha_vigencia'])->format('d/m/Y') }}
                                    @else
                                        —
                                    @endif
                                </p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Tu puesto</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $approval->jobPosition->name ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Estado del flujo</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $regulation->approvalStatusLabel() }}</p>
                            </div>
                        </div>

                        {{-- Botón para desplegar vista previa --}}
                        <button type="button"
                                @click="showPreview = !showPreview"
                                class="mt-4 w-full flex items-center justify-between px-4 py-2.5 rounded-lg border border-dashed border-[#1A428A]/40 bg-blue-50/50 text-[#1A428A] text-sm font-medium hover:bg-blue-50 transition-colors">
                            <span x-text="showPreview ? 'Ocultar contenido del documento' : 'Ver contenido del documento'"></span>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 transition-transform duration-200" :class="showPreview ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Vista previa colapsable del contenido --}}
                    <div x-show="showPreview"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0 -translate-y-1"
                         x-transition:enter-end="opacity-100 translate-y-0"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100 translate-y-0"
                         x-transition:leave-end="opacity-0 -translate-y-1"
                         class="px-6 pb-5 space-y-5 border-t pt-5">

                        @foreach($previewBlocks as $block)
                            @php
                                $filledFields = collect($block['fields'])->filter(fn($label, $key) => !empty($details[$key]));
                            @endphp
                            @if($filledFields->isNotEmpty())
                                <div>
                                    <h3 class="text-xs font-semibold text-[#1A428A] uppercase tracking-widest mb-3 flex items-center gap-2">
                                        <span class="inline-block w-5 h-px bg-[#1A428A]/30"></span>
                                        {{ $block['title'] }}
                                        <span class="inline-block flex-1 h-px bg-[#1A428A]/30"></span>
                                    </h3>
                                    <div class="space-y-3">
                                        @foreach($filledFields as $key => $label)
                                            <div class="bg-gray-50 rounded-lg px-4 py-3">
                                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">{{ $label }}</p>
                                                <p class="text-sm text-gray-800 leading-relaxed whitespace-pre-line">{{ $details[$key] }}</p>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        @if(collect($previewBlocks)->every(fn($b) => collect($b['fields'])->every(fn($l, $k) => empty($details[$k]))))
                            <p class="text-sm text-gray-400 italic text-center py-4">Este documento no tiene contenido adicional registrado.</p>
                        @endif
                    </div>

                    {{-- Formulario de rechazo --}}
                    <div x-show="showReject" x-transition.opacity class="px-6 pb-4">
                        <form method="POST" action="{{ route('processes.reject', $regulation) }}"
                              @submit.prevent="if ($el.querySelector('textarea').value.trim() === '') { $el.querySelector('textarea').focus(); return; } $el.submit();">
                            @csrf
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                                <label class="block text-sm font-semibold text-red-700 mb-2">
                                    Motivo de rechazo <span class="text-red-500">*</span>
                                </label>
                                <textarea name="comments" rows="3" required
                                          placeholder="Describe el motivo del rechazo..."
                                          class="w-full rounded-md border-red-200 text-sm focus:border-red-400 focus:ring-red-400 resize-none"></textarea>
                                <div class="flex items-center gap-2 mt-3">
                                    <button type="submit" class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                        Confirmar rechazo
                                    </button>
                                    <button type="button" @click="showReject = false"
                                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Footer: acciones --}}
                    <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between gap-3 flex-wrap">

                        <a href="{{ route('processes.show', $regulation) }}"
                           target="_blank"
                           class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                            </svg>
                            Ver ficha completa
                        </a>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="showReject = !showReject"
                                    :class="showReject ? 'bg-red-50 border-red-300 text-red-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-red-50 hover:border-red-300 hover:text-red-700'"
                                    class="px-4 py-2 rounded-lg border text-sm font-semibold transition-colors">
                                Rechazar
                            </button>

                            <button type="button"
                                    @click="showConfirm = true"
                                    class="px-5 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d] shadow-sm">
                                Aprobar
                            </button>

                            {{-- Modal de confirmación de aprobación --}}
                            <div x-show="showConfirm"
                                 x-transition.opacity
                                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                 style="display:none;">
                                <div class="absolute inset-0 bg-black/40" @click="showConfirm = false"></div>
                                <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 mx-auto mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-[#1A428A]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-bold text-gray-900 mb-1">Confirmar aprobación</h3>
                                    <p class="text-sm text-gray-500 mb-6">
                                        ¿Aprobar <span class="font-semibold text-gray-800">«{{ $regulation->name }}»</span>?<br>
                                        Esta acción no se puede deshacer.
                                    </p>
                                    <div class="flex gap-3">
                                        <button type="button"
                                                @click="showConfirm = false"
                                                class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                            Cancelar
                                        </button>
                                        <form method="POST" action="{{ route('processes.approve', $regulation) }}" class="flex-1">
                                            @csrf
                                            <button type="submit"
                                                    class="w-full px-4 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                                Sí, aprobar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            @endforeach
        @endif

    </div>

</x-layouts.vigia>
