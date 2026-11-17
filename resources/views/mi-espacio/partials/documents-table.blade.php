{{-- Lista de procesos de Mi espacio (solo consulta). Variables: $documents, $showCompany, $emptyText. --}}
@php
    $statusColors = [
        'Liberado'       => 'bg-green-100 text-green-800',
        'Obsoleto'       => 'bg-gray-100 text-gray-700',
        'Desactualizado' => 'bg-amber-100 text-amber-800',
    ];
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-8">
    <div class="overflow-x-auto rounded-xl border border-gray-200">
        <table class="w-full text-left">
            <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                <tr>
                    <th class="px-4 py-5">Documento</th>
                    <th class="px-4 py-5">Estado</th>
                    <th class="px-4 py-5 text-right">Versión</th>
                    <th class="px-4 py-5"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @forelse($documents as $document)
                    <tr>
                        <td class="px-4 py-5">
                            <a href="{{ route('mi-espacio.documents.show', $document) }}" class="font-semibold text-brand hover:underline">{{ $document->name }}</a>
                            @if($document->mentions_user)
                                <span class="ml-2 inline-flex rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700">Te mencionan</span>
                            @endif
                            <div class="mt-1.5 text-sm text-gray-500">
                                {{ collect([$document->code, $document->processType?->name, $showCompany ? $document->company?->procesos_name : null])->filter()->implode(' · ') }}
                            </div>
                        </td>
                        <td class="px-4 py-5 align-top">
                            <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusColors[$document->auditee_status] }}">{{ $document->auditee_status }}</span>
                            @if($document->being_updated)
                                <div class="mt-1.5 text-xs text-gray-500">Se está actualizando</div>
                            @endif
                        </td>
                        <td class="px-4 py-5 text-right align-top text-gray-800">{{ $document->publishedVersion?->version_number ?? '—' }}</td>
                        <td class="px-4 py-5 text-right">
                            <a href="{{ route('mi-espacio.documents.show', $document) }}"
                               class="inline-flex rounded-lg border-2 border-brand px-4 py-2 text-[15px] font-semibold text-brand hover:bg-brand-50">Consultar</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-12 text-center text-[15px] text-gray-600">{{ $emptyText }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
