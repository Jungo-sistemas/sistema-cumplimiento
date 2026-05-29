<x-layouts.vigia title="Usuarios del Sistema">

    <div x-data="{
        showForm: false,
        selectedRole: '{{ old('role_id', '') }}',
        superadminId: '{{ $roles->where('slug', 'superadmin')->first()?->id }}',
        editOpen: false,
        editUserId: null,
        editUserName: '',
        editRole: '',
        editGroup: '',
        editCompany: '',
        openEdit(id, name, roleId, groupId, companyId) {
            this.editUserId = id;
            this.editUserName = name;
            this.editRole = String(roleId ?? '');
            this.editGroup = String(groupId ?? '');
            this.editCompany = String(companyId ?? '');
            this.editOpen = true;
        }
    }">

        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-[#1A428A]">Usuarios del Sistema</h1>
            <button
                type="button"
                @click="showForm = !showForm"
                class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] text-sm"
            >
                + Nuevo usuario
            </button>
        </div>

        {{-- Flash messages --}}
        @if(session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                {{ session('error') }}
            </div>
        @endif

        {{-- Inline create form --}}
        <div x-show="showForm" x-transition class="mb-6 rounded-xl border border-gray-200 bg-white p-5 shadow-sm" style="display: none;">
            <h2 class="mb-4 font-semibold text-gray-800">Nuevo usuario</h2>
            <form method="POST" action="{{ route('superadmin.users.store') }}">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">

                    {{-- Nombre --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_name">
                            Nombre <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="user_name"
                            type="text"
                            name="name"
                            value="{{ old('name') }}"
                            required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                            placeholder="Nombre completo"
                        >
                        @error('name')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Email --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_email">
                            Correo electrónico <span class="text-red-500">*</span>
                        </label>
                        <input
                            id="user_email"
                            type="email"
                            name="email"
                            value="{{ old('email') }}"
                            required
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                            placeholder="correo@ejemplo.com"
                        >
                        @error('email')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Rol --}}
                    <div>
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_role_id">
                            Rol <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="user_role_id"
                            name="role_id"
                            required
                            x-model="selectedRole"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                        >
                            <option value="">Seleccionar rol…</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->id }}"
                                    data-slug="{{ $role->slug }}"
                                    {{ old('role_id') == $role->id ? 'selected' : '' }}>
                                    {{ $role->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('role_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Grupo (hidden when superadmin) --}}
                    <div x-show="selectedRole !== '{{ $roles->where('slug', 'superadmin')->first()?->id }}'">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_group_id">
                            Grupo
                        </label>
                        <select
                            id="user_group_id"
                            name="group_id"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                        >
                            <option value="">Seleccionar grupo…</option>
                            @foreach($groups as $group)
                                <option value="{{ $group->id }}" {{ old('group_id') == $group->id ? 'selected' : '' }}>
                                    {{ $group->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('group_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Empresa (hidden when superadmin) --}}
                    <div x-show="selectedRole !== '{{ $roles->where('slug', 'superadmin')->first()?->id }}'">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_company_id">
                            Empresa
                        </label>
                        <select
                            id="user_company_id"
                            name="company_id"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                        >
                            <option value="">Seleccionar empresa…</option>
                            @foreach($companies as $company)
                                <option value="{{ $company->id }}" {{ old('company_id') == $company->id ? 'selected' : '' }}>
                                    {{ $company->name }} ({{ $company->group?->name ?? '—' }})
                                </option>
                            @endforeach
                        </select>
                        @error('company_id')
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Superadmin note --}}
                    <div x-show="selectedRole === '{{ $roles->where('slug', 'superadmin')->first()?->id }}'" class="sm:col-span-2">
                        <p class="rounded-md bg-purple-50 border border-purple-200 px-3 py-2 text-sm text-purple-700">
                            El Superadministrador tiene acceso global — no requiere empresa ni grupo.
                        </p>
                    </div>

                </div>

                <div class="mt-4 flex gap-2">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] text-sm"
                    >
                        Invitar usuario
                    </button>
                    <button
                        type="button"
                        @click="showForm = false"
                        class="px-4 py-2 rounded-md border border-gray-300 text-gray-600 font-semibold hover:bg-gray-50 text-sm"
                    >
                        Cancelar
                    </button>
                </div>
            </form>
        </div>

        {{-- Edit user modal --}}
        <div x-show="editOpen" x-transition.opacity
             class="fixed inset-0 z-50 flex items-center justify-center bg-black/40"
             style="display:none;"
             @keydown.escape.window="editOpen = false">
            <div class="w-full max-w-lg rounded-xl bg-white shadow-xl p-6" @click.stop>
                <h2 class="mb-4 text-lg font-semibold text-gray-800">
                    Editar usuario: <span class="text-[#1A428A]" x-text="editUserName"></span>
                </h2>

                <template x-if="editUserId">
                    <form method="POST" :action="`/superadmin/users/${editUserId}`">
                        @csrf
                        @method('PATCH')

                        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">

                            {{-- Rol --}}
                            <div class="sm:col-span-2">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Rol <span class="text-red-500">*</span></label>
                                <select name="role_id" x-model="editRole" required
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]">
                                    <option value="">Seleccionar rol…</option>
                                    @foreach($roles as $role)
                                        <option value="{{ $role->id }}" data-slug="{{ $role->slug }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Grupo --}}
                            <div x-show="editRole !== superadminId">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Grupo</label>
                                <select name="group_id" x-model="editGroup"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]">
                                    <option value="">Sin grupo</option>
                                    @foreach($groups as $group)
                                        <option value="{{ $group->id }}">{{ $group->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Empresa --}}
                            <div x-show="editRole !== superadminId">
                                <label class="mb-1 block text-sm font-medium text-gray-700">Empresa</label>
                                <select name="company_id" x-model="editCompany"
                                    class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]">
                                    <option value="">Sin empresa</option>
                                    @foreach($companies as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }} ({{ $company->group?->name ?? '—' }})</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Nota superadmin --}}
                            <div x-show="editRole === superadminId" class="sm:col-span-2">
                                <p class="rounded-md bg-purple-50 border border-purple-200 px-3 py-2 text-sm text-purple-700">
                                    El Superadministrador tiene acceso global — empresa y grupo se limpiarán automáticamente.
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex gap-2 justify-end">
                            <button type="button" @click="editOpen = false"
                                class="px-4 py-2 rounded-md border border-gray-300 text-gray-600 text-sm font-semibold hover:bg-gray-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                Guardar cambios
                            </button>
                        </div>
                    </form>
                </template>
            </div>
        </div>

        {{-- Users table --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Nombre</th>
                            <th class="px-5 py-3">Correo</th>
                            <th class="px-5 py-3">Rol</th>
                            <th class="px-5 py-3">Empresa</th>
                            <th class="px-5 py-3">Grupo</th>
                            <th class="px-5 py-3">Alcance</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($users as $user)
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">{{ $user->name }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $user->email }}</td>
                                <td class="px-5 py-3">
                                    @php $slug = $user->role?->slug; @endphp
                                    @if($slug === 'superadmin')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-purple-100 text-purple-700">{{ $user->role->name }}</span>
                                    @elseif($slug === 'admin')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-blue-100 text-blue-700">{{ $user->role->name }}</span>
                                    @elseif($slug === 'operative')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">{{ $user->role->name }}</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">{{ $user->role?->name ?? '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-gray-600">{{ $user->company?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-600">{{ $user->group?->name ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-500 font-mono text-xs">{{ $user->scope_level }}</td>
                                <td class="px-5 py-3 text-center">
                                    @if($user->status === 'active')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Activo</span>
                                    @elseif($user->status === 'invited')
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-yellow-100 text-yellow-700">Invitado</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">{{ $user->status }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($user->id !== auth()->id())
                                        <div class="inline-flex gap-2">
                                            <button type="button"
                                                @click="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->role_id }}', '{{ $user->group_id ?? '' }}', '{{ $user->company_id ?? '' }}')"
                                                class="px-3 py-1.5 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                                                Editar
                                            </button>
                                            <form method="POST" action="{{ route('superadmin.users.destroy', $user) }}" class="inline"
                                                onsubmit="return confirm('¿Eliminar al usuario «{{ $user->name }}»?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                                    Eliminar
                                                </button>
                                            </form>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Tú</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-5 py-6 text-center text-gray-400">No hay usuarios registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($users->hasPages())
                <div class="border-t border-gray-100 px-5 py-3">
                    {{ $users->links() }}
                </div>
            @endif
        </div>

    </div>

</x-layouts.vigia>
