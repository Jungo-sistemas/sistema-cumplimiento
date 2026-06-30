<x-layouts.vigia :title="'Agregar usuario'">
    <x-slot name="breadcrumb">
        <a href="{{ route('users.index') }}" class="text-gray-600 hover:underline">
            Usuarios
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Agregar usuario</span>
    </x-slot>

    @php
        $adminRoleId      = $roles->where('slug', 'admin')->first()?->id;
        $companiesByGroup = $companies->groupBy('group_id')->map->values();
    @endphp

    <script>
    function createUserData() {
        return {
            selectedRole: '{{ old('role_id', '') }}',
            selectedGroup: '{{ old('group_id', $singleCompany?->group_id ?? '') }}',
            selectedCompany: '{{ old('company_id', $singleCompany?->id ?? '') }}',
            selectedPosition: '{{ old('job_position_id', '') }}',
            adminRoleId: '{{ $adminRoleId }}',
            companiesByGroup: @json($companiesByGroup),
            positionsByGroup: @json($positionsByGroup),
            get isAdmin() { return this.selectedRole === this.adminRoleId; },
            get needsCompany() { return this.selectedRole !== '' && !this.isAdmin; },
            get availableCompanies() {
                if (!this.selectedGroup) return [];
                return this.companiesByGroup[this.selectedGroup] ?? [];
            },
            get availablePositions() {
                if (!this.selectedGroup) return [];
                return this.positionsByGroup[this.selectedGroup] ?? [];
            }
        };
    }
    </script>

    <div class="bg-white rounded-xl shadow p-6 max-w-3xl" x-data="createUserData()">

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

            {{-- ── DATOS BÁSICOS ─────────────────────────────────────────── --}}
            <div class="space-y-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Datos del usuario</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                </div>
            </div>

            <hr class="border-gray-100">

            {{-- ── ROL DEL SISTEMA ───────────────────────────────────────── --}}
            <div class="space-y-4">
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Rol en la plataforma</h2>
                    <p class="text-xs text-gray-400 mt-0.5">Define los permisos de acceso al sistema.</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Rol <span class="text-red-500">*</span></label>
                    <select name="role_id" x-model="selectedRole" required
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                        <option value="">Selecciona un rol</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">
                                @switch($role->slug)
                                    @case('admin') Administrador @break
                                    @case('operative') Operativo @break
                                    @case('readonly') Solo lectura @break
                                    @default {{ $role->name }}
                                @endswitch
                            </option>
                        @endforeach
                    </select>
                    <div class="mt-2 grid grid-cols-3 gap-2 text-xs text-gray-500">
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <span class="font-medium text-gray-700">Administrador</span><br>
                            Gestiona usuarios, activos y configuración del grupo.
                        </div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <span class="font-medium text-gray-700">Operativo</span><br>
                            Crea y edita activos, tareas y documentos.
                        </div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <span class="font-medium text-gray-700">Solo lectura</span><br>
                            Solo consulta. No puede crear ni modificar.
                        </div>
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            {{-- ── PUESTO EN LA ORGANIZACIÓN ─────────────────────────────── --}}
            <div class="space-y-4" x-show="selectedRole !== ''" x-transition>
                <div>
                    <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Puesto en la organización</h2>
                    <p class="text-xs text-gray-400 mt-0.5">
                        Determina el nivel jerárquico del usuario en los flujos de aprobación de documentos.<br>
                        Es independiente del rol de plataforma.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Puesto</label>
                    <select name="job_position_id" x-model="selectedPosition"
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                        <option value="">— Sin asignar —</option>
                        <template x-for="pos in availablePositions" :key="pos.id">
                            <option :value="pos.id" x-text="pos.name" :selected="selectedPosition == pos.id"></option>
                        </template>
                    </select>
                    <div x-show="availablePositions.length === 0 && selectedGroup !== ''"
                         class="mt-1 text-xs text-yellow-600">
                        No hay puestos configurados para este grupo.
                    </div>
                    {{-- Jerarquía visual --}}
                    <div class="mt-2 flex items-center gap-1 text-xs text-gray-400">
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Líder</span>
                        <span>→</span>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Jefe</span>
                        <span>→</span>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Gerente</span>
                        <span>→</span>
                        <span class="px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">Dirección</span>
                        <span class="ml-1 text-gray-400">(de menor a mayor)</span>
                    </div>
                </div>
            </div>

            <hr class="border-gray-100" x-show="selectedRole !== ''" x-transition>

            {{-- ── ALCANCE Y EMPRESA ─────────────────────────────────────── --}}
            @if($singleCompany)

                <input type="hidden" name="company_id" value="{{ $singleCompany->id }}">
                <input type="hidden" name="group_id" value="{{ $singleCompany->group_id }}">

                <div x-show="selectedRole !== ''" x-transition class="space-y-4">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Empresa</h2>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empresa asignada</label>
                        <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                            {{ $singleCompany->name }}
                        </div>
                    </div>
                </div>

            @else

                {{-- Grupo --}}
                <div x-show="selectedRole !== ''" x-transition class="space-y-4">
                    <div>
                        <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Alcance de acceso</h2>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Grupo <span class="text-red-500">*</span>
                        </label>
                        @if($groups->count() === 1)
                            <input type="hidden" name="group_id" value="{{ $groups->first()->id }}"
                                   x-init="selectedGroup = '{{ $groups->first()->id }}'">
                            <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-700">
                                {{ $groups->first()->name }}
                            </div>
                        @else
                            <select name="group_id" x-model="selectedGroup" required
                                class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                                <option value="">Selecciona un grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Empresa (solo para operativo y solo lectura) --}}
                    <div x-show="needsCompany && selectedGroup !== ''" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Empresa <span class="text-red-500">*</span>
                        </label>
                        <select name="company_id" x-model="selectedCompany"
                            :required="needsCompany"
                            class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                            <option value="">Selecciona una empresa</option>
                            <template x-for="company in availableCompanies" :key="company.id">
                                <option :value="company.id" x-text="company.name"
                                    :selected="selectedCompany == company.id"></option>
                            </template>
                        </select>
                        <p x-show="selectedGroup !== '' && availableCompanies.length === 0"
                           class="mt-1 text-xs text-yellow-600">
                            Este grupo no tiene empresas registradas.
                        </p>
                    </div>

                    {{-- Nota para rol admin --}}
                    <div x-show="isAdmin && selectedGroup !== ''" x-transition
                         class="flex items-start gap-2 rounded-lg bg-blue-50 border border-blue-200 px-4 py-3">
                        <svg class="w-4 h-4 text-blue-500 mt-0.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm text-blue-700">El administrador tendrá acceso a todas las empresas del grupo seleccionado.</p>
                    </div>
                </div>

            @endif

            {{-- Módulos / Vista predeterminada --}}
            <div x-show="selectedRole !== ''" x-transition class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    <span x-text="isAdmin ? 'Vista predeterminada' : 'Módulos visibles'"></span>
                    <span x-show="!isAdmin" class="text-red-500">*</span>
                </label>
                <select name="module_access"
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A] text-sm">
                    <option value="all"         {{ old('module_access', 'all') === 'all'         ? 'selected' : '' }}>Ambos módulos</option>
                    <option value="cumplimiento" {{ old('module_access') === 'cumplimiento'       ? 'selected' : '' }}>Solo Cumplimiento</option>
                    <option value="procesos"     {{ old('module_access') === 'procesos'           ? 'selected' : '' }}>Solo Procesos</option>
                </select>
                <p class="text-xs text-gray-400"
                   x-text="isAdmin
                       ? 'El administrador accede a todo; esto solo define el módulo de inicio al entrar al sistema.'
                       : 'Define a qué sección del sistema tendrá acceso este usuario.'">
                </p>
            </div>

            <div class="flex justify-end gap-3 pt-2">
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
