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
            'Estados de una auditoría'         => 'Sin fecha: todavía no se acuerda con el auditado. Programada: ya tiene fecha y el auditado recibe el aviso unos días antes. Por iniciar: llegó el día; el auditor la inicia con el botón Iniciar auditoría al empezar la llamada y queda registrada la hora. En curso: se captura el checklist (ya no se cambia la fecha). Finalizada: se publicó el resultado.',
            'Capturar checklist'               => 'Durante la llamada, el auditor marca cada criterio como Cumple, Parcial, No cumple o No aplica, pasa lista de asistencia y escribe sus conclusiones. En cada criterio puede adjuntar archivos o tomar una foto como soporte. La calificación se calcula sola con el valor fijo de cada resultado. Puede guardar avance y finalizar para publicar el resultado.',
            'Hallazgos y acciones correctivas' => 'El auditor registra cada hallazgo en el criterio del checklist donde lo encontró, con un solo responsable. Si marca No cumple o Parcial, la ventana para registrarlo se abre sola. Los hallazgos se pueden editar o eliminar hasta finalizar la auditoría; al finalizar se envían a sus responsables. Primero el auditado envía la causa raíz y las acciones. Cuando el auditor las aprueba, el auditado sube la evidencia, y cuando el auditor la aprueba, el hallazgo se cierra. En las dos revisiones el auditor puede rechazar con un motivo.',
        ] as $seccion => $texto)
            <details class="py-5" open>
                <summary class="cursor-pointer text-base font-semibold text-brand">{{ $seccion }}</summary>
                <p class="mt-2 text-[15px] text-gray-700">{{ $texto }}</p>
            </details>
        @endforeach
    </div>
</x-layouts.vigia>
