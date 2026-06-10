{{-- resources/views/assets/edit.blade.php --}}
<x-layouts.vigia :title="'Editar: ' . $asset->name">
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', old('company_id', $asset->company_id))])) }}"
           class="text-gray-600 hover:underline">
            Activos y Actividades
        </a>
        <span class="text-gray-400">›</span>
        <a href="{{ route('assets.show', $asset) }}" class="text-gray-600 hover:underline">
            {{ $asset->name }}
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Editar</span>
    </x-slot>

    @php
        $user = auth()->user();
        $selectedCompanyId = old('company_id', $asset->company_id);
    @endphp

    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold text-[#1A428A]">Editar activo</h1>

                @if($user->hasGroupScope() && $asset->company)
                    <p class="mt-1 text-sm text-gray-500">
                        Empresa actual: <span class="font-medium text-gray-700">{{ $asset->company->name }}</span>
                    </p>
                @endif
            </div>

            <a href="{{ route('assets.show', $asset) }}"
               class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50">
                Volver
            </a>
        </div>

        @if ($errors->any())
            <div class="mt-6 p-4 border border-red-300 bg-red-50 rounded-lg">
                <div class="font-semibold text-red-700 mb-2">Revisa los siguientes campos:</div>
                <ul class="list-disc list-inside text-red-700 text-sm space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('assets.update', $asset) }}" class="mt-6"
              x-data="{
                  typeId: '{{ old('asset_type_id', $asset->asset_type_id) }}',
                  vehicleIds: @json($vehicleTypeIds),
                  marca: '{{ old('marca', $asset->marca ?? '') }}',
                  modelo: '{{ old('modelo', $asset->modelo ?? '') }}',
                  placas: '{{ old('placas', $asset->placas ?? '') }}',
                  nameCustomized: true,
                  get isVehicle() { return this.vehicleIds.includes(Number(this.typeId)); },
                  syncName() {}
              }">
            @csrf
            @method('PUT')

            @if($user->hasGroupScope())
                <div class="mb-6">
                    <label for="company_id" class="block text-sm font-semibold text-gray-700">Empresa</label>
                    <select
                        name="company_id"
                        id="company_id"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                        <option value="">-- Selecciona empresa --</option>
                        @foreach($companies as $company)
                            <option value="{{ $company->id }}" @selected((int) $selectedCompanyId === (int) $company->id)>
                                {{ $company->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('company_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            @else
                <input type="hidden" name="company_id" value="{{ $user->company_id }}">
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                {{-- Nombre --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Nombre</label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name', $asset->name) }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Tipo</label>
                    <select
                        name="asset_type_id"
                        id="asset_type_id"
                        x-model="typeId"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                        <option value="">-- Selecciona tipo --</option>
                        @foreach($assetTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                data-name="{{ \Illuminate\Support\Str::lower(trim($type->name)) }}"
                                @selected(old('asset_type_id', $asset->asset_type_id) == $type->id)
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('asset_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Ubicación --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Ubicación</label>
                    <select
                        name="location"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                        <option value="">-- Selecciona ubicación --</option>

                        @foreach($mexicoStates as $state)
                            <option value="{{ $state }}" @selected(old('location', $asset->location) === $state)>
                                {{ $state }}
                            </option>
                        @endforeach
                    </select>
                    @error('location')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Código --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Código</label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code', $asset->code) }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Si lo dejas igual, se mantiene.
                    </p>
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Responsable --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">Responsable</label>
                    <select
                        id="responsible_user_id"
                        name="responsible_user_id"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    >
                        <option value="">-- Ninguno --</option>
                        @foreach($responsibles as $u)
                            <option
                                value="{{ $u->id }}"
                                @selected((int) old('responsible_user_id', $asset->responsible_user_id) === (int) $u->id)
                            >
                                {{ $u->name }}{{ $u->email ? ' (' . $u->email . ')' : '' }}
                            </option>
                        @endforeach
                    </select>

                    @error('responsible_user_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Bóveda documental --}}
                <div>
                    <label for="vault_location" class="block text-sm font-semibold text-gray-700">
                        Bóveda documental
                    </label>
                    <input
                        type="text"
                        name="vault_location"
                        id="vault_location"
                        value="{{ old('vault_location', $asset->vault_location) }}"
                        class="mt-1 w-full rounded-md border-gray-300 shadow-sm focus:border-[#1A428A] focus:ring-[#1A428A] text-sm"
                        placeholder="Ej. Bóveda A - Estante 3"
                    >
                    @error('vault_location')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Activo principal relacionado --}}
                <div id="parent-asset-wrapper">
                    <label for="parent_asset_id" class="block text-sm font-medium text-gray-700 mb-1">
                        Activo principal relacionado
                    </label>

                    <select
                        name="parent_asset_id"
                        id="parent_asset_id"
                        class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-700 focus:border-[#1A428A] focus:ring-[#1A428A]"
                    >
                        <option value="">Sin relación</option>

                        @foreach($parentAssets as $parent)
                            <option
                                value="{{ $parent->id }}"
                                {{ old('parent_asset_id', $asset->parent_asset_id ?? '') == $parent->id ? 'selected' : '' }}
                            >
                                {{ $parent->name }} ({{ $parent->assetType->name ?? '-' }})
                            </option>
                        @endforeach
                    </select>

                    <p class="mt-1 text-xs text-gray-500">
                        Disponible solo para activos tipo tracto, semirremolque, ATQ, cilindrera y carro tanque.
                    </p>

                    @error('parent_asset_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Sección datos del vehículo --}}
            @include('assets.partials.vehicle-fields', ['asset' => $asset])

            <div class="mt-8 flex justify-end gap-3 border-t pt-6">
                <a href="{{ route('assets.show', $asset) }}"
                   class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50">
                    Cancelar
                </a>

                <button type="submit"
                        class="px-5 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                    Guardar cambios
                </button>
            </div>
        </form>
    </div>

    {{-- Select2 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            $('#responsible_user_id').select2({
                width: '100%',
                placeholder: '-- Ninguno --',
                allowClear: true,
                dropdownParent: $('#responsible_user_id').closest('div')
            });

            const assetTypeSelect = document.getElementById('asset_type_id');
            const parentAssetSelect = document.getElementById('parent_asset_id');
            const parentAssetWrapper = document.getElementById('parent-asset-wrapper');

            const allowedTypes = [
                'tracto',
                'semirremolque',
                'atq',
                'cilindrera',
                'carro tanque',
                'carros tanque'
            ];

            function toggleParentAssetField() {
                const selectedOption = assetTypeSelect.options[assetTypeSelect.selectedIndex];
                const selectedTypeName = (selectedOption?.dataset?.name || '').trim().toLowerCase();

                const shouldEnable = allowedTypes.includes(selectedTypeName);

                parentAssetSelect.disabled = !shouldEnable;

                if (!shouldEnable) {
                    parentAssetSelect.value = '';
                    parentAssetWrapper.classList.add('opacity-60');
                } else {
                    parentAssetWrapper.classList.remove('opacity-60');
                }
            }

            assetTypeSelect.addEventListener('change', toggleParentAssetField);

            toggleParentAssetField();
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