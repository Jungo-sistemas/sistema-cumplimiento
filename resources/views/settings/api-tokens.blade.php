<x-layouts.vigia title="API Tokens">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Configuración</span>
        <span class="mx-2 text-gray-400">/</span>
        <span class="text-gray-700 font-medium">API Tokens</span>
    </x-slot>

    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">API Tokens</h1>
    </div>

    <p class="mt-1 text-sm text-gray-500">
        Genera tokens de solo lectura para que sistemas externos puedan consultar
        los tipos de activo y sus requerimientos.
    </p>

    {{-- Token generado: mostrar una sola vez --}}
    @if(session('generated_token'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-4">
            <p class="text-sm font-semibold text-green-800 mb-2">Token generado — cópialo ahora:</p>
            <div class="flex items-center gap-2">
                <code class="flex-1 block bg-white border border-green-300 rounded px-3 py-2 text-xs font-mono text-gray-800 break-all select-all">
                    {{ session('generated_token') }}
                </code>
                <button onclick="navigator.clipboard.writeText('{{ session('generated_token') }}')"
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

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">

        {{-- Formulario nuevo token --}}
        <div class="lg:col-span-1">
            <div class="bg-white border rounded-lg shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-4">Nuevo token</h2>

                <form method="POST" action="{{ route('settings.api-tokens.store') }}" class="space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Nombre / descripción</label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               placeholder="Ej: Sistema de inventario"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                        <select name="company_id"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                            <option value="">Selecciona una empresa</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                    {{ $company->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    <button type="submit"
                            class="w-full px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                        Generar token
                    </button>
                </form>
            </div>

            {{-- Referencia de endpoints --}}
            <div class="mt-4 bg-white border rounded-lg shadow-sm p-5">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Endpoints disponibles</h2>
                <div class="space-y-3 text-xs font-mono">
                    <div>
                        <span class="inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5 mr-1">GET</span>
                        <span class="text-gray-600">/api/v1/asset-types</span>
                    </div>
                    <div>
                        <span class="inline-block bg-blue-100 text-blue-700 rounded px-1.5 py-0.5 mr-1">GET</span>
                        <span class="text-gray-600">/api/v1/asset-types/{id}/requirements</span>
                    </div>
                </div>
                <p class="mt-3 text-xs text-gray-400">
                    Header: <code class="bg-gray-100 px-1 rounded">Authorization: Bearer &lt;token&gt;</code>
                </p>
            </div>
        </div>

        {{-- Lista de tokens activos --}}
        <div class="lg:col-span-2">
            <div class="bg-white border rounded-lg shadow-sm overflow-hidden">
                <div class="px-5 py-3 border-b bg-gray-50">
                    <h2 class="text-sm font-semibold text-gray-700">Tokens activos</h2>
                </div>

                @if($tokens->isEmpty())
                    <div class="px-5 py-10 text-center text-sm text-gray-400">
                        No hay tokens generados.
                    </div>
                @else
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50 text-gray-500 text-xs">
                            <tr>
                                <th class="text-left px-5 py-2 font-medium">Nombre</th>
                                <th class="text-left px-5 py-2 font-medium">Empresa</th>
                                <th class="text-left px-5 py-2 font-medium">Último uso</th>
                                <th class="text-left px-5 py-2 font-medium">Creado</th>
                                <th class="px-5 py-2"></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($tokens as $token)
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-5 py-3 font-medium text-gray-800">{{ $token->name }}</td>
                                    <td class="px-5 py-3 text-gray-500">{{ $token->company->name }}</td>
                                    <td class="px-5 py-3 text-gray-400 text-xs">
                                        {{ $token->last_used_at?->diffForHumans() ?? 'Nunca' }}
                                    </td>
                                    <td class="px-5 py-3 text-gray-400 text-xs">
                                        {{ $token->created_at->format('d/m/Y') }}
                                    </td>
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST"
                                              action="{{ route('settings.api-tokens.destroy', $token) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit"
                                                    onclick="return confirm('¿Revocar este token?')"
                                                    class="px-3 py-1 rounded border border-red-200 text-red-600 text-xs hover:bg-red-50">
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

    </div>

</x-layouts.vigia>
