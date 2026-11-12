<x-layouts.vigia :title="$regulation->name">
    <x-slot name="breadcrumb">
        <a href="{{ route('mi-espacio.documents') }}" class="text-gray-600 hover:underline">Mis documentos</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">{{ $regulation->code ?: $regulation->name }}</span>
    </x-slot>

    @php
        $statusColors = [
            'Liberado'       => 'bg-green-100 text-green-800',
            'Obsoleto'       => 'bg-gray-100 text-gray-700',
            'Desactualizado' => 'bg-amber-100 text-amber-800',
        ];
        $previewUrl = route('regulation-versions.preview', ['version' => $version, 'from' => 'mi-espacio']);
    @endphp

    {{-- Solo consulta: la última versión aprobada, sin acciones de edición. --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight text-brand-ink sm:text-3xl">{{ $regulation->name }}</h1>
            <p class="mt-2 text-sm text-gray-600">
                {{ collect([$regulation->code, $regulation->processType?->name, $regulation->company?->procesos_name])->filter()->implode(' · ') }}
            </p>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <span class="inline-flex rounded-full px-3 py-1 text-sm font-semibold {{ $statusColors[$regulation->auditee_status] }}">{{ $regulation->auditee_status }}</span>
                <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">Versión {{ $version->version_number }}</span>
                @if($version->valid_until)
                    <span class="inline-flex rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">Vigente hasta {{ $version->valid_until->format('d/m/Y') }}</span>
                @endif
                @if($regulation->mentions_user)
                    <span class="inline-flex rounded-full bg-blue-50 px-3 py-1 text-sm font-semibold text-blue-700">Te mencionan</span>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 gap-2">
            <a href="{{ $previewUrl }}" target="_blank"
               class="inline-flex rounded-lg border-2 border-brand px-4 py-2 text-[15px] font-semibold text-brand hover:bg-brand-50">Abrir en pestaña</a>
            @if($regulation->allowsDownload())
                <a href="{{ route('regulation-versions.download', $version) }}"
                   class="inline-flex rounded-lg bg-brand px-4 py-2 text-[15px] font-semibold text-white hover:bg-brand-dark">Descargar</a>
            @endif
        </div>
    </div>

    @if($regulation->being_updated)
        <div class="mb-4 rounded-xl border border-blue-200 bg-blue-50 px-5 py-3 text-sm text-blue-900">
            Este proceso se está actualizando. Mientras se aprueba la nueva versión, aquí ves la última versión aprobada.
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white">
        <iframe src="{{ $previewUrl }}" class="h-[75vh] w-full border-0" title="{{ $regulation->name }}"></iframe>
    </div>
</x-layouts.vigia>
