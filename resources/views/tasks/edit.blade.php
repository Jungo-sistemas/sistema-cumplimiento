{{-- resources/views/tasks/edit.blade.php --}}
@php
    /** @var \App\Models\AssetRequirement $requirement */
    /** @var \App\Models\Task $task */

    $asset = $asset ?? $requirement->asset;
    $folderTitle = $requirement->template?->name ?? $requirement->type ?? 'Carpeta';

    $assetInactive = $asset
        ? (($asset->status ?? null) === \App\Models\Asset::STATUS_INACTIVE
            || (method_exists($asset, 'isInactive') && $asset->isInactive()))
        : false;

    $isCompleted = (bool) $task->completed_at;
@endphp

<x-layouts.vigia title="Editar tarea" :nav-context="$navContext">

    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset?->company_id)])) }}"
           class="text-gray-600 hover:underline">
            Energético
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
            Editar tarea
        </span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div>
                <h1 class="text-2xl font-bold text-[#1A428A]">Editar tarea</h1>
                <div class="text-sm text-gray-500 mt-1">
                    Carpeta: <span class="font-semibold text-gray-700">{{ $folderTitle }}</span>
                    @if($asset)
                        · Activo: <span class="font-semibold text-gray-700">{{ $asset->name }}</span>
                    @endif
                    @if(auth()->user()->hasGroupScope() && $asset?->company)
                        · Empresa: <span class="font-semibold text-gray-700">{{ $asset->company->name }}</span>
                    @endif
                </div>

                <div class="mt-2 flex items-center gap-2 flex-wrap">
                    @if($asset)
                        <span class="text-xs px-3 py-1 rounded border
                            {{ $assetInactive ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-green-50 text-green-700 border-green-200' }}">
                            {{ $assetInactive ? 'ASSET INACTIVO' : 'ASSET ACTIVO' }}
                        </span>
                    @endif

                    <span class="text-xs px-3 py-1 rounded border
                        {{ $isCompleted ? 'bg-green-50 text-green-700 border-green-200' : 'bg-gray-50 text-gray-700 border-gray-200' }}">
                        {{ $isCompleted ? 'TAREA COMPLETADA' : 'TAREA PENDIENTE' }}
                    </span>
                </div>
            </div>
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
        <form method="POST" action="{{ route('requirements.tasks.update', [$requirement, $task]) }}" class="mt-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 gap-5">
                {{-- Título --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700">Título</label>
                    <input
                        type="text"
                        name="title"
                        value="{{ old('title', $task->title) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        required
                        {{ $assetInactive ? 'disabled' : '' }}
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
                        {{ $assetInactive ? 'disabled' : '' }}
                    >{{ old('description', $task->description) }}</textarea>
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
                        {{ $assetInactive ? 'disabled' : '' }}
                    >
                        <option value="">-- Selecciona un responsable --</option>

                        @foreach($responsibles as $u)
                            <option
                                value="{{ $u->id }}"
                                @selected((int) old('responsible_user_id', $selectedResponsibleId ?? null) === (int) $u->id)
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
                        value="{{ old('due_date', optional($task->due_date)->format('Y-m-d')) }}"
                        class="mt-1 block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm"
                        {{ $assetInactive ? 'disabled' : '' }}
                    >
                    <p class="mt-1 text-xs text-gray-500">
                        Todas las tareas requieren evidencia obligatoria.
                    </p>
                    @error('due_date')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
            </div>

            {{-- Acciones --}}
            <div class="mt-6 flex items-center gap-3">
                <button
                    type="submit"
                    class="px-5 py-2 rounded-md font-semibold transition
                    {{ $assetInactive ? 'bg-gray-100 text-gray-500 border border-gray-300 cursor-not-allowed' : 'bg-[#1A428A] text-white hover:bg-[#15356d]' }}"
                    {{ $assetInactive ? 'disabled' : '' }}
                >
                    Actualizar
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