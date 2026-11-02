<x-layouts.vigia title="Checklist · Auditorías">
    <x-slot name="breadcrumb">
        <a href="{{ route('auditorias.audits.index') }}" class="hover:underline">Auditorías</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Checklist de auditoría</span>
    </x-slot>

    @php
        // Datos de ejemplo para la demo: las auditorías en ejecución, con el mismo número (id) que usará la lista.
        $auditorias = [
            1 => ['procedimiento' => 'Control de maniobras y seguridad', 'auditado' => 'Laura Méndez',  'estado' => 'En captura'],
            2 => ['procedimiento' => 'Recepción de materia prima',       'auditado' => 'Jorge Salinas', 'estado' => 'Programada'],
        ];

        // $audit es el número que viene en la dirección (/auditorias/1/checklist). Si no existe: 404.
        abort_unless(isset($auditorias[$audit]), 404);
        $auditoria = $auditorias[$audit];
    @endphp

    <div class="mb-4 flex items-center justify-between gap-4">
        <a href="{{ route('auditorias.audits.index') }}" class="text-sm font-semibold text-brand hover:underline">Volver a auditorías</a>
        <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $auditoria['estado'] === 'En captura' ? 'bg-amber-50 text-amber-700' : 'bg-brand-50 text-brand' }}">{{ $auditoria['estado'] }}</span>
    </div>

    @include('mi-espacio.partials.header', [
        'title'    => 'Checklist de auditoría',
        'subtitle' => $auditoria['procedimiento'] . ' · ' . $auditoria['auditado'],
    ])
</x-layouts.vigia>
