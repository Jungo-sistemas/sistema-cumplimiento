<x-layouts.vigia :title="'Usuarios'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Usuarios</span>
    </x-slot>

    <script>
    function editUserData() {
        return {
            editOpen: false,
            editUserId: null,
            editUserName: '',
            editRole: '',
            editGroup: '',
            editModuleAccess: 'all',
            editPosition: '',
            adminRoleId: '{{ $adminRoleId ?? '' }}',
            positionsByGroup: @json($positionsByGroup),
            get isAdminEdit() { return this.editRole === this.adminRoleId; },
            get editPositions() {
                if (!this.editGroup) return [];
                return this.positionsByGroup[this.editGroup] ?? [];
            },
            openEdit(id, name, roleId, groupId, moduleAccess, positionId) {
                this.editUserId = id;
                this.editUserName = name;
                this.editRole = String(roleId ?? '');
                this.editGroup = String(groupId ?? '');
                this.editModuleAccess = moduleAccess || 'all';
                this.editPosition = String(positionId ?? '');
                this.editOpen = true;
            }
        };
    }
    </script>

    <div x-data="editUserData()">

    {{-- Edit user modal --}}
    <div x-show="editOpen"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-50 flex items-end sm:items-center justify-center bg-black/50 px-4"
         style="display:none;"
         @click.self="editOpen = false"
         @keydown.escape.window="editOpen = false">

        <div x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
             x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
             class="w-full max-w-lg rounded-2xl bg-white shadow-2xl overflow-hidden">

            {{-- Header --}}
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 bg-gray-50">
                <div>
                    <p class="text-xs font-medium text-gray-400 uppercase tracking-wider mb-0.5">Editar usuario</p>
                    <h2 class="text-base font-semibold text-gray-900" x-text="editUserName"></h2>
                </div>
                <button type="button" @click="editOpen = false"
                    class="flex items-center justify-center w-8 h-8 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-200 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <template x-if="editUserId">
                <form method="POST" :action="`/users/${editUserId}`">
                    @csrf
                    @method('PATCH')

                    <div class="px-6 py-5 space-y-4">

                        {{-- Rol --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Rol <span class="text-red-500">*</span>
                            </label>
                            <select name="role_id" x-model="editRole" required
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                <option value="">Seleccionar rol…</option>
                                @foreach($roles as $role)
                                    <option value="{{ $role->id }}">{{ $role->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        {{-- Puesto --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Puesto</label>
                            <select name="job_position_id" x-model="editPosition"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                <option value="">— Sin asignar —</option>
                                <template x-for="pos in editPositions" :key="pos.id">
                                    <option :value="pos.id" x-text="pos.name" :selected="editPosition == pos.id"></option>
                                </template>
                            </select>
                            <p x-show="editGroup === '' && editPositions.length === 0"
                               class="mt-1 text-xs text-gray-400">Sin puestos disponibles para este usuario.</p>
                        </div>

                        {{-- Vista predeterminada / Módulos --}}
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                <span x-text="isAdminEdit ? 'Vista predeterminada' : 'Módulos visibles'"></span>
                            </label>
                            <select name="module_access" x-model="editModuleAccess"
                                class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                <option value="all">Ambos módulos</option>
                                <option value="cumplimiento">Solo Cumplimiento</option>
                                <option value="procesos">Solo Procesos</option>
                            </select>
                            <p class="mt-1 text-xs text-gray-400"
                               x-text="isAdminEdit
                                   ? 'Define el módulo de inicio (el admin accede a todo).'
                                   : 'Define a qué módulo tendrá acceso este usuario.'">
                            </p>
                        </div>

                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center justify-end gap-2 px-6 py-4 border-t border-gray-100 bg-gray-50">
                        <button type="button" @click="editOpen = false"
                            class="px-4 py-2 rounded-lg border border-gray-300 text-gray-600 text-sm font-medium hover:bg-gray-100 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                            class="px-5 py-2 rounded-lg bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d] transition-colors shadow-sm">
                            Guardar cambios
                        </button>
                    </div>
                </form>
            </template>
        </div>
    </div>

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
                                        <div class="inline-flex gap-2">
                                            <button type="button"
                                                @click="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->role_id }}', '{{ $user->group_id ?? '' }}', '{{ $user->module_access ?? 'all' }}', '{{ $user->jobPositions->first()?->id ?? '' }}')"
                                                class="px-3 py-1.5 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                                Editar
                                            </button>
                                            @if(!$user->isAdmin() || auth()->user()->isSuperAdmin())
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
                                            @endif
                                        </div>
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

    </div>
</x-layouts.vigia>