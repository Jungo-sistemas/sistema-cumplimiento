@php
    /** @var \App\Models\AssetRequirement $requirement */
    $asset = $asset ?? $requirement->asset;
    $title = $requirement->template?->name ?? $requirement->type ?? 'Carpeta';
@endphp

<x-layouts.vigia title="Nueva tarea" :nav-context="$navContext">

    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset?->company_id)])) }}"
           class="text-gray-600 hover:underline">
            Activos y Actividades
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.show', $requirement->asset) }}" class="text-gray-600 hover:underline">
            {{ $requirement->asset->name }}
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.requirements.show', [$requirement->asset_id, $requirement->id]) }}"
           class="text-gray-600 hover:underline">
            <x-truncate max="max-w-[400px]" class="font-semibold text-gray-700">
                {{ $requirement->template?->name ?? $requirement->type }}
            </x-truncate>
        </a>

        <span class="text-gray-400">›</span>

        <span class="text-gray-700 font-medium">
            Nueva tarea
        </span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div>
                <h1 class="text-2xl font-bold text-[#1A428A]">Nueva tarea</h1>
                <div class="text-sm text-gray-500 mt-1">
                    Carpeta: <span class="font-semibold text-gray-700">{{ $title }}</span>
                    @if($asset)
                        · Activo: <span class="font-semibold text-gray-700">{{ $asset->name }}</span>
                    @endif
                    @if(auth()->user()->hasGroupScope() && $asset?->company)
                        · Empresa: <span class="font-semibold text-gray-700">{{ $asset->company->name }}</span>
                    @endif
                </div>
            </div>

            @if($asset)
                @php
                    $assetInactive = ($asset->status ?? null) === \App\Models\Asset::STATUS_INACTIVE
                        || (method_exists($asset, 'isInactive') && $asset->isInactive());
                @endphp

                <span class="text-xs px-3 py-1 rounded border
                    {{ $assetInactive ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-green-50 text-green-700 border-green-200' }}">
                    {{ $assetInactive ? 'ASSET INACTIVO' : 'ASSET ACTIVO' }}
                </span>
            @endif
        </div>

        {{-- Errores --}}
        @if ($errors->any())
            <div class="mt-6 p-4 border border-red-200 bg-red-50 rounded-lg">
                <div class="font-semibold text-red-700 mb-2">Revisa los campos</div>
                <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form --}}
        <form method="POST" action="{{ route('requirements.tasks.store', $requirement) }}" class="mt-6"
              x-data="{
                  taskType: '{{ old('type', 'manual') }}',
                  expiresAt: '{{ $requirement->expires_at?->format('Y-m-d') ?? '' }}',
                  renewalDate() {
                      if (!this.expiresAt) return '';
                      const d = new Date(this.expiresAt + 'T00:00:00');
                      d.setDate(d.getDate() - 60);
                      const today = new Date();
                      today.setHours(0,0,0,0);
                      if (d <= today) { today.setDate(today.getDate() + 1); return today.toISOString().split('T')[0]; }
                      return d.toISOString().split('T')[0];
                  }
              }">
            @csrf

            <div class="grid grid-cols-1 gap-5">
                {{-- Tipo de tarea --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Tipo de tarea</label>
                    <select
                        name="type"
                        x-model="taskType"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    >
                        <option value="manual">Manual</option>
                        <option value="renewal">Renovación</option>
                        <option value="review">Revisión</option>
                    </select>
                    <p class="mt-1 text-xs text-gray-500" x-show="taskType === 'renewal'">
                        La fecha límite se calculará 60 días antes del vencimiento del documento oficial.
                    </p>
                    <p class="mt-1 text-xs text-gray-500" x-show="taskType !== 'renewal'">
                        Las tareas manuales y de revisión requieren evidencia para completarse.
                    </p>
                </div>

                {{-- Título --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Título</label>
                    <input
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        placeholder="Ej. Subir bitácora firmada"
                        required
                    >
                    @error('title')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Descripción --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Descripción</label>
                    <textarea
                        name="description"
                        rows="4"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        placeholder="Opcional"
                    >{{ old('description') }}</textarea>
                    @error('description')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Responsable --}}
                @php
                    $selectClass = "mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm";
                @endphp

                <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">
                        Selecciona un responsable
                    </label>

                    <select
                        id="responsible_user_id"
                        name="responsible_user_id"
                        class="{{ $selectClass }} searchable-select"
                        required
                    >
                        <option value="">-- Selecciona un responsable --</option>

                        @foreach($responsibles as $u)
                            <option
                                value="{{ $u->id }}"
                                @selected((int) old('responsible_user_id', $defaultResponsibleId ?? null) === (int) $u->id)
                            >
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('responsible_user_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Fecha límite --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Fecha límite</label>
                    <input
                        type="date"
                        name="due_date"
                        :value="taskType === 'renewal' ? renewalDate() : '{{ old('due_date') }}'"
                        :readonly="taskType === 'renewal'"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        :class="taskType === 'renewal' ? 'bg-gray-50 text-gray-500' : ''"
                    >
                    @error('due_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Acciones --}}
            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="px-5 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d] transition"
                >
                    Guardar
                </button>

                @if($asset)
                    <a
                        href="{{ route('assets.requirements.show', [$asset, $requirement]) }}"
                        class="px-5 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition"
                    >
                        Cancelar
                    </a>
                @else
                    <a
                        href="{{ url()->previous() }}"
                        class="px-5 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50 transition"
                    >
                        Cancelar
                    </a>
                @endif
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#responsible_user_id').select2({
                placeholder: '-- Selecciona un responsable --',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#responsible_user_id').closest('div')
            });
        });
    </script>

    <style>
        .select2-container {
            width: 100% !important;
        }

        .select2-container--default .select2-selection--single {
            height: 42px !important;
            border: 1px solid rgb(209 213 219) !important;
            border-radius: 0.375rem !important;
            background-color: #fff !important;
            display: flex !important;
            align-items: center !important;
            padding: 0 0.75rem !important;
            box-shadow: none !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: rgb(55 65 81) !important;
            font-size: 0.875rem !important;
            line-height: 40px !important;
            padding-left: 0 !important;
            padding-right: 2rem !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__placeholder {
            color: rgb(156 163 175) !important;
        }

        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 10px !important;
        }

        .select2-container--default.select2-container--open .select2-selection--single,
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 1px #2563eb !important;
        }

        .select2-dropdown {
            border: 1px solid rgb(209 213 219) !important;
            border-radius: 0.375rem !important;
            overflow: hidden !important;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1) !important;
        }

        .select2-container--open .select2-dropdown {
            left: 0 !important;
        }

        .select2-search--dropdown {
            padding: 0.5rem !important;
            box-sizing: border-box !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field {
            display: block !important;
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
            box-sizing: border-box !important;
            border: 1px solid rgb(209 213 219) !important;
            border-radius: 0.375rem !important;
            padding: 0.5rem 0.75rem !important;
            font-size: 0.875rem !important;
            outline: none !important;
        }

        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #2563eb !important;
            box-shadow: 0 0 0 1px #2563eb !important;
        }

        .select2-results__options {
            max-height: 240px !important;
            overflow-y: auto !important;
        }

        .select2-results__option {
            padding: 0.625rem 0.75rem !important;
            font-size: 0.875rem !important;
            color: rgb(55 65 81) !important;
        }

        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: rgb(239 246 255) !important;
            color: #1A428A !important;
        }

        .select2-container--default .select2-results__option[aria-selected=true] {
            background-color: rgb(249 250 251) !important;
            color: rgb(17 24 39) !important;
        }
    </style>
</x-layouts.vigia>