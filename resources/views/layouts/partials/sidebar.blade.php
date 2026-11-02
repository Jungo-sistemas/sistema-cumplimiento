{{-- Menú lateral del layout vigia. Lo pinta dos veces: en escritorio (colapsable, `collapsed`
     viene del x-data del layout) y en el celular ($mobile, siempre abierto). Las secciones y ligas
     las arma el layout en $sections / $helpItem. Colapsado solo muestra los íconos; el nombre
     queda en el tooltip (title). --}}
@php
    $rowClasses = "collapsed ? 'justify-center px-0 py-3' : 'items-center gap-3 px-4 py-3 text-[15px]'";
@endphp

<div class="flex h-full flex-col" @if($mobile) x-data="{ collapsed: false }" @endif>
    <div class="px-3 pt-4">
        <button type="button"
                @if($mobile) @click="mobileMenuOpen = false" @else @click="toggleSidebar()" @endif
                :title="collapsed ? 'Abrir menú' : 'Cerrar menú'"
                class="flex w-full items-center rounded-xl font-medium text-gray-800 hover:bg-gray-50"
                :class="{{ $rowClasses }}">
            <x-icon :name="$mobile ? 'close' : 'menu'" class="h-6 w-6" />
            <span x-show="!collapsed">Cerrar menú</span>
        </button>
    </div>

    {{-- En el menú angosto ninguna barra de desplazamiento debe verse ni quitarle ancho. --}}
    <nav class="no-scrollbar min-h-0 flex-1 overflow-y-auto overflow-x-hidden px-3 pb-3">
        @foreach($sections as $section)
            <div class="mt-3 border-t border-gray-100 pt-4">
                @if($section['title'])
                    <p x-show="!collapsed" class="mb-2 truncate px-4 text-[11px] font-bold uppercase tracking-wider text-gray-500">{{ $section['title'] }}</p>
                @endif

                <div class="space-y-1">
                    @foreach($section['items'] as $link)
                        <a href="{{ $link['url'] }}"
                           @if(!$mobile && !empty($link['tour'])) data-tour="{{ $link['tour'] }}" @endif
                           @if(!empty($link['newTab'])) target="_blank" rel="noopener" @endif
                           @if($mobile) @click="mobileMenuOpen = false" @endif
                           title="{{ $link['label'] }}"
                           class="relative flex items-center rounded-xl transition {{ $link['active'] ? 'bg-brand-50 font-semibold text-brand' : 'font-medium text-gray-800 hover:bg-gray-50' }}"
                           :class="{{ $rowClasses }}">
                            <x-icon :name="$link['icon']" class="h-6 w-6" />
                            <span x-show="!collapsed" class="min-w-0 flex-1 truncate">{{ $link['label'] }}</span>
                            @if(!empty($link['badge']))
                                <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-yellow-400 px-1.5 text-xs font-bold text-yellow-900"
                                      :class="collapsed ? 'absolute right-0.5 top-0.5' : ''">{{ $link['badge'] }}</span>
                            @endif
                        </a>

                        @if(!empty($link['children']))
                            <div x-show="!collapsed" class="mt-1 space-y-1">
                                @foreach($link['children'] as $child)
                                    <a href="{{ $child['url'] }}" @if($mobile) @click="mobileMenuOpen = false" @endif
                                       style="padding-left: {{ 1 + $child['depth'] * 0.75 }}rem"
                                       class="flex items-center rounded-lg py-2 pr-3 text-sm {{ $child['active'] ? 'bg-brand-50 font-semibold text-brand' : 'text-gray-700 hover:bg-gray-50' }}">
                                        <span class="mr-2 text-gray-400">└</span><span class="truncate">{{ $child['label'] }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </nav>

    <div class="px-3 pb-4">
        @if($helpItem)
            <a href="{{ $helpItem['url'] }}" title="{{ $helpItem['label'] }}"
               @if(!empty($helpItem['newTab'])) target="_blank" rel="noopener" @endif
               class="flex items-center rounded-xl {{ $helpItem['active'] ? 'bg-brand-50 font-semibold text-brand' : 'font-medium text-gray-800 hover:bg-gray-50' }}"
               :class="{{ $rowClasses }}">
                <x-icon name="help" class="h-6 w-6" />
                <span x-show="!collapsed">{{ $helpItem['label'] }}</span>
            </a>
        @endif

        <a href="{{ route('profile.edit') }}" title="Mi perfil"
           class="mt-2 flex items-center gap-3 border-t border-gray-100 pt-4 hover:opacity-90"
           :class="collapsed ? 'justify-center' : 'px-2'">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-brand-50 text-base font-bold text-brand">{{ $initials ?: '?' }}</span>
            <span x-show="!collapsed" class="min-w-0">
                <span class="block truncate font-semibold text-gray-900">{{ $user?->name }}</span>
                @if($companyLabel)
                    <span class="block truncate text-sm text-gray-500">{{ $companyLabel }}</span>
                @endif
            </span>
        </a>
    </div>
</div>
