<x-layouts.vigia title="Mis aprobaciones de inversión">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Mis aprobaciones de inversión</span>
    </x-slot>

    <h1 class="text-2xl font-semibold text-brand">Mis aprobaciones de inversión</h1>
    <p class="text-sm text-gray-500 mt-1">Anexos 8 donde estás en el flujo de aprobación.</p>

    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- POR APROBAR --}}
    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 flex items-center justify-between">
            <div class="font-semibold text-gray-800">Por aprobar</div>
            @if($pending->isNotEmpty())
                <span class="rounded-full bg-brand text-white text-xs font-semibold px-2.5 py-0.5">{{ $pending->count() }}</span>
            @endif
        </div>

        @if($pending->isEmpty())
            <div class="px-6 py-8 text-center text-sm text-gray-500">Todo al día: no tienes solicitudes por aprobar.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="text-left px-4 py-3">Concepto</th>
                            <th class="text-left px-4 py-3">Empresa</th>
                            <th class="text-left px-4 py-3">Monto</th>
                            <th class="text-left px-4 py-3">Solicitante</th>
                            <th class="text-left px-4 py-3">Fecha límite</th>
                            <th class="text-left px-4 py-3">Apruebas como</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pending as $approval)
                            @php $ir = $approval->investmentRequest; @endphp
                            <tr class="border-t border-gray-100 text-gray-700 hover:bg-gray-50 cursor-pointer"
                                ondblclick="window.location.href='{{ route('investment-requests.show', $ir) }}'">
                                <td class="px-4 py-3"><div class="font-medium text-gray-900 max-w-xs truncate">{{ $ir->concept }}</div></td>
                                <td class="px-4 py-3">{{ $ir->displayCompanyName() ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${{ number_format($ir->amount) }}</td>
                                <td class="px-4 py-3">{{ $ir->requester?->name ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $ir->deadline_at?->format('d/m/Y') ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    {{ $approval->position ?? '—' }}
                                    <span class="text-xs text-gray-400">· paso {{ $approval->sequence_order }} de {{ $ir->approvals->count() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('investment-requests.show', $ir) }}"
                                       class="font-semibold text-blue-600 hover:underline whitespace-nowrap">Revisar →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- YA DECIDIDAS --}}
    <div class="mt-8 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b bg-gray-50 font-semibold text-gray-800">Ya decididas</div>

        @if($decided->isEmpty())
            <div class="px-6 py-6 text-center text-sm text-gray-500">Todavía no has aprobado ni rechazado solicitudes.</div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-200">
                        <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                            <th class="text-left px-4 py-3">Concepto</th>
                            <th class="text-left px-4 py-3">Empresa</th>
                            <th class="text-left px-4 py-3">Monto</th>
                            <th class="text-left px-4 py-3">Tu decisión</th>
                            <th class="text-left px-4 py-3">Estado de la solicitud</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($decided as $approval)
                            @php $ir = $approval->investmentRequest; @endphp
                            <tr class="border-t border-gray-100 text-gray-700 hover:bg-gray-50 cursor-pointer"
                                ondblclick="window.location.href='{{ route('investment-requests.show', $ir) }}'">
                                <td class="px-4 py-3"><div class="font-medium text-gray-900 max-w-xs truncate">{{ $ir->concept }}</div></td>
                                <td class="px-4 py-3">{{ $ir->displayCompanyName() ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">${{ number_format($ir->amount) }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="{{ $approval->status === 'rejected' ? 'text-red-700' : 'text-green-700' }} font-medium">{{ $approval->statusLabel() }}</span>
                                    <span class="text-xs text-gray-400">{{ $approval->decided_at?->format('d/m/Y') }}</span>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $ir->status->badgeClasses() }}">
                                        {{ $ir->status->label() }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('investment-requests.show', $ir) }}"
                                       class="font-semibold text-blue-600 hover:underline whitespace-nowrap">Ver →</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if($decided->hasPages())
                <div class="px-4 py-3 border-t bg-gray-50">{{ $decided->links() }}</div>
            @endif
        @endif
    </div>

</x-layouts.vigia>
