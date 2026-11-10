<x-layouts.vigia title="Indicadores de auditorías">
    @include('mi-espacio.partials.header', [
        'title'    => 'Indicadores de auditorías',
        'subtitle' => 'Avance del programa y atención de hallazgos.',
    ])

    {{-- Las auditorías todavía no tienen tablas (AUD-1): se dejan los indicadores acordados sin números. --}}
    <div class="rounded-2xl border border-dashed border-gray-300 bg-white p-7">
        <h2 class="text-lg font-semibold text-gray-900">Todavía no hay auditorías registradas</h2>
        <p class="mt-2 max-w-2xl text-[15px] text-gray-600">Cuando se registren auditorías, aquí se verán:</p>
        <ul class="mt-4 grid gap-3 text-[15px] text-gray-700 sm:grid-cols-2">
            <li class="rounded-xl bg-gray-50 px-4 py-3"><span class="font-semibold">Programa cumplido:</span> auditorías realizadas contra las programadas del año.</li>
            <li class="rounded-xl bg-gray-50 px-4 py-3"><span class="font-semibold">Hallazgos abiertos:</span> por empresa y cuántos tienen el plan vencido.</li>
            <li class="rounded-xl bg-gray-50 px-4 py-3"><span class="font-semibold">Cierre a tiempo:</span> hallazgos cerrados dentro de su plazo.</li>
            <li class="rounded-xl bg-gray-50 px-4 py-3"><span class="font-semibold">Días para cerrar:</span> promedio por hallazgo.</li>
        </ul>
    </div>
</x-layouts.vigia>
