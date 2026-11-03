<x-layouts.vigia title="Ayuda · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Ayuda</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Ayuda',
        'subtitle' => 'Auditorías realizadas durante una llamada de Teams.',
    ])

    <div class="divide-y divide-gray-200 rounded-2xl border border-gray-200 bg-white px-8">
        @foreach([
            'Capturar checklist'               => 'El auditor asignado captura respuestas, puntajes y observaciones durante la llamada. Puede guardar avance y finalizar para publicar el resultado.',
            'Hallazgos y acciones correctivas' => 'El auditor registra los hallazgos. Su seguimiento conserva las causas raíz, acciones y evidencias en la actividad correspondiente.',
        ] as $seccion => $texto)
            <details class="py-5" open>
                <summary class="cursor-pointer text-base font-semibold text-brand">{{ $seccion }}</summary>
                <p class="mt-2 text-[15px] text-gray-700">{{ $texto }}</p>
            </details>
        @endforeach
    </div>
</x-layouts.vigia>
