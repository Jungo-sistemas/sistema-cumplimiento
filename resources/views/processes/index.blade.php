<x-layouts.vigia title="Procesos">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Procesos</span>
    </x-slot>

    @php $user = auth()->user(); @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Reglamentos</h1>

        @if($user->isAdmin() || $user->isOperative())
            <button type="button"
                    x-data
                    @click="$dispatch('open-modal', 'create-regulation')"
                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                + Nuevo reglamento
            </button>
        @endif
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('processes.index') }}"
          class="mt-4 flex flex-wrap items-end gap-3">

        @if($user->hasGroupScope())
            <div class="min-w-[160px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="company_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}"
                            @selected((string) request('company_id', $selectedCompanyId) === (string) $company->id)>
                            {{ $company->name }}
                        </option>
                    @endforeach
                </select>
            </div>
        @endif

        <div class="min-w-[160px]">
            <label class="block text-xs text-gray-500 mb-1">Tipo de proceso</label>
            <select name="process_type_id" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>
                @foreach($processTypes as $pt)
                    <option value="{{ $pt->id }}"
                        @selected(request('process_type_id') == $pt->id)>
                        {{ $pt->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="min-w-[160px]">
            <label class="block text-xs text-gray-500 mb-1">Tipo de documento</label>
            <select name="document_type" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>
                @foreach($documentTypes as $dt)
                    <option value="{{ $dt }}" @selected(request('document_type') === $dt)>
                        {{ $dt }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="min-w-[120px]">
            <label class="block text-xs text-gray-500 mb-1">Estatus</label>
            <select name="status" class="w-full rounded-md border-gray-300 text-sm">
                <option value="">Todos</option>
                <option value="green"  @selected(request('status') === 'green')>Vigente</option>
                <option value="yellow" @selected(request('status') === 'yellow')>Por vencer / Pendiente</option>
                <option value="red"    @selected(request('status') === 'red')>Vencido</option>
            </select>
        </div>

        <div class="flex-1 min-w-[180px] max-w-xs">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text"
                   name="q"
                   value="{{ request('q') }}"
                   placeholder="Código o nombre..."
                   class="w-full rounded-md border-gray-300 text-sm">
        </div>

        <button type="submit"
                class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
            Filtrar
        </button>

        <a href="{{ route('processes.index') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

    {{-- ALERTS --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA --}}
    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="text-left px-4 py-3 font-semibold">Código</th>
                        <th class="text-left px-4 py-3 font-semibold">Descripción</th>
                        <th class="text-left px-4 py-3 font-semibold">Proceso</th>
                        <th class="text-left px-4 py-3 font-semibold">Tipo</th>
                        <th class="text-left px-4 py-3 font-semibold">Versión</th>
                        <th class="text-left px-4 py-3 font-semibold">Vigencia</th>
                        <th class="text-left px-4 py-3 font-semibold">Estatus</th>
                        @if($user->hasGroupScope())
                            <th class="text-left px-4 py-3 font-semibold">Empresa</th>
                        @endif
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($regulations as $regulation)
                        @php
                            $version    = $regulation->currentVersion;
                            $color      = $regulation->statusColor();
                            $daysLeft   = $regulation->daysUntilExpiry();
                        @endphp

                        <tr class="border-t hover:bg-gray-50">

                            {{-- Código --}}
                            <td class="px-4 py-3 font-mono text-gray-700 whitespace-nowrap">
                                {{ $regulation->code ?? '—' }}
                            </td>

                            {{-- Nombre --}}
                            <td class="px-4 py-3 font-medium text-gray-800">
                                {{ $regulation->name }}
                            </td>

                            {{-- Proceso --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $regulation->processType->name ?? '—' }}
                            </td>

                            {{-- Tipo de documento --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $regulation->document_type ?? '—' }}
                            </td>

                            {{-- Versión --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                {{ $version ? 'v' . $version->version_number : '—' }}
                            </td>

                            {{-- Vigencia (fecha inicio emisión → válido hasta) --}}
                            <td class="px-4 py-3 text-gray-600 whitespace-nowrap">
                                @if($version?->valid_until)
                                    {{ $version->issued_at?->format('d/m/Y') ?? '—' }}
                                    →
                                    <span class="{{ $color === 'red' ? 'text-red-600 font-medium' : ($color === 'yellow' ? 'text-yellow-600 font-medium' : '') }}">
                                        {{ $version->valid_until->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">Sin vigencia</span>
                                @endif
                            </td>

                            {{-- Estatus (bola de color) --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                <div x-data="{ open: false }" class="relative inline-block">
                                    <button type="button"
                                            @click="open = !open"
                                            @click.outside="open = false"
                                            class="flex items-center gap-2 focus:outline-none">
                                        <span class="inline-block h-3 w-3 rounded-full
                                            {{ $color === 'green'  ? 'bg-green-500' : '' }}
                                            {{ $color === 'yellow' ? 'bg-yellow-400' : '' }}
                                            {{ $color === 'red'    ? 'bg-red-500' : '' }}">
                                        </span>
                                        <span class="text-xs text-gray-500">
                                            {{ $regulation->statusLabel() }}
                                        </span>
                                    </button>

                                    {{-- Tooltip popup --}}
                                    <div x-show="open"
                                         x-transition
                                         class="absolute left-0 top-6 z-20 w-56 rounded-xl border border-gray-200 bg-white shadow-lg p-3 text-xs text-gray-700">
                                        @if(! $version)
                                            <p class="font-semibold text-yellow-600">Sin versión cargada</p>
                                            <p class="mt-1 text-gray-500">No se ha subido ningún archivo todavía.</p>
                                        @elseif($color === 'red')
                                            <p class="font-semibold text-red-600">Vencido</p>
                                            @if($daysLeft !== null)
                                                <p class="mt-1">Venció hace {{ abs($daysLeft) }} día(s).</p>
                                            @endif
                                            @if($version->responsible_name)
                                                <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                            @endif
                                        @elseif($color === 'yellow')
                                            <p class="font-semibold text-yellow-600">Por vencer</p>
                                            @if($daysLeft !== null)
                                                <p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>
                                            @endif
                                            @if($version->responsible_name)
                                                <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                            @endif
                                        @else
                                            <p class="font-semibold text-green-600">Vigente</p>
                                            @if($daysLeft !== null)
                                                <p class="mt-1">Vence en {{ $daysLeft }} día(s).</p>
                                            @endif
                                            @if($version->responsible_name)
                                                <p class="mt-1">Responsable: <span class="font-medium">{{ $version->responsible_name }}</span></p>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Empresa (solo grupo) --}}
                            @if($user->hasGroupScope())
                                <td class="px-4 py-3 text-xs text-indigo-600 font-medium whitespace-nowrap">
                                    {{ $regulation->company->name ?? '—' }}
                                </td>
                            @endif

                            {{-- Acción --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('processes.show', $regulation) }}"
                                   class="text-blue-600 font-semibold text-sm hover:underline">
                                    Gestionar →
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="{{ $user->hasGroupScope() ? 9 : 8 }}"
                                class="px-6 py-8 text-center text-gray-500">
                                No hay reglamentos para los filtros seleccionados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="mt-3 text-xs text-gray-400">
        {{ $regulations->count() }} {{ \Illuminate\Support\Str::plural('reglamento', $regulations->count()) }} encontrados
    </p>

    {{-- MODAL: Crear reglamento --}}
    @if($user->isAdmin() || $user->isOperative())
        <x-modal name="create-regulation" :show="$errors->createRegulation->isNotEmpty()" focusable maxWidth="lg">
            <form method="POST" action="{{ route('processes.store') }}" class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Nuevo reglamento</h2>

                <div class="space-y-4">

                    @if($user->hasGroupScope())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Empresa <span class="text-red-500">*</span>
                            </label>
                            <select name="company_id"
                                    required
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">— Seleccionar —</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}"
                                            @selected(old('company_id') == $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($errors->createRegulation->has('company_id'))
                                <p class="text-sm text-red-600 mt-1">{{ $errors->createRegulation->first('company_id') }}</p>
                            @endif
                        </div>
                    @else
                        <input type="hidden" name="company_id" value="{{ $user->company_id }}">
                    @endif

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de proceso <span class="text-red-500">*</span>
                        </label>
                        <select name="process_type_id"
                                required
                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">— Seleccionar —</option>
                            @foreach($processTypes as $pt)
                                <option value="{{ $pt->id }}"
                                        @selected(old('process_type_id') == $pt->id)>
                                    {{ $pt->name }}
                                </option>
                            @endforeach
                        </select>
                        @if($errors->createRegulation->has('process_type_id'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createRegulation->first('process_type_id') }}</p>
                        @endif
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Tipo de documento
                        </label>
                        <select name="document_type"
                                class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                            <option value="">— Seleccionar —</option>
                            @foreach($documentTypes as $dt)
                                <option value="{{ $dt }}" @selected(old('document_type') === $dt)>
                                    {{ $dt }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                        <input type="text"
                               name="code"
                               value="{{ old('code') }}"
                               placeholder="REG-SAV-001"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Descripción <span class="text-red-500">*</span>
                        </label>
                        <input type="text"
                               name="name"
                               value="{{ old('name') }}"
                               required
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->createRegulation->has('name'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createRegulation->first('name') }}</p>
                        @endif
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                            x-on:click="$dispatch('close')"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                        Guardar
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

</x-layouts.vigia>
