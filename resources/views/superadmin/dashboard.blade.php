<x-layouts.vigia title="Panel de Administración">

    <div class="mb-6 flex items-center justify-between">
        <h1 class="text-2xl font-bold text-[#1A428A]">Panel de Administración</h1>
        <span class="rounded-full bg-purple-100 px-3 py-1 text-xs font-bold text-purple-700">Superadministrador</span>
    </div>

    {{-- Stat cards --}}
    <div class="mb-8 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Grupos</p>
            <p class="mt-1 text-3xl font-bold text-[#1A428A]">{{ $stats['groups'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Empresas</p>
            <p class="mt-1 text-3xl font-bold text-[#1A428A]">{{ $stats['companies'] }}</p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-500">Usuarios totales</p>
            <p class="mt-1 text-3xl font-bold text-[#1A428A]">{{ $stats['users'] }}</p>
        </div>
    </div>

    {{-- Groups table --}}
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="font-semibold text-gray-800">Grupos del sistema</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-5 py-3">Nombre</th>
                        <th class="px-5 py-3">Slug</th>
                        <th class="px-5 py-3 text-center">Empresas</th>
                        <th class="px-5 py-3 text-center">Usuarios</th>
                        <th class="px-5 py-3 text-center">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($groups as $group)
                        <tr class="border-t hover:bg-gray-50">
                            <td class="px-5 py-3 font-medium text-gray-900">{{ $group->name }}</td>
                            <td class="px-5 py-3 font-mono text-gray-500">{{ $group->slug }}</td>
                            <td class="px-5 py-3 text-center text-gray-700">{{ $group->companies_count }}</td>
                            <td class="px-5 py-3 text-center text-gray-700">{{ $group->users_count }}</td>
                            <td class="px-5 py-3 text-center">
                                @if($group->is_active)
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-700">Activo</span>
                                @else
                                    <span class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">Inactivo</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-gray-400">No hay grupos registrados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</x-layouts.vigia>
