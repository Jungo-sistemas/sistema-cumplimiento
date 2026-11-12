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
            <div x-data="{ showApprove: false, showReject: {{ $errors->has('comments') ? 'true' : 'false' }} }" class="mt-6 rounded-xl border border-yellow-200 bg-yellow-50 p-5">
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
                            @php $rejectMax = \App\Http\Controllers\RegulationApprovalController::REJECT_COMMENTS_MAX; @endphp
                            <div x-data="{ len: {{ mb_strlen((string) old('comments')) }} }">
                                <label class="block text-sm font-medium text-gray-700 mb-1">
                                    Motivo del rechazo <span class="text-red-500">*</span>
                                </label>
                                <textarea name="comments"
                                          rows="5"
                                          required
                                          maxlength="{{ $rejectMax }}"
                                          @input="len = $el.value.length"
                                          placeholder="Describe el motivo del rechazo para que el creador pueda corregirlo..."
                                          class="w-full rounded-md border-gray-300 text-sm focus:border-red-400 focus:ring-red-400">{{ old('comments') }}</textarea>
                                <div class="mt-1 flex justify-between gap-2 text-xs">
                                    <span class="text-red-600">@error('comments'){{ $message }}@enderror</span>
                                    <span :class="len >= {{ $rejectMax }} ? 'text-red-600 font-semibold' : 'text-gray-400'"
                                          x-text="len.toLocaleString('es-MX') + ' / {{ number_format($rejectMax) }}'"></span>
                                </div>
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

        @if($regulation->approval_status === 'approved' && $regulation->direct_approved_at)
            <div class="mt-6 rounded-xl border border-green-200 bg-green-50 px-5 py-4">
                <p class="text-sm font-semibold text-green-700">Aprobado sin flujo</p>
                <p class="text-xs text-green-600 mt-0.5">
                    Marcado como aprobado por {{ $regulation->directApprovedBy?->name ?? '—' }}
                    el {{ $regulation->direct_approved_at->format('d/m/Y H:i') }}, sin pasar por los aprobadores.
                </p>
            </div>
        @endif

        {{-- Alerta: rechazado — editar y re-enviar --}}
        @if($regulation->isRejected())
            <div x-data class="mt-6 flex items-center justify-between gap-4 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
                <div>
                    <p class="text-sm font-semibold text-red-700">Documento rechazado</p>
                    <p class="text-xs text-red-500 mt-0.5">
                        Corrige los puntos indicados y re-envía a aprobación para reiniciar el flujo desde el paso 1.
                    </p>
                </div>
                <div class="shrink-0 flex items-center gap-2">
                    @include('processes.partials.edit-version-button', ['regulation' => $regulation, 'currentVersion' => $currentVersion, 'editLock' => $editLock])
                    @if(auth()->user()->isProcessAdmin())
                        <button type="button" @click="$dispatch('open-resubmit')"
                                class="px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-blue-800">
                            Re-enviar a aprobación
                        </button>
                    @endif
                </div>
            </div>

            {{-- Re-envío al mismo flujo: permite reemplazar la síntesis de cambios antes de reiniciar --}}
            @if(auth()->user()->isProcessAdmin())
                @include('processes.partials.change-evidence-script')
                <div x-data="{
                        open: false,
                        summaryName: '',
                        summaryError: '',
                        pickSummary(event) {
                            const file = event.target.files[0];
                            this.summaryError = '';
                            if (file && file.size > 20 * 1024 * 1024) {
                                this.summaryError = 'El documento no puede pesar más de 20 MB.';
                                this.clearSummary();
                                return;
                            }
                            this.summaryName = file ? file.name : '';
                        },
                        clearSummary() {
                            this.$refs.summaryInput.value = '';
                            this.summaryName = '';
                        },
                     }"
                     @open-resubmit.window="open = true"
                     x-show="open" x-cloak
                     class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 px-4"
                     @keydown.escape.window="open = false">
                    <div class="w-full max-w-md rounded-xl bg-white shadow-xl" @click.outside="open = false">
                        <form method="POST" id="resubmitForm" action="{{ route('processes.resubmit', $regulation) }}" enctype="multipart/form-data" class="p-6">
                            @csrf
                            <h3 class="text-base font-semibold text-gray-800">Re-enviar a aprobación</h3>
                            <p class="text-sm text-gray-500 mt-1 mb-4">
                                El flujo se reiniciará desde el paso 1 con los mismos aprobadores.
                            </p>

                            @if($regulation->change_summary_path)
                                <p class="text-xs text-gray-500 mb-2">
                                    Síntesis actual: <span class="font-semibold text-gray-700">{{ $regulation->change_summary_original_name }}</span>
                                </p>
                            @endif

                            <input type="file" name="change_summary" x-ref="summaryInput" class="hidden"
                                   accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png"
                                   @change="pickSummary($event)">
                            @include('processes.partials.flow-change-summary')
                            @include('processes.partials.flow-change-evidences', [
                                'formId'   => 'resubmitForm',
                                'existing' => \Illuminate\Support\Js::from($regulation->changeEvidences->map(fn ($e) => ['id' => $e->id, 'name' => $e->original_name])),
                            ])

                            <div class="flex gap-3">
                                <button type="button" @click="open = false"
                                        class="flex-1 px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="flex-1 px-4 py-2 rounded-lg bg-brand text-white text-sm font-semibold hover:bg-blue-800">
                                    Re-enviar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            @endif
        @endif

        {{-- Síntesis de cambios adjunta al establecer el flujo: la misma que se ve en "Mis aprobaciones",
             para que el aprobador la consulte también desde el detalle. --}}
        @if($regulation->change_summary_path || $regulation->changeEvidences->isNotEmpty())
            @php $evidenceCount = $regulation->changeEvidences->count(); @endphp
            <div x-data="{ open: {{ $pendingApprovalForUser ? 'true' : 'false' }} }" class="mt-6 rounded-xl border border-gray-200 bg-white">
                <div class="flex items-center justify-between gap-3 flex-wrap px-5 py-4">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800">Síntesis de cambios</p>
                        <p class="text-xs text-gray-500 truncate">
                            {{ $regulation->change_summary_path ? $regulation->change_summary_original_name : 'Sin documento de síntesis' }}
                            @if($evidenceCount)
                                · {{ $evidenceCount }} {{ $evidenceCount === 1 ? 'evidencia' : 'evidencias' }} de apoyo
                            @endif
                        </p>
                    </div>
                    <button type="button" @click="open = !open"
                            class="shrink-0 px-3 py-1.5 rounded-lg border border-brand text-brand text-xs font-semibold hover:bg-brand-50"
                            x-text="open ? 'Ocultar' : 'Ver síntesis'"></button>
                </div>
                <div x-show="open" class="border-t px-5 py-4" @unless($pendingApprovalForUser) style="display:none;" @endunless>
                    @include('processes.partials.change-summary-viewer', ['loadWhen' => 'open'])
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
