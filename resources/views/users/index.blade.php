<x-layouts.vigia :title="'Usuarios'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Usuarios</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-semibold text-[#1A428A]">Usuarios</h1>
                <p class="text-sm text-gray-500">Administra los accesos de los usuarios.</p>
            </div>

            @if(auth()->user()->isAdmin())
                <a href="{{ route('users.create') }}"
                   class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                    Agregar usuario
                </a>
            @endif
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-700">
                {{ session('success') }}
            </div>
        @endif

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">Nombre</th>
                        <th class="text-left px-4 py-3 font-semibold">Correo</th>
                        <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                        <th class="text-left px-4 py-3 font-semibold">Rol</th>
                        <th class="text-left px-4 py-3 font-semibold">Alcance</th>
                        <th class="text-left px-4 py-3 font-semibold">Estado</th>

                        @if(auth()->user()->isAdmin())
                            <th class="text-right px-4 py-3 font-semibold">Acciones</th>
                        @endif
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-200">
                    @forelse($users as $user)
                        <tr>
                            <td class="px-4 py-3">{{ $user->name }}</td>
                            <td class="px-4 py-3">{{ $user->email }}</td>
                            <td class="px-4 py-3">{{ $user->company->name ?? '-' }}</td>
                            <td class="px-4 py-3">{{ $user->role->name ?? '-' }}</td>

                            <td class="px-4 py-3">
                                @if($user->scope_level === 'group')
                                    <span class="text-xs px-3 py-1 rounded border bg-blue-50 text-blue-700 border-blue-200">
                                        Grupo
                                    </span>
                                @else
                                    <span class="text-xs px-3 py-1 rounded border bg-gray-50 text-gray-700 border-gray-200">
                                        Empresa
                                    </span>
                                @endif
                            </td>

                            <td class="px-4 py-3">
                                @if($user->status === 'active')
                                    <span class="text-xs px-3 py-1 rounded border bg-green-50 text-green-700 border-green-200">
                                        Activo
                                    </span>
                                @elseif($user->status === 'invited')
                                    <span class="text-xs px-3 py-1 rounded border bg-yellow-50 text-yellow-700 border-yellow-200">
                                        Invitado
                                    </span>
                                @else
                                    <span class="text-xs px-3 py-1 rounded border bg-gray-50 text-gray-700 border-gray-200">
                                        {{ $user->status }}
                                    </span>
                                @endif
                            </td>

                            @if(auth()->user()->isAdmin())
                                <td class="px-4 py-3 text-right">
                                    @if($user->id !== auth()->id())
                                        <form method="POST"
                                              action="{{ route('users.destroy', $user) }}"
                                              onsubmit="return confirm('¿Seguro que quieres eliminar este usuario?');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-[#DB0000] text-white text-sm font-semibold hover:bg-red-700">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400">Tú</span>
                                    @endif
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-4 py-8 text-center text-gray-500">
                                No hay usuarios registrados todavía.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $users->links() }}
        </div>
    </div>
</x-layouts.vigia>