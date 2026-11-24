<x-layouts.vigia :title="'Agregar usuario'" :minimal="true" :back-route="'users.index'" :back-label="'Usuarios'">
    <x-slot name="breadcrumb">
        <a href="{{ route('users.index') }}" class="text-gray-600 hover:underline">
            Usuarios
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Agregar usuario</span>
    </x-slot>

    @php
        $adminRoleId      = $roles->where('slug', 'admin')->first()?->id;
        $auditorRoleId    = $roles->where('slug', 'auditor')->first()?->id;
        $jefeRoleId       = $roles->where('slug', 'jefe_auditoria')->first()?->id;
        $companiesByGroup = $companies->groupBy('group_id')->map->values();
        // Solo los módulos de quien crea: un admin de Cumplimiento crea usuarios de Cumplimiento.
        $moduleOptions = auth()->user()->grantablePortalModules();
    @endphp

    <script>
    function createUserData() {
        return {
            selectedRole: '{{ old('role_id', '') }}',
            selectedGroup: '{{ old('group_id', $singleCompany?->group_id ?? '') }}',
            selectedCompanyIds: {!! json_encode(array_map('strval', old('company_id', $singleCompany ? [$singleCompany->id] : []))) !!},
            selectedPosition: '{{ old('job_position_id', '') }}',
            companySearch: '',
            companySearchOpen: false,
            adminRoleId: '{{ $adminRoleId }}',
            auditorRoleId: '{{ $auditorRoleId }}',
            jefeRoleId: '{{ $jefeRoleId }}',
            companiesByGroup: @json($companiesByGroup),
            positionsByGroup: @json($positionsByGroup),
            // Módulos marcados; de entrada, todos los que puede dar quien crea.
            selectedModules: @js(array_values((array) old('modules', $moduleOptions))),
            // Área que dan los módulos marcados (de ella dependen empresas y avisos).
            get moduleAccess() {
                const procesos = this.selectedModules.includes('procesos');
                const otros = this.selectedModules.some(m => m !== 'procesos');
                return procesos && otros ? 'all' : (procesos ? 'procesos' : 'cumplimiento');
            },
            init() {
                // Al cambiar de módulo se quitan las empresas que ya no salen en ese lado.
                this.$watch('companyModule', () => {
                    const ids = this.availableCompanies.map(c => String(c.id));
                    this.selectedCompanyIds = this.selectedCompanyIds.filter(id => ids.includes(id));
                });
            },
            get isAdmin() { return this.selectedRole === this.adminRoleId; },
            get isAuditor() { return this.selectedRole === this.auditorRoleId; },
            // Auditor y Jefe de Auditoría solo trabajan en Procesos: su módulo no se elige.
            get soloProcesos() { return this.isAuditor || this.selectedRole === this.jefeRoleId; },
            get needsCompany() { return this.selectedRole !== ''; },
            // El auditor y el Jefe solo están en Procesos aunque las casillas digan otra cosa.
            get companyModule() { return this.soloProcesos ? 'procesos' : this.moduleAccess; },
            // Solo Procesos: las empresas que salen en Procesos. Cumplimiento o ambos: todas.
            get availableCompanies() {
                if (!this.selectedGroup) return [];
                const list = this.companiesByGroup[this.selectedGroup] ?? [];
                return this.companyModule === 'procesos' ? list.filter(c => c.show_in_processes && !c.otras) : list;
            },
            // Nombre según el módulo: en Procesos algunas se llaman distinto (VIDENCI → SAVSA).
            companyLabel(c) {
                const procesos = c.process_name || c.name;
                if (this.companyModule === 'procesos') return procesos;
                if (this.companyModule === 'all' && procesos !== c.name && c.show_in_processes) {
                    return c.name + ' · ' + procesos + ' en Procesos';
                }
                return c.name;
            },
            get availablePositions() {
                if (!this.selectedGroup) return [];
                return this.positionsByGroup[this.selectedGroup] ?? [];
            },
            get companySearchResults() {
                const q = this.companySearch.trim().toLowerCase();
                return this.availableCompanies
                    .filter(c => !this.selectedCompanyIds.includes(String(c.id)))
                    // Se encuentra por cualquiera de sus dos nombres (VIDENCI o SAVSA).
                    .filter(c => !q || [c.name, c.process_name].some(n => (n || '').toLowerCase().includes(q)));
            },
            companyName(id) {
                const c = this.availableCompanies.find(c => String(c.id) === String(id));
                return c ? this.companyLabel(c) : '—';
            },
            addCompany(id) {
                id = String(id);
                if (!this.selectedCompanyIds.includes(id)) this.selectedCompanyIds.push(id);
                this.companySearch = '';
                this.companySearchOpen = false;
            },
            removeCompany(id) {
                this.selectedCompanyIds = this.selectedCompanyIds.filter(x => x !== String(id));
            }
        };
    }
    </script>

    <div class="bg-white rounded-xl shadow p-6 max-w-3xl" x-data="createUserData()">

        <h1 class="text-2xl font-semibold text-brand">Agregar usuario</h1>

        @if ($errors->any())
            <div class="mt-4 rounded-lg border border-red-300 bg-red-50 p-4">
                <ul class="list-disc list-inside text-sm text-red-700">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- El correo de invitación se manda en la misma petición y tarda unos segundos: sin bloquear
             el botón, un segundo clic llegaba cuando el usuario ya existía y salía "email already taken". --}}
        <form method="POST" action="{{ route('users.store') }}" class="mt-6 space-y-6"
              x-data="{ submitting: false }" @submit="if (submitting) { $event.preventDefault() } else { submitting = true }">
            @csrf

            {{-- ── DATOS BÁSICOS ─────────────────────────────────────────── --}}
            <div class="space-y-4">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-gray-400">Datos del usuario</h2>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                    <input type="text" name="name" value="{{ old('name') }}" required
                        class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                    <input type="email" name="email" value="{{ old('email') }}" required
                        class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">
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
                        class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">
                        <option value="">Selecciona un rol</option>
                        @foreach($roles as $role)
                            <option value="{{ $role->id }}">
                                @switch($role->slug)
                                    @case('admin') Administrador @break
                                    @case('operative') Operativo @break
                                    @case('readonly') Solo lectura @break
                                    @case('auditor') Auditor @break
                                    @default {{ $role->name }}
                                @endswitch
                            </option>
                        @endforeach
                    </select>
                    <div class="mt-2 grid grid-cols-2 lg:grid-cols-3 gap-2 text-xs text-gray-500">
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
                        @if(auth()->user()->canAssignProcesosAccess())
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <span class="font-medium text-gray-700">Auditor</span><br>
                            Evalúa y califica procedimientos. Exclusivo del módulo Procesos.
                        </div>
                        <div class="rounded-lg bg-gray-50 border border-gray-200 px-3 py-2">
                            <span class="font-medium text-gray-700">Jefe de Auditoría</span><br>
                            Planea el programa anual y asigna a los auditores. Exclusivo del módulo Procesos.
                        </div>
                        @endif
                    </div>
                </div>
            </div>

            <hr class="border-gray-100">

            {{-- ── PUESTO EN LA ORGANIZACIÓN ─────────────────────────────── --}}
            {{-- Los puestos son de Procesos (flujos de aprobación): solo los asigna quien tiene Procesos. --}}
            @if(auth()->user()->canAssignProcesosAccess())
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
                        class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">
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
            @endif

            {{-- ── MÓDULOS ── antes de las empresas: la lista de empresas se arma según el módulo
                 (en Procesos algunas se llaman distinto, p. ej. VIDENCI → SAVSA, y otras no salen). --}}
            <div x-show="selectedRole !== ''" x-transition class="space-y-2">
                <label class="block text-sm font-medium text-gray-700">
                    <span x-text="soloProcesos ? 'Módulo' : 'Módulos visibles'"></span>
                </label>

                <template x-if="soloProcesos">
                    <div>
                        <input type="hidden" name="module_access" value="procesos">
                        <div class="w-full rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-500">
                            Solo Procesos y Auditorías
                        </div>
                    </div>
                </template>

                <template x-if="!soloProcesos">
                    <div>
                        @include('users.partials.module-checkboxes', ['model' => 'selectedModules', 'options' => $moduleOptions])
                    </div>
                </template>

                <p class="text-xs text-gray-400" x-show="soloProcesos">
                    Este rol solo entra a Procesos y Auditorías; no ve los demás módulos.
                </p>
            </div>

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
                                class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">
                                <option value="">Selecciona un grupo</option>
                                @foreach($groups as $group)
                                    <option value="{{ $group->id }}">{{ $group->name }}</option>
                                @endforeach
                            </select>
                        @endif
                    </div>

                    {{-- Empresas --}}
                    <div x-show="needsCompany && selectedGroup !== ''" x-transition>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Empresas</label>

                        {{-- Seleccionadas --}}
                        <div class="flex flex-wrap gap-1.5 mb-2" x-show="selectedCompanyIds.length > 0">
                            <template x-for="id in selectedCompanyIds" :key="id">
                                <span class="inline-flex items-center gap-1 pl-2.5 pr-1.5 py-1 rounded-full bg-blue-50 text-brand text-xs font-medium border border-blue-200">
                                    <span x-text="companyName(id)"></span>
                                    <button type="button" @click="removeCompany(id)" class="rounded-full hover:bg-blue-100 p-0.5">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                                        </svg>
                                    </button>
                                </span>
                            </template>
                        </div>

                        {{-- Buscador --}}
                        <div class="relative" @click.outside="companySearchOpen = false">
                            <input type="text"
                                x-model="companySearch"
                                @focus="companySearchOpen = true"
                                placeholder="Buscar empresa para agregar…"
                                autocomplete="off"
                                class="w-full rounded-md border-gray-300 focus:border-brand focus:ring-brand text-sm">

                            <div x-show="companySearchOpen && companySearchResults.length > 0"
                                 class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg max-h-48 overflow-y-auto"
                                 style="display:none;">
                                <template x-for="company in companySearchResults" :key="company.id">
                                    <div @click="addCompany(company.id)"
                                         class="px-3 py-2 text-sm text-gray-700 cursor-pointer hover:bg-gray-50"
                                         x-text="companyLabel(company)"></div>
                                </template>
                            </div>

                            <p x-show="companySearchOpen && companySearch.trim() !== '' && companySearchResults.length === 0"
                               class="absolute z-10 mt-1 w-full rounded-lg border border-gray-200 bg-white shadow-lg px-3 py-2 text-sm text-gray-400">
                                Sin resultados.
                            </p>
                        </div>

                        <template x-for="id in selectedCompanyIds" :key="id">
                            <input type="hidden" name="company_id[]" :value="id">
                        </template>

                        <p class="mt-1 text-xs text-gray-400">
                            Ninguna agregada: el usuario verá todas las empresas del grupo. Varias: acceso solo
                            a esas empresas (útil para quien administra más de una).
                        </p>
                        <p x-show="selectedGroup !== '' && availableCompanies.length === 0"
                           class="mt-1 text-xs text-yellow-600">
                            Este grupo no tiene empresas registradas.
                        </p>
                    </div>

                </div>

            @endif

            {{-- Notificaciones --}}
            <div x-show="selectedRole !== ''" x-transition class="space-y-2">
                {{--
                    Notificaciones, una casilla por área. Cumplimiento y Procesos (avisos de
                    administración) solo para un admin y solo de las áreas que tiene; Auditorías la
                    designa quien administra Procesos. Sus avisos personales le llegan siempre.
                --}}
                @php
                    $oldNotify = array_values((array) old('notify_modules', array_keys(\App\Support\PortalModules::LABELS)));
                    $canAudit = auth()->user()->canAssignProcesosAccess();
                @endphp
                {{-- Pastillas compactas: una por módulo que tendrá (avisos de administración, solo
                     admins; los módulos nuevos aún no mandan avisos pero ya queda la preferencia) y
                     Auditorías. "Todas" prende o apaga las que aplican a este usuario. --}}
                <div x-data="{
                        notifyAreas: @js($oldNotify),
                        notifyAudit: @js((bool) old('receives_audit_alerts')),
                        canAudit: @js($canAudit),
                        labels: { fiscal: 'Fiscal', regulatorio: 'Regulatorio', objetivos: 'Objetivos', procesos: 'Procesos', flujos: 'Flujos', cursos: 'Cursos', auditorias: 'Auditorías' },
                        get notifyOptions() {
                            // En el orden del menú, solo los módulos marcados arriba.
                            const o = Object.keys(this.labels).filter(k => k !== 'auditorias' && this.isAdmin && this.selectedModules.includes(k));
                            if (this.canAudit) o.push('auditorias');
                            return o;
                        },
                        isOn(k) { return k === 'auditorias' ? this.notifyAudit : this.notifyAreas.includes(k); },
                        toggle(k) {
                            if (k === 'auditorias') { this.notifyAudit = ! this.notifyAudit; return; }
                            this.notifyAreas = this.isOn(k) ? this.notifyAreas.filter(a => a !== k) : [...this.notifyAreas, k];
                        },
                        get allOn() { return this.notifyOptions.every(k => this.isOn(k)); },
                        toggleAll() {
                            const on = ! this.allOn;
                            this.notifyOptions.forEach(k => { if (this.isOn(k) !== on) this.toggle(k); });
                        },
                     }"
                     x-show="notifyOptions.length > 0" class="mt-3">
                    <p class="mb-1.5 text-sm font-medium text-gray-700">Notificaciones</p>
                    <div class="flex flex-wrap gap-2">
                        <button type="button" x-show="notifyOptions.length > 1" @click="toggleAll()" :aria-pressed="allOn"
                                :class="allOn ? 'border-brand bg-brand text-white' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors">
                            <x-icon name="check" class="h-3.5 w-3.5" x-show="allOn" />
                            Todas
                        </button>
                        <template x-for="k in notifyOptions" :key="k">
                            <button type="button" @click="toggle(k)" :aria-pressed="isOn(k)"
                                    :class="isOn(k) ? 'border-brand bg-brand/10 text-brand' : 'border-gray-300 bg-white text-gray-600 hover:bg-gray-50'"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-sm font-medium transition-colors">
                                <x-icon name="check" class="h-3.5 w-3.5" x-show="isOn(k)" />
                                <span x-text="labels[k]"></span>
                            </button>
                        </template>
                    </div>
                    <template x-for="k in notifyAreas.filter(a => notifyOptions.includes(a))" :key="k">
                        <input type="hidden" name="notify_modules[]" :value="k">
                    </template>
                    @if($canAudit)
                        <input type="hidden" name="receives_audit_alerts" :value="notifyAudit ? 1 : 0">
                    @endif
                    <p class="mt-1.5 text-xs text-gray-400">
                        <span x-show="notifyOptions.some(k => k !== 'auditorias')">Módulos: solicitudes de acceso, escalamientos y avisos a administradores de ese módulo.</span>
                        <span x-show="canAudit">Auditorías: hallazgos críticos y plazos vencidos del grupo.</span>
                        Sus avisos personales le llegan siempre.
                    </p>
                </div>
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <a href="{{ route('users.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" :disabled="submitting"
                        class="px-4 py-2 rounded-md bg-brand text-white font-semibold hover:bg-brand-dark disabled:opacity-60 disabled:cursor-wait">
                    <span x-show="! submitting">Enviar invitación</span>
                    <span x-show="submitting" x-cloak>Enviando invitación…</span>
                </button>
            </div>
        </form>
    </div>
</x-layouts.vigia>
