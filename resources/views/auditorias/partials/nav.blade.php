{{-- Menú lateral del módulo de Auditorías. El layout lo incluye dos veces:
     en el menú de escritorio y en el del celular; $mobile cierra el menú móvil al navegar. --}}
@php
    $items = [
        ['route' => 'auditorias.home',           'active' => 'auditorias.home',       'label' => 'Inicio'],
        ['route' => 'auditorias.audits.index',   'active' => 'auditorias.audits.*',   'label' => 'Auditorías'],
        ['route' => 'auditorias.findings.index', 'active' => 'auditorias.findings.*', 'label' => 'Hallazgos'],
        ['route' => 'auditorias.help',           'active' => 'auditorias.help',       'label' => 'Ayuda'],
    ];
@endphp

@foreach($items as $item)
    <a href="{{ route($item['route']) }}" @if($mobile) @click="mobileMenuOpen = false" @endif
       class="block rounded-md px-3 py-2 {{ request()->routeIs($item['active']) ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">{{ $item['label'] }}</a>
@endforeach
