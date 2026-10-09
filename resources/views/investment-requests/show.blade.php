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
                    <span class="inline-flex items-center text-xs px-3 py-1 rounded-full border bg-yellow-50 text-yellow-700 border-yellow-200">
                        {{ $investmentRequest->status->label() }}
                    </span>

                    <span class="inline-flex items-center text-xs px-3 py-1 rounded border bg-gray-50 text-gray-700 border-gray-200">
                        Monto: ${{ number_format($investmentRequest->amount, 2) }}
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
                            <a href="{{ route('investment-requests.preview', $investmentRequest) }}"
                               target="_blank"
                               class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                Ver
                            </a>
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
