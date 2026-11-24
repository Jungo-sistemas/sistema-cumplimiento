{{-- Pantalla provisional del Jefe de Auditoría mientras se construye la de verdad. Variables: $titulo, $descripcion. --}}
<x-layouts.vigia :title="$titulo . ' · Auditorías'">
    @include('mi-espacio.partials.header', ['title' => $titulo, 'subtitle' => $descripcion])

    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-10 text-center">
        <h2 class="text-lg font-semibold text-gray-900">Pantalla en construcción</h2>
        <p class="mt-2 text-[15px] text-gray-600">Muy pronto vas a poder trabajar aquí. Mientras, muévete con el menú.</p>
    </div>
</x-layouts.vigia>
