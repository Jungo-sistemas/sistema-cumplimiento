<x-layouts.vigia title="Ayuda · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Ayuda</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 space-y-6">
        <div class="space-y-2">
            <h1 class="text-2xl font-semibold text-[#1A428A]">Ayuda</h1>
            <p class="text-sm text-gray-600">Auditorías realizadas durante una llamada de Teams.</p>
        </div>

        <div class="divide-y border-y">
            <details class="py-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-[#1A428A]">Capturar checklist</summary>
                <p class="mt-2 text-sm text-gray-700">
                    El auditor asignado captura respuestas, puntajes y observaciones durante la llamada.
                    Puede guardar avance y finalizar para publicar el resultado.
                </p>
            </details>

            <details class="py-4" open>
                <summary class="cursor-pointer text-sm font-semibold text-[#1A428A]">Hallazgos y acciones correctivas</summary>
                <p class="mt-2 text-sm text-gray-700">
                    El auditor registra los hallazgos. Su seguimiento conserva las causas raíz, acciones y
                    evidencias en la actividad correspondiente.
                </p>
            </details>
        </div>
    </div>
</x-layouts.vigia>
