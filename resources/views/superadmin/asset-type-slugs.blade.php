<x-layouts.vigia title="IDs de tipos de activo">

    <x-slot name="breadcrumb">
        <a href="{{ route('superadmin.dashboard') }}" class="text-blue-600 hover:underline">Superadmin</a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">IDs de tipos de activo</span>
    </x-slot>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">IDs de tipos de activo</h1>
            <p class="mt-1 text-sm text-gray-500">
                Configura el identificador único que se usará en la API para consultar requerimientos por tipo.
            </p>
        </div>
        <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-700">Superadministrador</span>
    </div>

    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Referencia de uso --}}
    <div class="mt-4 rounded-lg border border-blue-100 bg-blue-50 p-4 text-xs text-blue-700 font-mono">
        GET /api/v1/asset-types/<strong>{slug}</strong>/requirements
        &nbsp;→&nbsp;
        <span class="text-blue-500">Authorization: Bearer &lt;token&gt;</span>
    </div>

    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden" x-data="{ search: '' }">
        <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between gap-4">
            <h2 class="text-sm font-semibold text-gray-700 shrink-0">
                Tipos de activo
                <span class="ml-1 text-xs font-normal text-gray-400">({{ $types->count() }})</span>
            </h2>
            <div class="relative w-full max-w-xs">
                <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                    </svg>
                </span>
                <input type="text" x-model="search" placeholder="Buscar tipo o empresa…"
                       class="w-full rounded-md border-gray-300 text-xs pl-8 py-1.5 focus:border-[#1A428A] focus:ring-[#1A428A]">
            </div>
        </div>

        <table class="w-full text-sm">
            <thead class="bg-gray-50 text-gray-500 text-xs border-b">
                <tr>
                    <th class="text-left px-5 py-3 font-medium">Tipo de activo</th>
                    <th class="text-left px-5 py-3 font-medium">Empresa</th>
                    <th class="text-left px-5 py-3 font-medium w-64">Slug (ID para API)</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($types as $type)
                    <tr class="border-t hover:bg-gray-50"
                        x-show="search === '' ||
                                 '{{ strtolower($type->name) }}'.includes(search.toLowerCase()) ||
                                 '{{ strtolower($type->company->name ?? '') }}'.includes(search.toLowerCase()) ||
                                 '{{ strtolower($type->slug ?? '') }}'.includes(search.toLowerCase())">
                        <td class="px-5 py-3 font-medium text-gray-800">{{ $type->name }}</td>
                        <td class="px-5 py-3 text-gray-500 text-xs">{{ $type->company->name ?? '—' }}</td>
                        <td class="px-5 py-3">
                            <form method="POST"
                                  action="{{ route('superadmin.asset-type-slugs.update', $type) }}"
                                  class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="text"
                                       name="slug"
                                       value="{{ $type->slug }}"
                                       placeholder="ej: vehiculo"
                                       pattern="[a-z0-9\-]+"
                                       title="Solo minúsculas, números y guiones"
                                       class="w-full rounded-md border-gray-300 text-xs font-mono focus:border-[#1A428A] focus:ring-[#1A428A] py-1.5">
                                <button type="submit"
                                        class="shrink-0 px-3 py-1.5 rounded-md bg-[#1A428A] text-white text-xs font-semibold hover:bg-[#15356d]">
                                    Guardar
                                </button>
                            </form>
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-400 font-mono whitespace-nowrap">
                            @if($type->slug)
                                <span class="text-green-600">/api/v1/asset-types/{{ $type->slug }}/requirements</span>
                            @else
                                <span class="text-red-400">Sin slug</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

</x-layouts.vigia>
