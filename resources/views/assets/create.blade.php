{{-- resources/views/assets/create.blade.php --}}
<x-layouts.vigia :title="'Crear un activo'">
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index') }}" class="text-gray-600 hover:underline">
            Energético
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Crear un activo</span>
    </x-slot>

    @php
        $selectClass = "mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm";
        $user = auth()->user();
        $nameFieldClass = $user->hasGroupScope() ? '' : 'md:col-span-2';
    @endphp

    {{-- Select2 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

    <div class="bg-white rounded-xl shadow p-6 max-w-5xl">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Crear un activo</h1>

        @if ($errors->any())
            <div class="mt-4 p-4 border border-red-300 bg-red-50 rounded-lg">
                <ul class="list-disc list-inside text-red-700 text-sm">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('assets.store') }}" class="mt-6"
              x-data="{
                  typeId: '{{ old('asset_type_id', '') }}',
                  vehicleIds: @json($vehicleTypeIds),
                  marca: '{{ old('marca', '') }}',
                  modelo: '{{ old('modelo', '') }}',
                  placas: '{{ old('placas', '') }}',
                  nameCustomized: {{ old('name') ? 'true' : 'false' }},
                  get isVehicle() { return this.vehicleIds.includes(Number(this.typeId)); },
                  syncName() {
                      if (this.nameCustomized) return;
                      const parts = [this.marca.trim(), this.modelo.trim()].filter(Boolean);
                      if (this.placas.trim()) parts.push('— ' + this.placas.trim().toUpperCase());
                      this.$refs.nameInput.value = parts.join(' ').toUpperCase();
                  }
              }">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    @php
                        $user = auth()->user();
                    @endphp

                    {{-- Empresa --}}
                    @if($user->hasGroupScope())
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">
                                Empresa
                            </label>

                            <select
                                name="company_id"
                                class="{{ $selectClass }}"
                                required
                            >
                                <option value="">Selecciona una empresa</option>

                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}"
                                        @selected(old('company_id', $selectedCompanyId) == $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('company_id')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    @else
                        <input type="hidden" name="company_id" value="{{ $selectedCompanyId }}">
                    @endif
                </div>
                
                {{-- Nombre --}}
                <div class="{{ $nameFieldClass }}">
                    <label class="block text-sm font-medium text-gray-700">
                        Nombre de activo
                        <span x-show="isVehicle" class="ml-1 text-xs text-blue-500 font-normal">(auto-generado desde marca + modelo + placas)</span>
                    </label>
                    <input
                        type="text"
                        name="name"
                        x-ref="nameInput"
                        @input="nameCustomized = true"
                        value="{{ old('name') }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    />
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Código --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Código del activo
                    </label>
                    <input
                        type="text"
                        name="code"
                        id="code"
                        placeholder="Ej. LP/26139/ALM/2024"
                        value="{{ old('code') }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                    />
                    @error('code')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Tipo --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Selecciona un tipo
                    </label>

                    <select
                        name="asset_type_id"
                        id="asset_type_id"
                        x-model="typeId"
                        class="{{ $selectClass }}"
                        required
                    >
                        <option value="">-- Selecciona tipo --</option>

                        @foreach($assetTypes as $type)
                            <option
                                value="{{ $type->id }}"
                                data-name="{{ \Illuminate\Support\Str::lower(trim($type->name)) }}"
                                @selected((string) old('asset_type_id') === (string) $type->id)
                            >
                                {{ $type->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('asset_type_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Responsable --}}
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
                            <option value="{{ $u->id }}" @selected((string) old('responsible_user_id') === (string) $u->id)>
                                {{ $u->name }}
                            </option>
                        @endforeach
                    </select>

                    @error('responsible_user_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Dirección --}}
                <div class="md:col-span-2 border rounded-lg p-4 space-y-4">
                    <div class="text-sm font-semibold text-gray-700">Dirección</div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-medium text-gray-700">Calle y número</label>
                            <input
                                type="text"
                                name="street_address"
                                value="{{ old('street_address') }}"
                                class="{{ $selectClass }}"
                            >
                            @error('street_address')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Colonia</label>
                            <input
                                type="text"
                                name="colonia"
                                value="{{ old('colonia') }}"
                                class="{{ $selectClass }}"
                            >
                            @error('colonia')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Código postal</label>
                            <input
                                type="text"
                                name="postal_code"
                                value="{{ old('postal_code') }}"
                                maxlength="10"
                                class="{{ $selectClass }}"
                            >
                            @error('postal_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Municipio / Ciudad</label>
                            <input
                                type="text"
                                name="municipality"
                                value="{{ old('municipality') }}"
                                class="{{ $selectClass }}"
                            >
                            @error('municipality')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Estado</label>
                            <select
                                name="location"
                                class="{{ $selectClass }}"
                                required
                            >
                                <option value="">-- Selecciona estado --</option>

                                @foreach($mexicoStates as $state)
                                    <option value="{{ $state }}" @selected(old('location') === $state)>
                                        {{ $state }}
                                    </option>
                                @endforeach
                            </select>
                            @error('location')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>
                </div>

                {{-- Fecha inicio --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Fecha de inicio de operaciones
                    </label>
                    <input
                        type="date"
                        name="compliance_start_date"
                        value="{{ old('compliance_start_date') }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    />
                    @error('compliance_start_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Fecha límite --}}
                <div>
                    <label class="block text-sm font-medium text-gray-700">
                        Fecha límite para cumplir los requerimientos
                    </label>
                    <input
                        type="date"
                        name="compliance_due_date"
                        value="{{ old('compliance_due_date') }}"
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                    >
                    @error('compliance_due_date')
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
                                {{ old('parent_asset_id') == $parent->id ? 'selected' : '' }}
                            >
                                {{ $parent->name }} ({{ $parent->assetType->name ?? '-' }})
                            </option>
                        @endforeach
                    </select>

                    @error('parent_asset_id')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Sección datos del vehículo --}}
            @include('assets.partials.vehicle-fields')

            <div class="mt-6 flex justify-end gap-3">
                <a
                    href="{{ route('assets.index') }}"
                    class="px-4 py-2 rounded-md border border-gray-300 text-gray-700 font-semibold hover:bg-gray-50"
                >
                    Cancelar
                </a>

                <button
                    type="submit"
                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]"
                >
                    Crear activo
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
                placeholder: '-- Selecciona un responsable --',
                allowClear: true,
                width: '100%',
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

        /* Importante: NO forzar width:100% aquí */
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