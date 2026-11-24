{{--
    Notificaciones de administración: lista desplegable con casillas (Cumplimiento, Procesos,
    Auditorías). Se guarda igual que antes: notification_modules (all / cumplimiento / procesos /
    none) y receives_audit_alerts.
    $areas: variable de Alpine con el arreglo de áreas marcadas; $audit: variable booleana de Auditorías.
--}}
@php
    $options = [
        'cumplimiento' => ['Cumplimiento', 'Solicitudes de acceso, escalamientos y avisos a administradores.'],
        'procesos'     => ['Procesos', 'Solicitudes de acceso, escalamientos y avisos a administradores.'],
    ];
@endphp
<div x-data="{ open: false }" class="relative" @click.outside="open = false" @keydown.escape="open = false">
    <input type="hidden" name="notification_modules" :value="notifyValue({{ $areas }})">
    <input type="hidden" name="receives_audit_alerts" :value="{{ $audit }} ? 1 : 0">

    <button type="button" @click="open = ! open"
            class="flex w-full items-center justify-between rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-left text-sm focus:border-brand focus:outline-none focus:ring-2 focus:ring-brand/20">
        <span x-text="notifySummary({{ $areas }}, {{ $audit }})"
              :class="{{ $areas }}.length || {{ $audit }} ? 'text-gray-900' : 'text-gray-500'"></span>
        <svg class="h-4 w-4 shrink-0 text-gray-400 transition" :class="open && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
    </button>

    <div x-show="open" x-cloak x-transition.opacity
         class="absolute z-20 mt-1 w-full divide-y divide-gray-100 rounded-lg border border-gray-200 bg-white shadow-lg">
        @foreach($options as $value => [$label, $hint])
            <label class="flex cursor-pointer items-start gap-2.5 px-3 py-2.5 text-sm hover:bg-gray-50">
                <input type="checkbox" value="{{ $value }}" x-model="{{ $areas }}"
                       class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/40">
                <span class="text-gray-700">{{ $label }}
                    <span class="block text-xs text-gray-400">{{ $hint }}</span>
                </span>
            </label>
        @endforeach
        <label class="flex cursor-pointer items-start gap-2.5 px-3 py-2.5 text-sm hover:bg-gray-50">
            <input type="checkbox" x-model="{{ $audit }}"
                   class="mt-0.5 rounded border-gray-300 text-brand focus:ring-brand/40">
            <span class="text-gray-700">Auditorías
                <span class="block text-xs text-gray-400">Hallazgos críticos y plazos vencidos de los hallazgos del grupo.</span>
            </span>
        </label>
    </div>
</div>
<p class="mt-1 text-xs text-gray-400">Qué avisos de administración recibirá. Sin ninguna marcada, solo sus avisos personales (sus propias aprobaciones, hallazgos y tareas le llegan siempre).</p>
