{{-- Pestañas como enlaces (?tab=...). Variables: $tabs [clave => etiqueta], $tab (activa), $route. --}}
<div class="mb-7 flex gap-6 border-b border-gray-200">
    @foreach($tabs as $key => $label)
        <a href="{{ route($route, ['tab' => $key]) }}"
           class="-mb-px border-b-2 pb-3 text-base font-semibold {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
    @endforeach
</div>
