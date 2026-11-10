<x-layouts.vigia title="Mis documentos">
    <x-slot name="breadcrumb">
        <span>Procesos</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis documentos</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis documentos',
        'subtitle' => 'Consulta los documentos relacionados con tu trabajo.',
    ])

    @php
        $statusColors = [
            'Liberado'       => 'bg-green-100 text-green-800',
            'Obsoleto'       => 'bg-gray-100 text-gray-700',
            'Desactualizado' => 'bg-amber-100 text-amber-800',
        ];
    @endphp

    <form method="GET" action="{{ route('mi-espacio.documents') }}" class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-center">
        @include('mi-espacio.partials.company-filter')
        <input type="search" name="q" value="{{ $search }}" placeholder="Buscar por código o nombre..."
               class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
    </form>

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
                                <a href="{{ route('processes.show', $document) }}" class="font-semibold text-brand hover:underline">{{ $document->name }}</a>
                                <div class="mt-1.5 text-sm text-gray-500">
                                    {{ collect([$document->code, $document->processType?->name, $companies->count() > 1 && ! $selectedCompanyId ? $document->company?->procesos_name : null])->filter()->implode(' · ') }}
                                </div>
                            </td>
                            <td class="px-4 py-5 align-top">
                                <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusColors[$document->auditee_status] }}">{{ $document->auditee_status }}</span>
                            </td>
                            <td class="px-4 py-5 text-right align-top text-gray-800">{{ $document->currentVersion?->version_number ?? '—' }}</td>
                            <td class="px-4 py-5 text-right">
                                <a href="{{ route('processes.show', $document) }}"
                                   class="inline-flex rounded-lg border-2 border-brand px-4 py-2 text-[15px] font-semibold text-brand hover:bg-brand-50">Consultar</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-12 text-center text-[15px] text-gray-600">
                                {{ $search !== '' ? 'Ningún documento coincide con la búsqueda.' : 'Todavía no tienes documentos publicados.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.vigia>
