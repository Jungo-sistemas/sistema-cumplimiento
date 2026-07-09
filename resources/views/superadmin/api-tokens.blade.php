<x-layouts.vigia title="API Tokens">

    <x-slot name="breadcrumb">
        <a href="{{ route('superadmin.dashboard') }}" class="text-blue-600 hover:underline">Superadmin</a>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">API Tokens</span>
    </x-slot>

    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-semibold text-[#1A428A]">API Tokens</h1>
            <p class="mt-1 text-sm text-gray-500">
                Tokens de solo lectura para consultar catálogos de tipos de activo y requerimientos.
            </p>
        </div>
        <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-700">Superadministrador</span>
    </div>

    {{-- Token generado --}}
    @if(session('generated_token'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-semibold text-green-800 mb-2">Token generado — cópialo ahora:</p>
            <div class="flex items-center gap-2">
                <code id="new-token" class="flex-1 block bg-white border border-green-300 rounded px-3 py-2 text-xs font-mono text-gray-800 break-all select-all">{{ session('generated_token') }}</code>
                <button onclick="navigator.clipboard.writeText(document.getElementById('new-token').innerText)"
                        class="shrink-0 px-3 py-2 rounded-md bg-green-600 text-white text-xs font-semibold hover:bg-green-700">
                    Copiar
                </button>
            </div>
            <p class="mt-2 text-xs text-green-700">Este token no se volverá a mostrar.</p>
        </div>
    @endif

    @if(session('success') && ! session('generated_token'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- Fila superior: Nuevo token + Endpoints --}}
    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="bg-white border rounded-lg shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-4">Nuevo token</h2>
            <form method="POST" action="{{ route('superadmin.api-tokens.store') }}" class="space-y-4">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nombre / descripción</label>
                        <input type="text" name="name" value="{{ old('name') }}"
                               placeholder="Ej: Sistema de inventario"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                        @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Grupo (referencia)</label>
                        <select name="group_id"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                            <option value="">Selecciona un grupo</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" @selected(old('group_id') == $group->id)>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('group_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <button type="submit"
                        class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    Generar token
                </button>
            </form>
        </div>

        <div class="bg-white border rounded-lg shadow-sm p-5">
            <h2 class="text-sm font-semibold text-gray-700 mb-3">Endpoints</h2>
            <div class="space-y-2 text-xs font-mono">
                <div class="flex items-start gap-2">
                    <span class="shrink-0 inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">GET</span>
                    <span class="text-gray-600 break-all">/api/v1/asset-types</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="shrink-0 inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">GET</span>
                    <span class="text-gray-600 break-all">/api/v1/asset-types/{slug}/requirements</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="shrink-0 inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">GET</span>
                    <span class="text-gray-600 break-all">/api/v1/companies</span>
                </div>
                <div class="flex items-start gap-2">
                    <span class="shrink-0 inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5">GET</span>
                    <span class="text-gray-600 break-all">/api/v1/companies/{company}/assets</span>
                </div>
            </div>
            <div class="mt-3 rounded bg-gray-50 border px-3 py-2 text-xs font-mono text-gray-500 break-all">
                Authorization: Bearer &lt;token&gt;
            </div>
            <p class="mt-3 text-xs text-gray-400">
                <span class="font-semibold">asset-types</span> devuelve todos los tipos y requerimientos sin filtrar por empresa ni grupo.
                <span class="font-semibold">companies</span> y <span class="font-semibold">companies/{company}/assets</span> solo devuelven información de empresas que pertenezcan al mismo grupo del token (usa <span class="font-mono">?per_page=</span> y <span class="font-mono">?status=active|inactive</span> como filtros opcionales en assets).
            </p>
        </div>

    </div>

    {{-- Tabla de tokens activos (ancho completo) --}}
    <div class="mt-6" x-data="{ search: '' }">
            <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b bg-gray-50 flex items-center justify-between gap-4">
                    <h2 class="text-sm font-semibold text-gray-700 shrink-0">
                        Tokens activos
                        <span class="ml-1 text-xs font-normal text-gray-400">({{ $tokens->count() }})</span>
                    </h2>
                    <div class="relative w-full max-w-xs">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5 text-gray-400" fill="none"
                                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                      d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
                            </svg>
                        </span>
                        <input type="text" x-model="search" placeholder="Buscar por nombre o grupo…"
                               class="w-full rounded-md border-gray-300 text-xs pl-8 py-1.5 focus:border-[#1A428A] focus:ring-[#1A428A]">
                    </div>
                </div>

                @if($tokens->isEmpty())
                    <div class="px-5 py-12 text-center text-sm text-gray-400">
                        No hay tokens generados.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs border-b">
                            <tr>
                                <th class="text-left px-5 py-3 font-medium">Nombre</th>
                                <th class="text-left px-5 py-3 font-medium">Grupo</th>
                                <th class="text-left px-5 py-3 font-medium">Último uso</th>
                                <th class="text-left px-5 py-3 font-medium">Creado</th>
                                <th class="px-5 py-3"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tokens as $token)
                                <tr class="border-t hover:bg-gray-50"
                                    x-show="search === '' ||
                                             '{{ strtolower($token->name) }}'.includes(search.toLowerCase()) ||
                                             '{{ strtolower($token->group->name ?? '') }}'.includes(search.toLowerCase())">
                                    <td class="px-5 py-3 font-medium text-gray-800">{{ $token->name }}</td>
                                    <td class="px-5 py-3">
                                        <span class="inline-flex items-center rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-medium text-blue-700">
                                            {{ $token->group->name ?? '—' }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-xs">
                                        @if($token->last_used_at)
                                            <span title="{{ $token->last_used_at->format('d/m/Y H:i') }}">
                                                {{ $token->last_used_at->diffForHumans() }}
                                            </span>
                                        @else
                                            <span class="text-gray-300">Nunca</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">
                                        {{ $token->created_at->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST"
                                              action="{{ route('superadmin.api-tokens.destroy', $token) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('¿Revocar «{{ addslashes($token->name) }}»?')"
                                                    class="px-3 py-1.5 rounded border border-red-200 text-red-600 text-xs hover:bg-red-50">
                                                Revocar
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
    </div>

</x-layouts.vigia>
