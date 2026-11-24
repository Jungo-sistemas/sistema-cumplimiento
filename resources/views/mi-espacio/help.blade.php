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
            'Mis auditorías'  => 'El auditor fija el día y la hora de tu auditoría. ' . \App\Models\Audit::noticeDays() . ' días antes te llega un aviso y aparece en Activas (la auditoría es en vivo por Teams); si el auditor cambia la fecha, también te avisamos. Prepara tus evidencias con el checklist del proceso; en Historial consultas los resultados.',
            'Mis hallazgos'   => 'Lo que el auditor anotó durante tu auditoría y en lo que te eligió como responsable. Sube hasta 5 archivos de evidencia por hallazgo; si el auditor la rechaza, verás el motivo y podrás subir una nueva. Cuando la acepta, el hallazgo se cierra.',
        ] as $section => $text)
            <details class="py-5" open>
                <summary class="cursor-pointer text-base font-semibold text-brand">{{ $section }}</summary>
                <p class="mt-2 text-[15px] text-gray-700">{{ $text }}</p>
            </details>
        @endforeach
    </div>
</x-layouts.vigia>
