<x-layouts.vigia title="Mis actividades">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis actividades</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis actividades',
        'subtitle' => 'Atiende tus pendientes y consulta su seguimiento.',
    ])

    @include('mi-espacio.partials.tabs', [
        'tabs'  => ['por-atender' => 'Por atender', 'concluidas' => 'Concluidas'],
        'route' => 'mi-espacio.activities',
    ])

    {{-- Las actividades (acciones correctivas de los hallazgos) llegan con AUD-1; por ahora vacío. --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-8">
        @forelse($activities as $activity)
        @empty
            @if($tab === 'por-atender')
                <div class="flex flex-col items-center py-8 text-center">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-xl bg-brand-50 text-brand">
                        <x-icon name="check" class="h-5 w-5" />
                    </span>
                    <p class="mt-4 text-[15px] text-gray-600">No tienes actividades pendientes.</p>
                </div>
            @else
                <p class="py-12 text-center text-[15px] text-gray-600">No hay actividades concluidas.</p>
            @endif
        @endforelse
    </div>
</x-layouts.vigia>
