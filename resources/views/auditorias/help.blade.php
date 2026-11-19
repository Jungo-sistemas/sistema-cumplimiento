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
            'Capturar checklist'               => 'Durante la llamada, el auditor marca cada criterio como Cumple, Parcial, No cumple o No aplica, pasa lista de asistencia y escribe sus conclusiones. La calificación se calcula sola con el valor fijo de cada resultado. Puede guardar avance y finalizar para publicar el resultado.',
            'Hallazgos y acciones correctivas' => 'El auditor registra cada hallazgo en el criterio del checklist donde lo encontró, con un solo responsable. El auditado responde con la causa raíz, las acciones y las evidencias, y el auditor aprueba y cierra el hallazgo o lo rechaza para que lo corrija.',
        ] as $seccion => $texto)
            <details class="py-5" open>
                <summary class="cursor-pointer text-base font-semibold text-brand">{{ $seccion }}</summary>
                <p class="mt-2 text-[15px] text-gray-700">{{ $texto }}</p>
            </details>
        @endforeach
    </div>
</x-layouts.vigia>
