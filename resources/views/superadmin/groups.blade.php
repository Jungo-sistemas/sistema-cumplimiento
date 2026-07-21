<x-layouts.vigia title="Grupos">

    <div x-data="{ showForm: false }">

        {{-- Header --}}
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-2xl font-bold text-[#1A428A]">Grupos</h1>
            <button
                type="button"
                @click="showForm = !showForm"
                class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] text-sm"
            >
                + Nuevo grupo
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
            <h2 class="mb-4 font-semibold text-gray-800">Nuevo grupo</h2>
            <form method="POST" action="{{ route('superadmin.groups.store') }}" class="flex flex-wrap items-end gap-4">
                @csrf
                <div class="flex-1 min-w-[200px]">
                    <label class="mb-1 block text-sm font-medium text-gray-700" for="group_name">
                        Nombre <span class="text-red-500">*</span>
                    </label>
                    <input
                        id="group_name"
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        required
                        class="w-full rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-[#1A428A] focus:outline-none focus:ring-1 focus:ring-[#1A428A]"
                        placeholder="Nombre del grupo"
                    >
                    @error('name')
                        <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="flex gap-2">
                    <button
                        type="submit"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] text-sm"
                    >
                        Crear grupo
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

        {{-- Groups table --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                        <tr>
                            <th class="px-5 py-3">Nombre</th>
                            <th class="px-5 py-3 text-center">Empresas</th>
                            <th class="px-5 py-3 text-center">Usuarios</th>
                            <th class="px-5 py-3">Licencia (activos)</th>
                            <th class="px-5 py-3 text-center">Estado</th>
                            <th class="px-5 py-3 min-w-[220px]">Ciclo de licencia</th>
                            <th class="px-5 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @forelse($groups as $group)
                            @php
                                $limit   = $group->asset_limit;
                                $current = $group->asset_current ?? 0;
                                $pct     = $limit > 0 ? min(100, (int) round(($current / $limit) * 100)) : 0;
                                $barColor = $pct >= 100 ? 'bg-red-500' : ($pct >= 80 ? 'bg-orange-400' : 'bg-[#1A428A]');
                            @endphp
                            <tr class="border-t hover:bg-gray-50">
                                <td class="px-5 py-3 font-medium text-gray-900">
                                    {{ $group->name }}
                                    <span class="ml-1 font-mono text-xs text-gray-400">{{ $group->slug }}</span>
                                </td>
                                <td class="px-5 py-3 text-center text-gray-700">{{ $group->companies_count }}</td>
                                <td class="px-5 py-3 text-center text-gray-700">{{ $group->users_count }}</td>
                                <td class="px-5 py-3 min-w-[260px]">
                                    @php
                                        $planMatch = \App\Services\LicenseService::planForLimit($limit);
                                        $planName  = $planMatch['name'] ?? ($limit === null ? 'Enterprise' : 'Personalizado');
                                        $planColor = match($planMatch['slug'] ?? 'custom') {
                                            'basic'      => 'bg-gray-100 text-gray-700',
                                            'pro'        => 'bg-blue-100 text-blue-700',
                                            'business'   => 'bg-purple-100 text-purple-700',
                                            'enterprise' => 'bg-yellow-100 text-yellow-700',
                                            default      => 'bg-gray-100 text-gray-500',
                                        };
                                    @endphp
                                    <div x-data="{ editing: false, plan: '{{ $planMatch['slug'] ?? ($limit === null ? 'enterprise' : 'custom') }}', customLimit: '{{ $limit ?? '' }}' }">
                                        {{-- Display mode --}}
                                        <div x-show="!editing" class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $planColor }}">{{ $planName }}</span>
                                                @if($limit)
                                                    <span class="text-xs text-gray-500">{{ $current }} / {{ $limit }}</span>
                                                @else
                                                    <span class="text-xs text-gray-400">Sin límite</span>
                                                @endif
                                                <button type="button" @click="editing = true"
                                                        class="text-xs text-[#1A428A] hover:underline">Cambiar</button>
                                            </div>
                                            @if($limit)
                                                <div class="h-1.5 w-full rounded-full bg-gray-200">
                                                    <div class="h-1.5 rounded-full {{ $barColor }}" style="width: {{ $pct }}%"></div>
                                                </div>
                                            @endif
                                        </div>
                                        {{-- Edit mode --}}
                                        <form x-show="editing" method="POST"
                                              action="{{ route('superadmin.groups.limit', $group) }}"
                                              class="space-y-2" style="display:none">
                                            @csrf @method('PATCH')
                                            <select name="asset_limit" x-model="plan"
                                                    @change="if(plan !== 'custom') customLimit = {basic:'50',pro:'100',business:'500',enterprise:''}[plan]"
                                                    class="w-full rounded-md border-gray-300 text-sm">
                                                @foreach(\App\Services\LicenseService::PLANS as $p)
                                                    <option value="{{ $p['slug'] }}"
                                                            data-limit="{{ $p['limit'] ?? '' }}">
                                                        {{ $p['name'] }}
                                                        @if($p['limit']) — hasta {{ $p['limit'] }} activos @else — sin límite @endif
                                                    </option>
                                                @endforeach
                                                <option value="custom">Personalizado</option>
                                            </select>
                                            <div x-show="plan === 'custom'" class="flex items-center gap-2">
                                                <input type="number" x-model="customLimit" min="1" max="99999"
                                                       placeholder="Número de activos"
                                                       class="w-32 rounded-md border-gray-300 text-sm">
                                            </div>
                                            {{-- Hidden field that sends the actual numeric limit --}}
                                            <input type="hidden" name="asset_limit"
                                                   :value="plan === 'enterprise' ? '' : (plan === 'custom' ? customLimit : {basic:'50',pro:'100',business:'500'}[plan])">
                                            <div class="flex gap-2">
                                                <button type="submit"
                                                        class="text-xs px-3 py-1.5 rounded bg-[#1A428A] text-white hover:bg-[#15356d]">Guardar</button>
                                                <button type="button" @click="editing = false"
                                                        class="text-xs text-gray-500 hover:underline">Cancelar</button>
                                            </div>
                                        </form>
                                    </div>
                                </td>
                                <td class="px-5 py-3 text-center">
                                    @if($group->is_active)
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Activo</span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    @include('superadmin.partials.license-cycle', [
                                        'license'      => $group->current_license,
                                        'activateRoute' => route('superadmin.groups.license', $group),
                                    ])
                                </td>
                                <td class="px-5 py-3 text-right">
                                    @if($group->companies_count === 0)
                                        <form method="POST" action="{{ route('superadmin.groups.destroy', $group) }}" class="inline"
                                            onsubmit="return confirm('¿Eliminar el grupo «{{ $group->name }}»?')">
                                            @csrf @method('DELETE')
                                            <button type="submit"
                                                class="px-3 py-1.5 rounded-md bg-red-600 text-white text-sm font-semibold hover:bg-red-700">
                                                Eliminar
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-gray-400 italic">Tiene empresas</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-5 py-6 text-center text-gray-400">No hay grupos registrados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</x-layouts.vigia>
