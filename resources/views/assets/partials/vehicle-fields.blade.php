{{--
    Partial: vehicle-fields.blade.php
    Requires Alpine.js parent scope with: isVehicle, marca, modelo, placas
    $asset is optional (for edit mode)
--}}
<div x-show="isVehicle" x-transition style="display:none" class="mt-6">
    <div class="rounded-lg border border-blue-200 bg-blue-50 p-5">

        <h3 class="text-sm font-semibold text-[#1A428A] mb-4 flex items-center gap-2">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none"
                 viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round"
                      d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10l1 1h1m8-1h2l3-3-1-4H9l-1 4 1 3h1"/>
            </svg>
            Datos del vehículo
        </h3>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">

            {{-- No. Económico --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">No. Económico</label>
                <input type="text" name="no_economico"
                       value="{{ old('no_economico', $asset->no_economico ?? '') }}"
                       :disabled="!isVehicle"
                       placeholder="Ej. ATQ-001"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('no_economico')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Número de serie --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Número de serie / NIV</label>
                <input type="text" name="numero_serie"
                       value="{{ old('numero_serie', $asset->numero_serie ?? '') }}"
                       :disabled="!isVehicle"
                       placeholder="Ej. 3AKJHHDR0FSFK1234"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('numero_serie')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Marca --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Marca</label>
                <input type="text" name="marca"
                       x-model="marca"
                       @input="syncName()"
                       :disabled="!isVehicle"
                       placeholder="Ej. FREIGHTLINER"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('marca')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Modelo --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Modelo / Año</label>
                <input type="text" name="modelo"
                       x-model="modelo"
                       @input="syncName()"
                       :disabled="!isVehicle"
                       placeholder="Ej. CASCADIA 2022"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('modelo')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Placas --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Placas</label>
                <input type="text" name="placas"
                       x-model="placas"
                       @input="syncName()"
                       :disabled="!isVehicle"
                       placeholder="Ej. ABC1234"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A] uppercase">
                @error('placas')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Marca del recipiente --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Marca del recipiente</label>
                <input type="text" name="marca_recipiente"
                       value="{{ old('marca_recipiente', $asset->marca_recipiente ?? '') }}"
                       :disabled="!isVehicle"
                       placeholder="Ej. MEXITANK"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('marca_recipiente')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Capacidad litros --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Capacidad del recipiente (litros)</label>
                <input type="number" name="capacidad_litros" min="1"
                       value="{{ old('capacidad_litros', $asset->capacidad_litros ?? '') }}"
                       :disabled="!isVehicle"
                       placeholder="Ej. 10000"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('capacidad_litros')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

            {{-- Número de serie del recipiente --}}
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Núm. serie del recipiente</label>
                <input type="text" name="serie_recipiente"
                       value="{{ old('serie_recipiente', $asset->serie_recipiente ?? '') }}"
                       :disabled="!isVehicle"
                       placeholder="Ej. REC-2022-00456"
                       class="w-full rounded-md border-gray-300 text-sm focus:border-[#1A428A] focus:ring-[#1A428A]">
                @error('serie_recipiente')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>

        </div>
    </div>
</div>
