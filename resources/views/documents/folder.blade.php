<x-layouts.vigia :title="$folder->name">

    <x-slot name="breadcrumb">
        <a href="{{ route('documents.index') }}" class="text-gray-500 hover:underline">Documentos</a>
        <span class="mx-1 text-gray-400">›</span>
        <span class="text-gray-700 font-medium">{{ $folder->name }}</span>
    </x-slot>

    {{-- HEADER --}}
    <div class="flex items-start justify-between gap-4">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-blue-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#1A428A]"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                </svg>
            </div>
            <div>
                <h1 class="text-2xl font-semibold text-[#1A428A]">{{ $folder->name }}</h1>
                <div class="mt-1 flex items-center gap-2 flex-wrap">
                    <span class="text-sm text-gray-500">
                        {{ $categories->count() }}
                        {{ \Illuminate\Support\Str::plural('categoría', $categories->count()) }}
                    </span>
                    @if($folder->company)
                        <span class="text-gray-300">·</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-indigo-50
                                     px-2.5 py-0.5 text-xs font-medium text-indigo-700">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                            </svg>
                            {{ $folder->company->name }}
                        </span>
                    @endif
                </div>
            </div>
        </div>

        <a href="{{ route('documents.index') }}"
           class="px-4 py-2 rounded-md border border-[#1A428A] bg-white text-[#1A428A] font-semibold hover:bg-blue-50 shrink-0">
            Volver
        </a>
    </div>

    {{-- GRID DE CATEGORÍAS --}}
    @if($categories->isEmpty())
        <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
            </div>
            <p class="text-sm text-gray-500">No hay categorías en esta carpeta.</p>
        </div>
    @else
        <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach($categories as $category)
                <a href="{{ route('documents.categories.show', $category) }}"
                   class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm
                          transition hover:border-[#1A428A] hover:shadow-md">

                    {{-- Icono + nombre --}}
                    <div class="flex items-start gap-3">
                        <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center
                                    rounded-lg bg-indigo-50 group-hover:bg-indigo-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-indigo-600"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                            </svg>
                        </div>

                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-gray-800 leading-snug group-hover:text-[#1A428A] transition">
                                {{ $category->name }}
                            </p>
                        </div>
                    </div>

                    {{-- Pie: conteo + flecha --}}
                    <div class="mt-4 flex items-center justify-between">
                        <span class="inline-flex items-center gap-1 rounded-full bg-gray-100
                                     px-2.5 py-0.5 text-xs font-medium text-gray-600">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                            {{ $category->documents_count ?? 0 }}
                            {{ \Illuminate\Support\Str::plural('documento', $category->documents_count ?? 0) }}
                        </span>

                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 text-gray-400 group-hover:text-[#1A428A] transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </div>

                </a>
            @endforeach
        </div>

        <p class="mt-4 text-xs text-gray-400">
            {{ $categories->count() }} {{ \Illuminate\Support\Str::plural('categoría', $categories->count()) }}
        </p>
    @endif

</x-layouts.vigia>
