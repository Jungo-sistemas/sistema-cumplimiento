<x-layouts.vigia title="Mis solicitudes">
    <x-slot name="breadcrumb">
        <a href="{{ route('mi-espacio.home') }}" class="hover:underline">Mi espacio</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis solicitudes</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis solicitudes',
        'subtitle' => 'Consulta el estado de lo que has solicitado.',
    ])

    <div class="rounded-2xl border border-gray-200 bg-white p-8">
        @forelse($requests as $solicitud)
            {{-- Todavía no se define qué solicitudes se listan aquí. --}}
        @empty
            <p class="py-12 text-center text-[15px] text-gray-600">Todavía no hay solicitudes.</p>
        @endforelse
    </div>
</x-layouts.vigia>
