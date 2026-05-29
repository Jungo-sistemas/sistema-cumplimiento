<x-layouts.vigia :title="'Agregar usuario'">
    <x-slot name="breadcrumb">
        <a href="{{ route('users.index') }}" class="text-gray-600 hover:underline">
            Usuarios
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Agregar usuario</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 max-w-3xl">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Agregar usuario</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4">
                <ul class="list-disc list-inside text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Nombre
                </label>
                <input
                    type="text"
                    name="name"
                    value="{{ old('name') }}"
                    class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Correo
                </label>
                <input
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    required
                >
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Empresa
                </label>
                @if($singleCompany)
                    <input type="hidden" name="company_id" value="{{ $singleCompany->id }}">
                    <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                        {{ $singleCompany->name }}
                    </div>
                @else
                    <select
                        name="company_id"
                        class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                        <option value="">Selecciona una empresa</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id') == $company->id)>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                    Rol
                </label>
                <select
                    name="role_id"
                    class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    required
                >
                    <option value="">Selecciona un rol</option>
                    @foreach($roles as $role)
                        <option value="{{ $role->id }}" @selected(old('role_id') == $role->id)>
                            @switch($role->slug)
                                @case('admin') Administrador @break
                                @case('operative') Operativo @break
                                @case('readonly') Solo lectura @break
                                @default {{ $role->name }}
                            @endswitch
                        </option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-400">
                    @if($singleCompany)
                        Operativo: puede gestionar activos y tareas. Solo lectura: solo consulta.
                    @else
                        Administrador: gestiona usuarios y configuración del grupo. Operativo: gestiona activos y tareas. Solo lectura: solo consulta.
                    @endif
                </p>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ route('users.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50">
                    Cancelar
                </a>

                <button type="submit"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                    Enviar invitación
                </button>
            </div>
        </form>
    </div>
</x-layouts.vigia>