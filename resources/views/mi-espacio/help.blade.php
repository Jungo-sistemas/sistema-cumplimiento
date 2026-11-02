<x-layouts.vigia title="Ayuda">
    <x-slot name="breadcrumb">
        <a href="{{ route('mi-espacio.home') }}" class="hover:underline">Mi espacio</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Ayuda</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Ayuda',
        'subtitle' => 'Qué encuentras en cada sección de tu espacio.',
    ])

    <div class="divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white px-8">
        @foreach([
            'Mis documentos'  => 'Los procesos publicados de las empresas en las que estás asignado. Desde aquí los consultas en su versión vigente.',
            'Mis auditorías'  => 'Las auditorías en las que participas: en Activas contestas el checklist y atiendes correcciones; en Historial consultas los resultados.',
            'Mis actividades' => 'Las acciones correctivas que te asignaron a partir de los hallazgos de una auditoría, con su seguimiento.',
            'Mis solicitudes' => 'El estado de lo que has solicitado dentro del sistema.',
        ] as $section => $text)
            <details class="py-5" open>
                <summary class="cursor-pointer text-base font-semibold text-brand">{{ $section }}</summary>
                <p class="mt-2 text-[15px] text-gray-700">{{ $text }}</p>
            </details>
        @endforeach
    </div>
</x-layouts.vigia>
