<x-layouts.vigia title="Inicio · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Inicio</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 space-y-2">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Inicio</h1>
        <p class="text-sm text-gray-600">Hola, {{ auth()->user()->name }}. Continúa las auditorías que tienes asignadas.</p>

        {{-- JD-832: tarjetas resumen y "Continuar auditoría" --}}
    </div>
</x-layouts.vigia>
