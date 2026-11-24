{{--
    Módulos visibles del usuario: una casilla por módulo del portal (App\Support\PortalModules).
    $model:   arreglo de Alpine con los marcados (x-model)
    $options: los módulos que puede dar quien edita (User::grantablePortalModules())
    Auditorías (Mi espacio) le queda a todos aunque no tenga marcado Procesos y Auditorías.
--}}
<div class="grid grid-cols-1 gap-px overflow-hidden rounded-lg border border-gray-200 bg-gray-100 sm:grid-cols-2">
    @foreach($options as $key)
        <label class="flex cursor-pointer items-center gap-2 bg-white px-3 py-2.5 text-sm text-gray-700">
            <input type="checkbox" name="modules[]" value="{{ $key }}" x-model="{{ $model }}"
                   class="rounded border-gray-300 text-brand focus:ring-brand/40">
            {{ \App\Support\PortalModules::LABELS[$key] }}
        </label>
    @endforeach
</div>
<p class="mt-1 text-xs text-red-600" x-show="{{ $model }}.length === 0">Marca al menos un módulo.</p>
<p class="mt-1 text-xs text-gray-400">
    Solo verá los módulos marcados. Sin Procesos y Auditorías, ahí solo tendrá Mi espacio (sus procedimientos, aprobaciones y auditorías).
</p>
@if(count($options) < count(\App\Support\PortalModules::LABELS))
    <p class="text-xs text-gray-400">Solo puedes dar acceso a los módulos que tú tienes.</p>
@endif
