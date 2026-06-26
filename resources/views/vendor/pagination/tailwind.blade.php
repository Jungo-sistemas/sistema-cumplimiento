@if ($paginator->hasPages())
<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">

    {{-- Contador --}}
    <p class="text-sm text-gray-500">
        @if ($paginator->firstItem())
            Mostrando
            <span class="font-medium text-gray-700">{{ $paginator->firstItem() }}</span>
            &ndash;
            <span class="font-medium text-gray-700">{{ $paginator->lastItem() }}</span>
            de
            <span class="font-medium text-gray-700">{{ $paginator->total() }}</span>
            resultado(s)
        @else
            <span class="font-medium text-gray-700">{{ $paginator->count() }}</span> resultado(s)
        @endif
    </p>

    {{-- Controles --}}
    <div class="flex items-center gap-1.5">

        {{-- Anterior --}}
        @if ($paginator->onFirstPage())
            <span class="inline-flex items-center px-3 py-2 rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed select-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" rel="prev"
               class="inline-flex items-center px-3 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
        @endif

        {{-- Páginas --}}
        @php
            $current = $paginator->currentPage();
            $last    = $paginator->lastPage();
            $start   = max(1, $current - 1);
            $end     = min($last, $current + 1);
        @endphp

        @if ($start > 1)
            <a href="{{ $paginator->url(1) }}"
               class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold text-sm">1</a>
            @if ($start > 2)
                <span class="px-1 py-2 text-gray-400 select-none text-sm">&hellip;</span>
            @endif
        @endif

        @for ($page = $start; $page <= $end; $page++)
            @if ($page == $current)
                <span class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold border border-[#1A428A] select-none text-sm">{{ $page }}</span>
            @else
                <a href="{{ $paginator->url($page) }}"
                   class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold text-sm">{{ $page }}</a>
            @endif
        @endfor

        @if ($end < $last)
            @if ($end < $last - 1)
                <span class="px-1 py-2 text-gray-400 select-none text-sm">&hellip;</span>
            @endif
            <a href="{{ $paginator->url($last) }}"
               class="px-4 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold text-sm">{{ $last }}</a>
        @endif

        {{-- Siguiente --}}
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" rel="next"
               class="inline-flex items-center px-3 py-2 rounded-md border border-[#1A428A] text-[#1A428A] bg-white hover:bg-blue-50 font-semibold">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </a>
        @else
            <span class="inline-flex items-center px-3 py-2 rounded-md border border-gray-200 text-gray-400 bg-gray-50 cursor-not-allowed select-none">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
            </span>
        @endif

    </div>
</div>
@endif
