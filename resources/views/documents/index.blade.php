<x-layouts.vigia title="Documentos">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Documentos</span>
    </x-slot>

    @php $user = auth()->user(); @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Documentos</h1>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('documents.index') }}"
          class="mt-4 flex flex-wrap items-end gap-3">

        @if($user->hasGroupScope())
            <div class="min-w-[180px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            @selected((string) request('company_id', $selectedCompanyId) === (string) $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="flex-1 min-w-[200px] max-w-sm">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text"
                   name="q"
                   value="{{ request('q') }}"
                   placeholder="Carpeta, categoría o documento..."
                   class="w-full rounded-md border-gray-300 text-sm">
        </div>

        <button type="submit"
                class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
            Filtrar
        </button>

        <a href="{{ route('documents.index') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

    @php
        $hasSearch = request()->filled('q');
        $totalResults = $folders->count() + $matchingCategories->count() + $matchingDocuments->count();
    @endphp

    {{-- RESULTADOS DE BÚSQUEDA --}}
    @if($hasSearch)
        <p class="mt-5 text-sm text-gray-500">
            {{ $totalResults }} {{ $totalResults === 1 ? 'resultado' : 'resultados' }} para
            <span class="font-semibold text-gray-700">"{{ request('q') }}"</span>
        </p>
    @endif

    @if($hasSearch && $totalResults === 0)
        <div class="mt-4 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
            <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M21 21l-4.35-4.35M17 11A6 6 0 111 11a6 6 0 0116 0z"/>
                </svg>
            </div>
            <p class="text-sm text-gray-500">No se encontraron resultados para esta búsqueda.</p>
        </div>
    @else

        {{-- SECCIÓN: CARPETAS --}}
        @if($folders->isNotEmpty())
            @if($hasSearch)
                <h2 class="mt-6 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                    Carpetas ({{ $folders->count() }})
                </h2>
            @endif

            <div class="{{ $hasSearch ? 'mt-1' : 'mt-6' }} grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach($folders as $folder)
                    <a href="{{ route('documents.folders.show', $folder) }}"
                       class="group flex flex-col rounded-xl border border-gray-200 bg-white p-5 shadow-sm
                              transition hover:border-[#1A428A] hover:shadow-md">

                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center
                                        rounded-lg bg-blue-50 group-hover:bg-blue-100 transition">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-[#1A428A]"
                                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                                </svg>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-800 leading-snug group-hover:text-[#1A428A] transition">
                                    {{ $folder->name }}
                                </p>
                                @if($user->hasGroupScope() && $folder->company)
                                    <p class="mt-1 text-xs font-medium text-indigo-600">{{ $folder->company->name }}</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-4 flex items-center justify-between">
                            <span class="inline-flex items-center gap-1 rounded-full bg-gray-100
                                         px-2.5 py-0.5 text-xs font-medium text-gray-600">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none"
                                     viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                          d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"/>
                                </svg>
                                {{ $folder->categories_count ?? 0 }}
                                {{ \Illuminate\Support\Str::plural('categoría', $folder->categories_count ?? 0) }}
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

            @if(! $hasSearch)
                <p class="mt-4 text-xs text-gray-400">
                    {{ $folders->count() }} {{ \Illuminate\Support\Str::plural('carpeta', $folders->count()) }} encontradas
                </p>
            @endif
        @elseif(! $hasSearch)
            <div class="mt-8 rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-gray-100">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M3 7a2 2 0 012-2h4l2 2h8a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"/>
                    </svg>
                </div>
                <p class="text-sm text-gray-500">No hay carpetas disponibles.</p>
            </div>
        @endif

        {{-- SECCIÓN: CATEGORÍAS --}}
        @if($hasSearch && $matchingCategories->isNotEmpty())
            <h2 class="mt-8 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Categorías ({{ $matchingCategories->count() }})
            </h2>
            <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                @foreach($matchingCategories as $category)
                    <a href="{{ route('documents.categories.show', $category) }}"
                       class="group flex items-center gap-3 px-5 py-3.5 hover:bg-blue-50 transition">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-50 group-hover:bg-amber-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-amber-600"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M7 7h.01M7 3h5l2 2h5a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-[#1A428A] transition truncate">
                                {{ $category->name }}
                            </p>
                            @if($category->parent)
                                <p class="text-xs text-gray-400 truncate">{{ $category->parent->name }}</p>
                            @endif
                        </div>
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-[#1A428A] transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif

        {{-- SECCIÓN: DOCUMENTOS --}}
        @if($hasSearch && $matchingDocuments->isNotEmpty())
            <h2 class="mt-8 mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">
                Documentos ({{ $matchingDocuments->count() }})
            </h2>
            <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 bg-white shadow-sm overflow-hidden">
                @foreach($matchingDocuments as $document)
                    @php $category = $document->folder; @endphp
                    <a href="{{ route('documents.document.show', [$category, $document]) }}"
                       class="group flex items-center gap-3 px-5 py-3.5 hover:bg-blue-50 transition">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-green-50 group-hover:bg-green-100 transition">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-600"
                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-gray-800 group-hover:text-[#1A428A] transition truncate">
                                {{ $document->name }}
                            </p>
                            <p class="text-xs text-gray-400 truncate">
                                @if($category?->parent){{ $category->parent->name }} &rsaquo; @endif
                                {{ $category?->name }}
                            </p>
                        </div>
                        @if($document->currentVersion)
                            <span class="shrink-0 inline-flex items-center rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                v{{ $document->currentVersion->version_number }}
                            </span>
                        @else
                            <span class="shrink-0 inline-flex items-center rounded-full bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-500">
                                Sin archivo
                            </span>
                        @endif
                        <svg xmlns="http://www.w3.org/2000/svg"
                             class="h-4 w-4 shrink-0 text-gray-300 group-hover:text-[#1A428A] transition"
                             fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                @endforeach
            </div>
        @endif

    @endif

</x-layouts.vigia>
