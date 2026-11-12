<x-layouts.vigia title="Mis documentos">
    <x-slot name="breadcrumb">
        <span>Procesos</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis documentos</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis documentos',
        'subtitle' => 'Consulta los procesos que te aplican. Siempre ves la última versión aprobada.',
    ])

    @php
        $multiCompany = $companies->count() > 1;
        $folderIcon = 'M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z';
        $tabUrl = fn ($t) => route('mi-espacio.documents', ['tab' => $t]);
        $companyUrl = fn ($extra = []) => route('mi-espacio.documents', array_filter([
            'tab'        => 'empresa',
            'company_id' => $multiCompany ? $selectedCompany?->id : null,
        ] + $extra));
    @endphp

    @if($missingCompany)
        <div class="mb-6 rounded-xl border border-amber-200 bg-amber-50 px-5 py-4 text-[15px] text-amber-900">
            <p class="font-semibold">No tienes una empresa asignada.</p>
            <p class="mt-1">Necesitas una empresa para ver los procesos. Pide a un administrador que te la asigne. Mientras tanto solo ves los documentos donde te mencionan.</p>
        </div>
    @endif

    {{-- Pestañas: Te mencionan / Procesos de mi empresa --}}
    <div class="mb-6 flex gap-1 border-b border-gray-200">
        @foreach([
            'menciones' => 'Te mencionan' . ($mentionsCount ? " ({$mentionsCount})" : ''),
            'empresa'   => $multiCompany || $companies->isEmpty() ? 'Procesos de mis empresas' : 'Procesos de mi empresa',
        ] as $key => $label)
            <a href="{{ $tabUrl($key) }}"
               @class([
                   '-mb-px border-b-2 px-4 py-3 text-[15px] font-semibold transition',
                   'border-brand text-brand' => $tab === $key,
                   'border-transparent text-gray-500 hover:text-brand' => $tab !== $key,
               ])>{{ $label }}</a>
        @endforeach
    </div>

    @if($tab === 'empresa' && $companies->isNotEmpty())
        {{-- Ruta: empresa › carpeta --}}
        <div class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <nav class="flex flex-wrap items-center gap-2 text-sm">
                @if($multiCompany)
                    <a href="{{ route('mi-espacio.documents', ['tab' => 'empresa']) }}" class="font-semibold text-brand hover:underline">Empresas</a>
                @endif
                @if($selectedCompany)
                    @if($multiCompany)<span class="text-gray-400">›</span>@endif
                    @if($selectedFolder)
                        <a href="{{ $companyUrl() }}" class="font-semibold text-brand hover:underline">{{ $selectedCompany->procesos_name }}</a>
                        <span class="text-gray-400">›</span>
                        <span class="font-semibold text-gray-700">{{ $selectedFolder['name'] }}</span>
                    @else
                        <span class="font-semibold text-gray-700">{{ $selectedCompany->procesos_name }}</span>
                    @endif
                @endif
            </nav>

            <form method="GET" action="{{ route('mi-espacio.documents') }}" class="w-full sm:w-96">
                <input type="hidden" name="tab" value="empresa">
                @if($multiCompany && $selectedCompany)
                    <input type="hidden" name="company_id" value="{{ $selectedCompany->id }}">
                @endif
                <input type="search" name="q" value="{{ $search }}"
                       placeholder="{{ $multiCompany && ! $selectedCompany ? 'Buscar en todas tus empresas...' : 'Buscar por código o nombre...' }}"
                       class="h-12 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
            </form>
        </div>
    @elseif($tab === 'menciones' && $mentionsCount > 0)
        <form method="GET" action="{{ route('mi-espacio.documents') }}" class="mb-5 sm:w-96">
            <input type="hidden" name="tab" value="menciones">
            <input type="search" name="q" value="{{ $search }}" placeholder="Buscar por código o nombre..."
                   class="h-12 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
        </form>
    @endif

    @if($tab === 'empresa' && $companies->isEmpty())
        <div class="rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center text-[15px] text-gray-600">
            No hay procesos de empresa que mostrar.
        </div>

    @elseif($tab === 'empresa' && $search === '' && $companyCards->isNotEmpty())
        {{-- Tarjetas de empresa (varias empresas o acceso a todas) --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($companyCards as $card)
                <a href="{{ route('mi-espacio.documents', ['tab' => 'empresa', 'company_id' => $card['company']->id]) }}"
                   class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm transition hover:border-brand hover:shadow-md">
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-brand-50 transition group-hover:bg-brand-100">
                            <svg class="h-5 w-5 text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $folderIcon }}"/></svg>
                        </div>
                        <p class="min-w-0 flex-1 font-semibold leading-snug text-gray-800 transition group-hover:text-brand">{{ $card['company']->procesos_name }}</p>
                    </div>
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            {{ $card['count'] }} {{ \Illuminate\Support\Str::plural('procedimiento', $card['count']) }}
                        </span>
                        <svg class="h-4 w-4 text-gray-400 transition group-hover:text-brand" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </div>
                </a>
            @endforeach
        </div>

    @elseif($tab === 'empresa' && $search === '' && ! $selectedFolder)
        {{-- Carpetas de la empresa, una por tipo de proceso --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach($folders as $folder)
                <a href="{{ $companyUrl(['process_type_id' => $folder['id']]) }}"
                   @class([
                       'group flex flex-col rounded-xl border bg-white p-4 shadow-sm transition hover:border-brand hover:shadow-md',
                       'border-gray-200' => $folder['count'] > 0,
                       'border-dashed border-gray-200' => $folder['count'] === 0,
                   ])>
                    <div class="flex items-start gap-3">
                        <div @class([
                                'mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-lg transition',
                                'bg-brand-50 group-hover:bg-brand-100' => $folder['count'] > 0,
                                'bg-gray-50 group-hover:bg-brand-50' => $folder['count'] === 0,
                             ])>
                            <svg @class(['h-5 w-5', 'text-brand' => $folder['count'] > 0, 'text-gray-400' => $folder['count'] === 0]) fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $folderIcon }}"/></svg>
                        </div>
                        <div class="min-w-0 flex-1">
                            @if($folder['number'])
                                <p class="font-mono text-xs text-gray-400">{{ str_pad($folder['number'], 2, '0', STR_PAD_LEFT) }}</p>
                            @endif
                            <p class="font-semibold leading-snug text-gray-800 transition group-hover:text-brand">{{ $folder['name'] }}</p>
                        </div>
                    </div>
                    <div class="mt-4">
                        <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            {{ $folder['count'] }} {{ \Illuminate\Support\Str::plural('procedimiento', $folder['count']) }}
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

    @else
        {{-- Lista: menciones, una carpeta o resultados de búsqueda --}}
        @include('mi-espacio.partials.documents-table', [
            'showCompany' => $tab === 'menciones' || ($multiCompany && ! $selectedCompany),
            'emptyText'   => match (true) {
                $search !== ''           => 'Ningún documento coincide con la búsqueda.',
                $tab === 'menciones'     => 'Ningún documento publicado te menciona todavía.',
                default                  => 'Esta carpeta no tiene procesos publicados.',
            },
        ])
    @endif
</x-layouts.vigia>
