<x-layouts.vigia :title="'Flujo · ' . $regulation->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-500 hover:underline">Procesos</a>
        <span class="text-gray-400">›</span>
        <a href="{{ route('processes.show', $regulation) }}" class="text-gray-500 hover:underline">
            <x-truncate max="max-w-[260px]">{{ $regulation->name }}</x-truncate>
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Flujo de aprobación</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-4 flex-wrap">
            <div class="min-w-0">
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-xl font-bold text-brand">Flujo de aprobación</h1>
                    @php $apColor = $regulation->approvalStatusColor(); @endphp
                    <span class="inline-flex items-center gap-1.5 text-xs px-3 py-1 rounded border
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
                </div>
                <p class="mt-1 text-sm text-gray-500">
                    {{ $regulation->name }}
                    <span class="mx-1.5 text-gray-300">·</span>
                    Impacto: <span class="font-medium text-gray-700">{{ $regulation->impactLevelLabel() }}</span>
                    @if($regulation->code)
                        <span class="mx-1.5 text-gray-300">·</span>
                        <span class="font-mono text-gray-600">{{ $regulation->code }}</span>
                    @endif
                </p>
            </div>

            <a href="{{ route('processes.show', $regulation) }}"
               class="shrink-0 px-4 py-2 rounded-md border border-brand bg-white text-brand font-semibold text-sm hover:bg-brand-50">
                Volver al documento
            </a>
        </div>

        {{-- Alertas de sesión --}}
        @if(session('success'))
            <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
                {{ session('success') }}
            </div>
        @endif
        @if(session('error'))
            <div class="mt-4 rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                {{ session('error') }}
            </div>
        @endif

        {{-- Alerta: aprobación pendiente del usuario actual --}}
        @if($pendingApprovalForUser)
            <div x-data="{ showApprove: false, showReject: false }" class="mt-6 rounded-xl border border-yellow-200 bg-yellow-50 p-5">
                <p class="text-sm font-semibold text-yellow-800 mb-4">
                    Tienes una aprobación pendiente en este documento — Paso {{ $pendingApprovalForUser->step_number }}
                    ({{ $pendingApprovalForUser->jobPosition->name ?? '' }}).
                </p>

                <div class="flex items-center gap-3 flex-wrap">
                    <button type="button"
                            @click="showApprove = true"
                            class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand-dark">
                        Aprobar
                    </button>
                    <button type="button"
                            @click="showReject = true"
                            class="px-4 py-2 rounded-lg border border-red-300 text-red-600 text-sm font-semibold hover:bg-red-50">
                        Rechazar
                    </button>
                </div>

                {{-- Modal de confirmación de aprobación --}}
                <div x-show="showApprove"
                     x-transition.opacity
                     class="fixed inset-0 z-50 flex items-center justify-center p-4"
                     style="display:none;">
                    <div class="absolute inset-0 bg-black/40" @click="showApprove = false"></div>
                    <div class="relative bg-white rounded-2xl shadow-xl max-w-sm w-full p-6 text-center">
                        <div class="flex items-center justify-center w-12 h-12 rounded-full bg-blue-100 mx-auto mb-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
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
                                    @click="showApprove = false"
                                    class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                Cancelar
                            </button>
                            <form method="POST" action="{{ route('processes.approve', $regulation) }}" class="flex-1">
                                @csrf
                                <button type="submit"
                                        class="w-full px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-brand-dark">
                                    Sí, aprobar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

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
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Motivo del rechazo <span class="text-red-500">*</span>
                                </label>
                                <textarea name="comments"
                                          rows="3"
                                          required
                                          placeholder="Describe el motivo del rechazo para que el creador pueda corregirlo..."
                                          class="w-full rounded-md border-gray-300 text-sm focus:border-red-400 focus:ring-red-400"></textarea>
                                @error('comments')
                                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                            <div class="flex gap-3">
                                <button type="button"
                                        @click="showReject = false"
                                        class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="flex-1 px-4 py-2 rounded-lg bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                    Confirmar rechazo
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        @endif

        {{-- Alerta: rechazado — editar y re-enviar --}}
        @if($regulation->isRejected())
            <div class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-red-700">Documento rechazado</p>
                    <p class="text-xs text-red-500 mt-0.5">
                        Corrige los puntos indicados y re-envía a aprobación para reiniciar el flujo desde el paso 1.
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    @include('processes.partials.edit-version-button', ['regulation' => $regulation, 'currentVersion' => $currentVersion, 'editLock' => $editLock])
                    @if(auth()->user()->isAdmin())
                        <form method="POST" action="{{ route('processes.resubmit', $regulation) }}">
                            @csrf
                            <button type="submit"
                                    onclick="return confirm('¿Re-enviar este documento a aprobación? El flujo se reiniciará desde el paso 1.')"
                                    class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-blue-800">
                                Re-enviar a aprobación
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @endif

        {{-- ===== TABLA DEL FLUJO POR PASO ===== --}}
        <div class="mt-6">
            @if($approvals->isNotEmpty())

                {{-- Tabla --}}
                <div class="border border-gray-200 rounded-xl overflow-hidden">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-600">
                            <tr>
                                <th class="text-left px-5 py-3 font-semibold w-20">Paso</th>
                                <th class="text-left px-5 py-3 font-semibold">Puesto</th>
                                <th class="text-left px-5 py-3 font-semibold">Aprobador</th>
                                <th class="text-left px-5 py-3 font-semibold">Estado</th>
                                <th class="text-left px-5 py-3 font-semibold">Fecha decisión</th>
                                <th class="text-left px-5 py-3 font-semibold">Comentarios</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($approvals as $step => $stepApprovals)
                                @php
                                    $stepApproved = $stepApprovals->every(fn($a) => in_array($a->status, ['approved', 'cancelled']))
                                                    && $stepApprovals->contains('status', 'approved');
                                    $stepRejected = $stepApprovals->contains('status', 'rejected');
                                    $stepPending  = $stepApprovals->contains('status', 'pending');
                                    $isFirst      = $loop->first;
                                @endphp

                                {{-- Fila separadora de paso --}}
                                <tr class="
                                    {{ $stepRejected ? 'bg-red-50' : ($stepApproved ? 'bg-green-50' : ($stepPending ? 'bg-yellow-50' : 'bg-gray-50')) }}
                                    {{ $isFirst ? '' : 'border-t-2 border-gray-200' }}">
                                    <td colspan="6" class="px-5 py-2.5">
                                        <div class="flex items-center gap-2">
                                            @if($stepRejected)
                                                <span class="h-5 w-5 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                                                    <svg class="h-3 w-3 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </span>
                                                <span class="text-xs font-bold text-red-700 uppercase tracking-wide">
                                                    Paso {{ $step }} — Rechazado
                                                </span>
                                            @elseif($stepApproved)
                                                <span class="h-5 w-5 rounded-full bg-green-100 flex items-center justify-center shrink-0">
                                                    <svg class="h-3 w-3 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                    </svg>
                                                </span>
                                                <span class="text-xs font-bold text-green-700 uppercase tracking-wide">
                                                    Paso {{ $step }} — Completado
                                                </span>
                                            @elseif($stepPending)
                                                <span class="h-5 w-5 rounded-full bg-yellow-100 flex items-center justify-center shrink-0">
                                                    <span class="h-2 w-2 rounded-full bg-yellow-400"></span>
                                                </span>
                                                <span class="text-xs font-bold text-yellow-700 uppercase tracking-wide">
                                                    Paso {{ $step }} — En espera
                                                </span>
                                            @else
                                                <span class="h-5 w-5 rounded-full bg-gray-200 flex items-center justify-center shrink-0">
                                                    <span class="h-2 w-2 rounded-full bg-gray-400"></span>
                                                </span>
                                                <span class="text-xs font-bold text-gray-500 uppercase tracking-wide">
                                                    Paso {{ $step }}
                                                </span>
                                            @endif

                                            @if(!$stepApprovals->first()?->requires_all && $stepApprovals->count() > 1)
                                                <span class="text-xs text-gray-400">(basta con uno)</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>

                                {{-- Filas de aprobadores del paso --}}
                                @foreach($stepApprovals as $ap)
                                    <tr class="border-t border-gray-100 hover:bg-gray-50/50">
                                        <td class="px-5 py-3 text-center">
                                            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-500">
                                                {{ $step }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-3 text-gray-600 whitespace-nowrap">
                                            {{ $ap->jobPosition->name ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3">
                                            <div class="font-medium text-gray-800">{{ $ap->user->name ?? '—' }}</div>
                                            <div class="text-xs text-gray-400">{{ $ap->user->email ?? '' }}</div>
                                        </td>
                                        <td class="px-5 py-3 whitespace-nowrap">
                                            @if($ap->status === 'approved')
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700">
                                                    <span class="h-2 w-2 rounded-full bg-green-500"></span>Aprobado
                                                </span>
                                            @elseif($ap->status === 'rejected')
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-700">
                                                    <span class="h-2 w-2 rounded-full bg-red-500"></span>Rechazado
                                                </span>
                                            @elseif($ap->status === 'cancelled')
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                                                    <span class="h-2 w-2 rounded-full bg-gray-300"></span>Cancelado
                                                </span>
                                            @elseif($ap->status === 'waiting')
                                                <span class="inline-flex items-center gap-1.5 text-xs text-gray-500" title="Todavía no le toca — va después de los anteriores en este mismo paso">
                                                    <span class="h-2 w-2 rounded-full bg-gray-300"></span>En fila (turno #{{ $ap->sequence_order + 1 }})
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-yellow-700">
                                                    <span class="h-2 w-2 rounded-full bg-yellow-400 animate-pulse"></span>Pendiente
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-5 py-3 text-gray-500 whitespace-nowrap text-xs">
                                            {{ $ap->decided_at?->format('d/m/Y H:i') ?? '—' }}
                                        </td>
                                        <td class="px-5 py-3 text-gray-600 text-xs max-w-[240px]">
                                            {{ $ap->comments ?: '—' }}
                                        </td>
                                    </tr>
                                @endforeach
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <p class="mt-3 text-xs text-gray-400">
                    {{ $approvals->flatten()->count() }} registro(s) en {{ $approvals->count() }} paso(s)
                </p>

            @else
                <div class="rounded-xl border border-dashed border-gray-300 px-6 py-10 text-center">
                    <p class="text-sm text-gray-500">
                        No hay registros de aprobación todavía.
                    </p>
                    <p class="mt-1 text-xs text-gray-400">
                        Verifica que existan usuarios asignados a los puestos requeridos por el flujo.
                    </p>
                </div>
            @endif
        </div>

    </div>

</x-layouts.vigia>
