<x-layouts.vigia title="Mis aprobaciones pendientes">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Mis aprobaciones</span>
    </x-slot>

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

        {{-- Mensajes de sesión --}}
        @if(session('success'))
            <div class="rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-700">
                {{ session('success') }}
            </div>
        @endif

        {{-- Empty state --}}
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
                    $regulation  = $approval->regulation;
                    $version     = $regulation->currentVersion;
                    $totalSteps  = count(\App\Services\ApprovalFlowService::getFlowSteps($regulation->impact_level));
                @endphp

                <div x-data="{ showReject: false }" class="bg-white rounded-xl border shadow-sm overflow-hidden">

                    {{-- Cabecera de la tarjeta --}}
                    <div class="px-6 py-4 border-b flex items-start justify-between gap-4 flex-wrap" style="background: linear-gradient(135deg, #1A428A 0%, #1e5096 100%);">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2 flex-wrap">
                                @if($regulation->code)
                                    <span class="font-mono text-xs bg-white/20 text-white px-2 py-0.5 rounded">
                                        {{ $regulation->code }}
                                    </span>
                                @endif
                                <span class="text-xs bg-white/20 text-white px-2 py-0.5 rounded">
                                    {{ $regulation->document_type ?? 'Documento' }}
                                </span>
                                <span class="text-xs bg-yellow-400/90 text-yellow-900 font-semibold px-2 py-0.5 rounded">
                                    Paso {{ $approval->step_number }}{{ $totalSteps > 1 ? ' de ' . $totalSteps : '' }}
                                </span>
                                @if($approval->requires_all)
                                    <span class="text-xs bg-white/15 text-white/80 px-2 py-0.5 rounded">
                                        Requiere unanimidad
                                    </span>
                                @endif
                            </div>
                            <h2 class="text-lg font-bold text-white mt-1.5 leading-snug">
                                {{ $regulation->name }}
                            </h2>
                        </div>
                        <div class="shrink-0">
                            <span class="text-xs text-white/60">Recibido</span>
                            <p class="text-sm text-white font-medium">{{ $approval->created_at->format('d/m/Y H:i') }}</p>
                        </div>
                    </div>

                    {{-- Cuerpo: info del documento --}}
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
                                <p class="text-gray-800 font-medium mt-0.5">
                                    {{ $regulation->details['quien_elabora'] ?? $regulation->creator?->name ?? '—' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Aprueba</p>
                                <p class="text-gray-800 font-medium mt-0.5">
                                    {{ $regulation->details['quien_aprueba'] ?? '—' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Vigencia</p>
                                <p class="text-gray-800 font-medium mt-0.5">
                                    @if(!empty($regulation->details['fecha_vigencia']))
                                        {{ \Carbon\Carbon::parse($regulation->details['fecha_vigencia'])->format('d/m/Y') }}
                                    @elseif($version?->valid_until)
                                        {{ $version->valid_until->format('d/m/Y') }}
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
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Versión</p>
                                <p class="text-gray-800 font-medium mt-0.5">
                                    {{ $version ? 'v' . $version->version_number : 'Sin archivo' }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">Estado del flujo</p>
                                <p class="text-gray-800 font-medium mt-0.5">{{ $regulation->approvalStatusLabel() }}</p>
                            </div>

                        </div>

                        {{-- Objetivo / descripción si existe --}}
                        @if(!empty($regulation->details['problema_resuelve']))
                            <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-100">
                                <p class="text-xs text-gray-400 font-medium uppercase tracking-wide mb-1">Problema que resuelve</p>
                                <p class="text-sm text-gray-700 leading-relaxed line-clamp-3">
                                    {{ $regulation->details['problema_resuelve'] }}
                                </p>
                            </div>
                        @endif
                    </div>

                    {{-- Formulario de rechazo (oculto por defecto) --}}
                    <div x-show="showReject" x-transition.opacity class="px-6 pb-4">
                        <form method="POST" action="{{ route('processes.reject', $regulation) }}"
                              @submit.prevent="
                                  if ($el.querySelector('textarea').value.trim() === '') {
                                      $el.querySelector('textarea').focus();
                                      return;
                                  }
                                  $el.submit();
                              ">
                            @csrf
                            <div class="bg-red-50 border border-red-200 rounded-xl p-4">
                                <label class="block text-sm font-semibold text-red-700 mb-2">
                                    Motivo de rechazo <span class="text-red-500">*</span>
                                </label>
                                <textarea name="comments"
                                          rows="3"
                                          required
                                          placeholder="Describe el motivo del rechazo..."
                                          class="w-full rounded-md border-red-200 text-sm focus:border-red-400 focus:ring-red-400 resize-none"></textarea>
                                <div class="flex items-center gap-2 mt-3">
                                    <button type="submit"
                                            class="px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                        Confirmar rechazo
                                    </button>
                                    <button type="button"
                                            @click="showReject = false"
                                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                                        Cancelar
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>

                    {{-- Footer: acciones --}}
                    <div class="px-6 py-4 bg-gray-50 border-t flex items-center justify-between gap-3 flex-wrap">

                        <div class="flex items-center gap-2">
                            @if($version)
                                <a href="{{ route('regulation-versions.download', $version) }}"
                                   class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                    </svg>
                                    Descargar v{{ $version->version_number }}
                                </a>
                            @else
                                <span class="text-xs text-gray-400 italic">Sin archivo adjunto</span>
                            @endif

                            <a href="{{ route('processes.show', $regulation) }}"
                               target="_blank"
                               class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                </svg>
                                Ver documento
                            </a>
                        </div>

                        <div class="flex items-center gap-2">
                            <button type="button"
                                    @click="showReject = !showReject"
                                    :class="showReject ? 'bg-red-50 border-red-300 text-red-700' : 'bg-white border-gray-300 text-gray-700 hover:bg-red-50 hover:border-red-300 hover:text-red-700'"
                                    class="px-4 py-2 rounded-lg border text-sm font-semibold transition-colors">
                                Rechazar
                            </button>

                            <form method="POST" action="{{ route('processes.approve', $regulation) }}">
                                @csrf
                                <button type="submit"
                                        onclick="return confirm('¿Aprobar «{{ addslashes($regulation->name) }}»?')"
                                        class="px-5 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d] shadow-sm">
                                    Aprobar
                                </button>
                            </form>
                        </div>

                    </div>
                </div>
            @endforeach
        @endif

    </div>

</x-layouts.vigia>
