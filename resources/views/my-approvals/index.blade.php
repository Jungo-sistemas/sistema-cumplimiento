<x-layouts.vigia title="Mis aprobaciones pendientes">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Mis aprobaciones</span>
    </x-slot>

    <div class="max-w-4xl mx-auto space-y-6" data-tour="mis-aprobaciones-content">

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
                    $currentVersion  = $regulation->currentVersion;
                    $previousVersion = $currentVersion?->previousVersion();
                    $changedSectionsAll = app(\App\Services\RegulationChangeDiffService::class)
                        ->diff($previousVersion?->body_html, $currentVersion?->body_html);
                    // Solo el último cambio (la sección más reciente), no la lista completa —
                    // con varias secciones modificadas en la misma edición esto podía salir muy
                    // largo; decisión explícita del usuario, se sigue calculando todo pero solo
                    // se muestra 1 fila.
                    $changedSections = $changedSectionsAll !== [] ? [end($changedSectionsAll)] : [];
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

                        {{-- Qué cambió en esta versión --}}
                        @if($currentVersion && ($currentVersion->change_description || $currentVersion->change_justification || count($changedSections) > 0))
                            <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3">
                                <p class="text-xs font-semibold text-amber-800 uppercase tracking-wide mb-1.5">
                                    Qué cambió en esta versión
                                </p>
                                @if($currentVersion->change_description)
                                    <p class="text-sm text-gray-700">{{ $currentVersion->change_description }}</p>
                                @endif
                                @if($currentVersion->change_justification)
                                    <p class="text-sm text-gray-600 mt-1">
                                        <span class="font-medium">Justificación:</span> {{ $currentVersion->change_justification }}
                                    </p>
                                @endif

                                @if(!empty($currentVersion->changes_table['rows']))
                                    <div class="mt-3 border border-amber-200 rounded-lg overflow-hidden">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-amber-100 text-amber-800">
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2 w-10">#</th>
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2 w-2/5">Modificación solicitada</th>
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2">Texto ya incorporado al procedimiento</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-amber-100 bg-white">
                                                @foreach($currentVersion->changes_table['rows'] as $i => $row)
                                                    <tr>
                                                        <td class="px-3 py-2 font-semibold text-amber-800 align-top">{{ $i + 1 }}</td>
                                                        <td class="px-3 py-2 text-gray-700 align-top whitespace-pre-line">{{ $row['modificacion'] }}</td>
                                                        <td class="px-3 py-2 text-gray-800 align-top whitespace-pre-line">{{ $row['texto_incorporado'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                @if(count($changedSections) > 0)
                                    <div class="mt-3 border border-amber-200 rounded-lg overflow-hidden">
                                        <table class="w-full text-xs">
                                            <thead>
                                                <tr class="bg-amber-100 text-amber-800">
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2 w-1/5">Sección</th>
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2">Antes</th>
                                                    <th class="text-left font-semibold uppercase tracking-wide px-3 py-2">Después</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-amber-100 bg-white">
                                                @foreach($changedSections as $section)
                                                    <tr>
                                                        <td class="px-3 py-2 font-semibold text-amber-800 align-top">{{ $section['title'] }}</td>
                                                        <td class="px-3 py-2 text-gray-600 align-top whitespace-pre-line">{{ $section['before'] }}</td>
                                                        <td class="px-3 py-2 text-gray-800 align-top whitespace-pre-line">{{ $section['after'] }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif

                                <div class="flex items-center gap-4 mt-3">
                                    @if($previousVersion)
                                        <a href="{{ route('regulation-versions.preview', $previousVersion) }}"
                                           target="_blank"
                                           class="text-xs font-semibold text-amber-800 hover:underline">
                                            Ver versión anterior completa
                                        </a>
                                    @endif
                                    <a href="{{ route('regulation-versions.preview', $currentVersion) }}"
                                       target="_blank"
                                       class="text-xs font-semibold text-amber-800 hover:underline">
                                        Ver esta versión completa
                                    </a>
                                </div>
                            </div>
                        @endif

                        {{-- Botón para desplegar vista previa --}}
                        <button type="button"
                                @click="showPreview = !showPreview"
                                class="mt-4 w-full flex items-center justify-between px-4 py-2.5 rounded-lg border border-dashed border-[#1A428A]/40 bg-blue-50/50 text-[#1A428A] text-sm font-medium hover:bg-blue-50 transition-colors">
                            <span x-text="showPreview ? 'Ocultar resumen de cambios' : 'Ver resumen de cambios'"></span>
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

                        @if($regulation->change_summary_path)
                            {{-- Síntesis de cambios que se adjuntó al establecer el flujo --}}
                            <div>
                                <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $regulation->change_summary_original_name }}</p>
                                        <p class="text-xs text-gray-500">
                                            Subido por {{ $regulation->changeSummaryUploader?->name ?? '—' }}
                                            · {{ $regulation->change_summary_uploaded_at?->format('d/m/Y H:i') }}
                                        </p>
                                    </div>
                                    <a href="{{ route('processes.changeSummary.show', $regulation) }}"
                                       target="_blank"
                                       class="text-xs font-semibold text-[#1A428A] hover:underline shrink-0">
                                        Abrir en pestaña nueva
                                    </a>
                                </div>
                                <template x-if="showPreview">
                                    <iframe src="{{ route('processes.changeSummary.show', $regulation) }}"
                                            title="Resumen de cambios"
                                            class="w-full h-[600px] rounded-lg border bg-gray-100"></iframe>
                                </template>
                            </div>
                        @else
                            {{-- Si no se adjuntó síntesis al establecer el flujo, se muestra el registro más
                                 reciente del "Historial de revisiones y cambios" de la ficha del proceso. --}}
                            <div>
                                <p class="text-xs text-gray-500 mb-2">
                                    Aún no hay un documento de resumen de cambios. Se muestra el último registro del historial de revisiones.
                                </p>
                                @if($currentVersion)
                                    <div class="overflow-x-auto">
                                        <table class="w-full border-collapse text-sm">
                                            <thead>
                                                <tr class="bg-[#e8eef8]">
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800 w-16">Versión</th>
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800 w-24">Fecha</th>
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Elaboró</th>
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Descripción del cambio</th>
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Justificación</th>
                                                    <th class="border border-gray-300 px-3 py-2 text-left font-semibold text-gray-800">Aprobó</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr class="bg-white">
                                                    <td class="border border-gray-300 px-3 py-2 text-center font-medium">{{ str_pad($currentVersion->version_number, 2, '0', STR_PAD_LEFT) }}</td>
                                                    <td class="border border-gray-300 px-3 py-2">{{ $currentVersion->issued_at?->format('d/m/Y') ?? $currentVersion->created_at->format('d/m/Y') }}</td>
                                                    <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $currentVersion->uploader?->name ?? $details['quien_elabora'] ?? '—' }}</td>
                                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $currentVersion->change_description ?: ($currentVersion->version_number == 1 ? 'Creación inicial del documento' : '—') }}</td>
                                                    <td class="border border-gray-300 px-3 py-2 text-gray-700">{{ $currentVersion->change_justification ?: '—' }}</td>
                                                    <td class="border border-gray-300 px-3 py-2 text-gray-600">{{ $details['quien_aprueba'] ?? '—' }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="text-sm text-gray-400 italic text-center py-4">Este documento no tiene versiones registradas.</p>
                                @endif
                            </div>
                        @endif

                    </div>

                    {{-- Footer: acciones --}}
                    <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between gap-3 flex-wrap">

                        @if($currentVersion)
                            <a href="{{ route('regulation-versions.preview', $currentVersion) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                Vista previa
                            </a>
                        @else
                            <span></span>
                        @endif

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="showReject = true"
                                    class="px-4 py-2 rounded-lg border bg-white border-gray-300 text-gray-700 hover:bg-red-50 hover:border-red-300 hover:text-red-700 text-sm font-semibold transition-colors">
                                Rechazar
                            </button>

                            {{-- Modal de confirmación de rechazo --}}
                            <div x-show="showReject"
                                 x-transition.opacity
                                 class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                 style="display:none;">
                                <div class="absolute inset-0 bg-black/40" @click="showReject = false"></div>
                                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6">
                                    <div class="flex items-center justify-center w-12 h-12 rounded-full bg-red-100 mx-auto mb-4">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </div>
                                    <h3 class="text-base font-bold text-gray-900 mb-1 text-center">Confirmar rechazo</h3>
                                    <p class="text-sm text-gray-500 mb-4 text-center">
                                        ¿Rechazar <span class="font-semibold text-gray-800">«{{ $regulation->name }}»</span>?
                                    </p>
                                    <form method="POST" action="{{ route('processes.reject', $regulation) }}" class="space-y-3">
                                        @csrf
                                        <div>
                                            <label class="block text-sm font-semibold text-red-700 mb-2">
                                                Motivo de rechazo <span class="text-red-500">*</span>
                                            </label>
                                            <textarea name="comments" rows="3" required
                                                      placeholder="Describe el motivo del rechazo..."
                                                      class="w-full rounded-md border-red-200 text-sm focus:border-red-400 focus:ring-red-400 resize-none"></textarea>
                                        </div>
                                        <div class="flex gap-3">
                                            <button type="button" @click="showReject = false"
                                                    class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                                                Cancelar
                                            </button>
                                            <button type="submit" class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                                Confirmar rechazo
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

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
