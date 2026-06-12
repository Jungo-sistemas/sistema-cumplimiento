<x-layouts.vigia title="Puestos de aprobación">
        <x-slot name="breadcrumb">
        <a href="{{ route('processes.index') }}" class="text-gray-600 hover:underline">Procesos</a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Puestos de aprobación</span>
    </x-slot>

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

        {{-- Header --}}
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-[#1A428A]">Puestos de aprobación</h1>
            <p class="text-sm text-gray-500 mt-1">
                Asigna usuarios a cada puesto. Los reglamentos son aprobados según el nivel de impacto y los usuarios aquí configurados.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-4 rounded-lg bg-green-50 border border-green-200 px-4 py-3 text-sm text-green-800">
                {{ session('success') }}
            </div>
        @endif

        {{-- Tabla de flujos de referencia --}}
        <div class="mb-8 rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-5 py-3 bg-[#1A428A] text-white text-sm font-semibold">Flujos de aprobación por nivel de impacto</div>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-gray-50 text-gray-600 text-xs uppercase">
                        <tr>
                            <th class="px-4 py-2 text-left font-medium">Nivel</th>
                            <th class="px-4 py-2 text-left font-medium">Paso 1 — Revisión</th>
                            <th class="px-4 py-2 text-left font-medium">Paso 2 — Autorización</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr class="bg-white">
                            <td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-red-100 text-red-800">Alto</span></td>
                            <td class="px-4 py-2 text-gray-600">Líder <strong>Y</strong> Ejecutivo de Reglamentos</td>
                            <td class="px-4 py-2 text-gray-600">Dirección General</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-orange-100 text-orange-800">Medio - Alto</span></td>
                            <td class="px-4 py-2 text-gray-600">Líder <strong>Y</strong> Ejecutivo de Reglamentos</td>
                            <td class="px-4 py-2 text-gray-600">Dir. General <strong>Y</strong> Dir. Finanzas</td>
                        </tr>
                        <tr class="bg-white">
                            <td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-blue-100 text-blue-800">Medio</span></td>
                            <td class="px-4 py-2 text-gray-600">Ejecutivo de Reglamentos</td>
                            <td class="px-4 py-2 text-gray-600">Líder <strong>O</strong> Gerente</td>
                        </tr>
                        <tr class="bg-gray-50">
                            <td class="px-4 py-2"><span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-semibold bg-gray-200 text-gray-700">Bajo</span></td>
                            <td class="px-4 py-2 text-gray-600" colspan="2">Ejecutivo de Reglamentos (paso único)</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Puestos --}}
        <div class="space-y-5">
            @foreach($positions as $position)
                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden"
                     x-data="{ open: false }">

                    <div class="px-5 py-4 flex items-center justify-between">
                        <div>
                            <h3 class="font-semibold text-gray-800">{{ $position->name }}</h3>
                            <p class="text-xs text-gray-400 mt-0.5">{{ $position->users->count() }} usuario(s) asignado(s)</p>
                        </div>
                        <button @click="open = !open"
                                class="text-sm text-[#1A428A] hover:underline font-medium">
                            <span x-text="open ? 'Cerrar' : 'Gestionar'"></span>
                        </button>
                    </div>

                    <div x-show="open" x-transition style="display:none">
                        <div class="border-t border-gray-100 px-5 py-4 space-y-4">

                            {{-- Usuarios actuales --}}
                            @if($position->users->isNotEmpty())
                                <div>
                                    <p class="text-xs font-medium text-gray-500 mb-2">Usuarios asignados</p>
                                    <ul class="space-y-1">
                                        @foreach($position->users as $u)
                                            <li class="flex items-center justify-between py-1 px-3 bg-gray-50 rounded-lg text-sm">
                                                <span class="text-gray-700">{{ $u->name }}</span>
                                                <form method="POST" action="{{ route('job-positions.remove') }}"
                                                      onsubmit="return confirm('¿Quitar a {{ $u->name }} de este puesto?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <input type="hidden" name="job_position_id" value="{{ $position->id }}">
                                                    <input type="hidden" name="user_id" value="{{ $u->id }}">
                                                    <button type="submit"
                                                            class="text-xs text-red-500 hover:text-red-700 font-medium">
                                                        Quitar
                                                    </button>
                                                </form>
                                            </li>
                                        @endforeach
                                    </ul>
                                </div>
                            @else
                                <p class="text-sm text-gray-400 italic">Sin usuarios asignados</p>
                            @endif

                            {{-- Agregar usuario --}}
                            @php
                                $assignedIds = $position->users->pluck('id')->toArray();
                                $available = $groupUsers->whereNotIn('id', $assignedIds);
                            @endphp
                            @if($available->isNotEmpty())
                                <form method="POST" action="{{ route('job-positions.assign') }}" class="flex items-end gap-3">
                                    @csrf
                                    <input type="hidden" name="job_position_id" value="{{ $position->id }}">
                                    <div class="flex-1">
                                        <label class="block text-xs font-medium text-gray-500 mb-1">Agregar usuario</label>
                                        <select name="user_id"
                                                class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                                            <option value="">— Seleccionar usuario —</option>
                                            @foreach($available as $u)
                                                <option value="{{ $u->id }}">{{ $u->name }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <button type="submit"
                                            class="px-4 py-2 bg-[#1A428A] text-white text-sm font-medium rounded-lg hover:bg-blue-800">
                                        Asignar
                                    </button>
                                </form>
                            @else
                                <p class="text-xs text-gray-400">Todos los usuarios del grupo ya están asignados a este puesto.</p>
                            @endif

                        </div>
                    </div>
                </div>
            @endforeach
        </div>

    </div>
</x-layouts.vigia>
