{{-- Logotipo de Jungo Cumple. Por defecto la versión blanca (encabezado y pantallas de acceso,
     sobre fondo morado); variant="color" para fondos claros. El tamaño se da con la altura:
     <x-brand-logo class="h-10" /> --}}
@props(['variant' => 'blanco'])

<img src="{{ asset($variant === 'color' ? 'images/jungo-cumple.svg' : 'images/jungo-cumple-blanco.svg') }}"
     alt="Jungo Cumple" {{ $attributes->merge(['class' => str_contains($attributes->get('class', ''), 'h-') ? 'w-auto' : 'h-8 w-auto']) }}>
