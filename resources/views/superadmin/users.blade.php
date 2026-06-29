<x-layouts.vigia title="Usuarios del Sistema">

    <script>
    function usersPageData() {
        return {
            showForm: {{ old('role_id') ? 'true' : 'false' }},
            selectedRole: '{{ old('role_id', '') }}',
            selectedGroup: '{{ old('group_id', '') }}',
            selectedModuleAccess: '{{ old('module_access', 'all') }}',
            selectedPosition: '{{ old('job_position_id', '') }}',
            superadminId: '{{ $roles->where('slug', 'superadmin')->first()?->id }}',
            adminId: '{{ $roles->where('slug', 'admin')->first()?->id }}',
            allPositions: @json($jobPositions),
            editOpen: false,
            editUserId: null,
            editUserName: '',
            editRole: '',
            editGroup: '',
            editCompany: '',
            editModuleAccess: 'all',
            editPosition: '',
            get isSuperadminCreate() { return this.selectedRole === this.superadminId; },
            get isAdminCreate() { return this.selectedRole === this.adminId; },
            get createPositions() {
                if (!this.selectedGroup) return [];
                return this.allPositions.filter(p => String(p.group_id) === String(this.selectedGroup));
            },
            get isSuperadminEdit() { return this.editRole === this.superadminId; },
            get isAdminEdit() { return this.editRole === this.adminId; },
            get editPositions() {
                if (!this.editGroup) return [];
                return this.allPositions.filter(p => String(p.group_id) === String(this.editGroup));
            },
            openEdit(id, name, roleId, groupId, companyId, moduleAccess, positionId) {
                this.editUserId = id;
                this.editUserName = name;
                this.editRole = String(roleId ?? '');
                this.editGroup = String(groupId ?? '');
                this.editCompany = String(companyId ?? '');
                this.editModuleAccess = moduleAccess || 'all';
                this.editPosition = String(positionId ?? '');
                this.editOpen = true;
            }
        };
    }
    </script>

    <div x-data="usersPageData()">

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
                    <div x-show="!isSuperadminCreate">
                        <label class="mb-1 block text-sm font-medium text-gray-700" for="user_group_id">
                            Grupo
                        </label>
                        <select
                            id="user_group_id"
                            name="group_id"
                            x-model="selectedGroup"
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
                    <div x-show="!isSuperadminCreate">
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

                    {{-- Puesto (hidden when superadmin) --}}
                    <div x-show="!isSuperadminCreate">
                        <label class="mb-1 block text-sm font-medium text-gray-700">Puesto</label>
                        <select name="job_position_id" x-model="selectedPosition"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]">
                            <option value="">— Sin asignar —</option>
                            <template x-for="pos in createPositions" :key="pos.id">
                                <option :value="pos.id" x-text="pos.name"></option>
                            </template>
                        </select>
                        <p x-show="selectedGroup === '' && selectedRole !== '' && !isSuperadminCreate"
                           class="mt-1 text-xs text-yellow-600">Selecciona un grupo para ver los puestos.</p>
                    </div>

                    {{-- Vista predeterminada / Módulos (hidden when superadmin) --}}
                    <div x-show="!isSuperadminCreate">
                        <label class="mb-1 block text-sm font-medium text-gray-700">
                            <span x-text="isAdminCreate ? 'Vista predeterminada' : 'Módulos visibles'"></span>
                        </label>
                        <select name="module_access" x-model="selectedModuleAccess"
                            class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]">
                            <option value="all">Ambos módulos</option>
                            <option value="cumplimiento">Solo Cumplimiento</option>
                            <option value="procesos">Solo Procesos</option>
                        </select>
                        <p class="mt-1 text-xs text-gray-400"
                           x-text="isAdminCreate
                               ? 'Define el módulo de inicio (el admin accede a todo).'
                               : 'Define a qué módulo tendrá acceso este usuario.'">
                        </p>
                    </div>

                    {{-- Superadmin note --}}
                    <div x-show="isSuperadminCreate" class="sm:col-span-2">
                        <p class="rounded-md bg-purple-50 border border-purple-200 px-3 py-2 text-sm text-purple-700">
                            El Superadministrador tiene acceso global — no requiere empresa, grupo ni puesto.
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

                {{-- Modal header --}}
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
                    <form method="POST" :action="`/superadmin/users/${editUserId}`">
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
                                        <option value="{{ $role->id }}" data-slug="{{ $role->slug }}">{{ $role->name }}</option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Grupo + Empresa (ocultos para superadmin) --}}
                            <div x-show="!isSuperadminEdit"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0"
                                 class="grid grid-cols-2 gap-3">

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Grupo</label>
                                    <select name="group_id" x-model="editGroup"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                        <option value="">Sin grupo</option>
                                        @foreach($groups as $group)
                                            <option value="{{ $group->id }}">{{ $group->name }}</option>
                                        @endforeach
                                    </select>
                                </div>

                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Empresa</label>
                                    <select name="company_id" x-model="editCompany"
                                        class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                        <option value="">Sin empresa</option>
                                        @foreach($companies as $company)
                                            <option value="{{ $company->id }}">{{ $company->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            {{-- Puesto (oculto para superadmin) --}}
                            <div x-show="!isSuperadminEdit"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0">
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Puesto</label>
                                <select name="job_position_id" x-model="editPosition"
                                    class="w-full rounded-lg border border-gray-300 px-3 py-2.5 text-sm bg-white focus:border-[#1A428A] focus:outline-none focus:ring-2 focus:ring-[#1A428A]/20 transition-colors">
                                    <option value="">— Sin asignar —</option>
                                    <template x-for="pos in editPositions" :key="pos.id">
                                        <option :value="pos.id" x-text="pos.name" :selected="editPosition == pos.id"></option>
                                    </template>
                                </select>
                                <p x-show="editGroup === ''" class="mt-1 text-xs text-yellow-600">Selecciona un grupo para ver los puestos.</p>
                            </div>

                            {{-- Vista / Módulos (oculto para superadmin) --}}
                            <div x-show="!isSuperadminEdit"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 -translate-y-1"
                                 x-transition:enter-end="opacity-100 translate-y-0">
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

                            {{-- Nota superadmin --}}
                            <div x-show="isSuperadminEdit"
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100"
                                 class="flex items-start gap-2.5 rounded-lg bg-purple-50 border border-purple-200 px-4 py-3">
                                <svg class="w-4 h-4 text-purple-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                </svg>
                                <p class="text-sm text-purple-700">Acceso global — empresa, grupo y puesto se limpian automáticamente.</p>
                            </div>

                        </div>

                        {{-- Modal footer --}}
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
                                                @click="openEdit({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->role_id }}', '{{ $user->group_id ?? '' }}', '{{ $user->company_id ?? '' }}', '{{ $user->module_access ?? 'all' }}', '{{ $user->jobPositions->first()?->id ?? '' }}')"
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
