<x-layouts.vigia title="Solicitud de inversión">

    <x-slot name="breadcrumb">
        <a href="{{ route('investment-requests.index') }}" class="text-gray-600 hover:underline">Solicitud de Inversiones</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">#{{ $investmentRequest->id }}</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div class="space-y-1">
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    {{ $investmentRequest->concept }}
                </h1>

                @if((auth()->user()->hasGroupScope() || auth()->user()->hasMultipleCompanies()) && $investmentRequest->company)
                    <div class="text-sm text-gray-500">
                        Empresa:
                        <span class="font-semibold text-gray-700">{{ $investmentRequest->company->name }}</span>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <span class="inline-flex items-center text-xs px-3 py-1 rounded-full border {{ $investmentRequest->status->badgeClasses() }}">
                        {{ $investmentRequest->status->label() }}
                    </span>

                    <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-700 border-gray-200">
                        Monto: ${{ number_format($investmentRequest->amount) }}
                    </span>

                    @if($investmentRequest->deadline_at)
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-600 border-gray-200">
                            Fecha límite: {{ $investmentRequest->deadline_at->format('d/m/Y') }}
                        </span>
                    @endif

                    @if($investmentRequest->requester)
                        <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-600 border-gray-200">
                            Solicitante: {{ $investmentRequest->requester->name }}
                        </span>
                    @endif
                </div>
            </div>

            <a href="{{ route('investment-requests.index') }}"
               class="shrink-0 px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
        </div>

        {{-- Alerts --}}
        <div class="mt-6 space-y-3">
            @if(session('success') || session('status'))
                <div class="rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
                    {{ session('success') ?? session('status') }}
                </div>
            @endif
            @if($errors->has('comments'))
                <div class="rounded-lg border border-red-200 bg-red-50 p-3 text-red-800 text-sm">
                    {{ $errors->first('comments') }}
                </div>
            @endif
        </div>

        {{-- Flujo de aprobación --}}
        <div class="mt-8 bg-white border rounded-xl overflow-hidden" x-data="{ showReject: {{ $errors->has('comments') ? 'true' : 'false' }}, showConfirm: false }">
            <div class="p-5 border-b">
                <div class="font-semibold text-[#1A428A]">Flujo de aprobación</div>
                <div class="text-sm text-gray-500">Las personas aprueban una tras otra, en este orden.</div>
            </div>

            <div class="p-5">
                @if($investmentRequest->approvals->isEmpty())
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                        Esta solicitud se capturó antes de que existiera el flujo de aprobación.
                    </div>
                @else
                    <ol class="space-y-3">
                        @foreach($investmentRequest->approvals as $approval)
                            @php
                                $dot = match ($approval->status) {
                                    'approved'  => 'bg-green-600 text-white',
                                    'rejected'  => 'bg-red-600 text-white',
                                    'pending'   => 'bg-[#1A428A] text-white ring-4 ring-blue-100',
                                    default     => 'bg-gray-200 text-gray-500',
                                };
                                $label = match ($approval->status) {
                                    'approved'  => 'text-green-700',
                                    'rejected'  => 'text-red-700',
                                    'pending'   => 'text-[#1A428A] font-semibold',
                                    default     => 'text-gray-400',
                                };
                            @endphp
                            <li class="flex items-start gap-3">
                                <span class="h-7 w-7 shrink-0 rounded-full text-xs font-semibold flex items-center justify-center {{ $dot }}">
                                    {{ $approval->sequence_order }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-x-2">
                                        <span class="font-medium text-gray-900">{{ $approval->user?->name ?? 'Usuario eliminado' }}</span>
                                        <span class="text-xs {{ $label }}">{{ $approval->statusLabel() }}</span>
                                        @if($approval->decided_at)
                                            <span class="text-xs text-gray-400">{{ $approval->decided_at->format('d/m/Y H:i') }}</span>
                                        @endif
                                    </div>
                                    @if($approval->comments)
                                        <div class="mt-1 text-sm rounded-md px-3 py-2 {{ $approval->status === 'rejected' ? 'bg-red-50 text-red-800' : 'bg-gray-50 text-gray-700' }}">
                                            {{ $approval->comments }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif

                @if($myTurn)
                    <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4 flex flex-wrap items-center justify-between gap-3">
                        <div class="text-sm text-[#1A428A] font-medium">Es tu turno de aprobar esta solicitud.</div>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showReject = true"
                                    class="px-4 py-2 rounded-md border bg-white border-gray-300 text-gray-700 hover:bg-red-50 hover:border-red-300 hover:text-red-700 text-sm font-semibold">
                                Rechazar
                            </button>
                            <button type="button" @click="showConfirm = true"
                                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                Aprobar
                            </button>
                        </div>
                    </div>

                    {{-- Confirmar aprobación --}}
                    <div x-show="showConfirm" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                        <div class="absolute inset-0 bg-black/40" @click="showConfirm = false"></div>
                        <form method="POST" action="{{ route('investment-requests.approve', $investmentRequest) }}"
                              class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-3">
                            @csrf
                            <h3 class="text-base font-bold text-gray-900">Confirmar aprobación</h3>
                            <p class="text-sm text-gray-500">
                                @if($investmentRequest->approvals->where('status', 'waiting')->isNotEmpty())
                                    Al aprobar, se avisará a la siguiente persona del flujo.
                                @else
                                    Eres la última persona del flujo: al aprobar, la solicitud quedará aprobada.
                                @endif
                            </p>
                            <textarea name="comments" rows="2" placeholder="Comentario (opcional)"
                                      class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600"></textarea>
                            <div class="flex gap-3">
                                <button type="button" @click="showConfirm = false"
                                        class="flex-1 px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="flex-1 px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                    Sí, aprobar
                                </button>
                            </div>
                        </form>
                    </div>

                    {{-- Confirmar rechazo --}}
                    <div x-show="showReject" x-transition.opacity class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                        <div class="absolute inset-0 bg-black/40" @click="showReject = false"></div>
                        <form method="POST" action="{{ route('investment-requests.reject', $investmentRequest) }}"
                              class="relative bg-white rounded-2xl shadow-xl max-w-md w-full p-6 space-y-3">
                            @csrf
                            <h3 class="text-base font-bold text-gray-900">Confirmar rechazo</h3>
                            <p class="text-sm text-gray-500">El flujo se detiene y se avisa a quien capturó la solicitud y al solicitante.</p>
                            <div>
                                <label class="block text-sm font-semibold text-red-700 mb-1">
                                    Motivo de rechazo <span class="text-red-500">*</span>
                                </label>
                                <textarea name="comments" rows="3" required placeholder="Describe el motivo del rechazo..."
                                          class="w-full rounded-md border-red-200 text-sm focus:border-red-400 focus:ring-red-400">{{ old('comments') }}</textarea>
                            </div>
                            <div class="flex gap-3">
                                <button type="button" @click="showReject = false"
                                        class="flex-1 px-4 py-2 rounded-md border border-gray-300 text-gray-700 text-sm font-semibold hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit" class="flex-1 px-4 py-2 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                    Confirmar rechazo
                                </button>
                            </div>
                        </form>
                    </div>
                @endif
            </div>
        </div>

        {{-- Anexo 8 --}}
        <div class="mt-8 bg-white border rounded-xl overflow-hidden">
            <div class="p-5 border-b">
                <div class="font-semibold text-[#1A428A]">Anexo 8</div>
                <div class="text-sm text-gray-500">
                    Documento justificante del gasto solicitado.
                </div>
            </div>

            <div class="p-5">
                @if($investmentRequest->hasFile())
                    <div class="border rounded-xl p-4 flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="font-semibold text-gray-900 truncate">
                                {{ $investmentRequest->original_name ?? basename($investmentRequest->file_path) }}
                            </div>
                            <div class="text-sm text-gray-500 mt-1">
                                <span class="block">Subido por: {{ $investmentRequest->uploader?->name ?? '—' }}</span>
                                <span class="block">{{ $investmentRequest->created_at?->format('d/m/Y H:i') }}</span>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 shrink-0">
                            @if($investmentRequest->isPreviewable())
                                <a href="{{ route('investment-requests.preview', $investmentRequest) }}"
                                   target="_blank"
                                   class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                    Ver
                                </a>
                            @endif
                            <a href="{{ route('investment-requests.download', $investmentRequest) }}"
                               class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                Descargar
                            </a>
                        </div>
                    </div>
                @else
                    <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                        No hay archivo cargado para esta solicitud.
                    </div>
                @endif
            </div>
        </div>

    </div>

</x-layouts.vigia>
