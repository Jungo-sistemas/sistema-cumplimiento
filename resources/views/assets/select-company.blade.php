{{-- Paso previo a "Crear un activo" para usuarios sin empresa asignada que pueden elegir entre varias. --}}
<x-layouts.vigia :title="'Crear un activo'">
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index') }}" class="text-gray-600 hover:underline">
            Energético
        </a>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Crear un activo</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6 max-w-xl">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Crear un activo</h1>
        <p class="mt-1 text-sm text-gray-500">
            Primero selecciona la empresa en la que quieres registrar el activo.
        </p>

        <form method="GET" action="{{ route('assets.create') }}" class="mt-6 space-y-4">
            <div>
                <label for="company_id" class="block text-sm font-medium text-gray-700">Empresa</label>
                <select id="company_id"
                        name="company_id"
                        required
                        class="mt-1 w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                    <option value="">Selecciona una empresa</option>
                    @foreach($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('assets.index') }}"
                   class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    Continuar
                </button>
            </div>
        </form>
    </div>
</x-layouts.vigia>
