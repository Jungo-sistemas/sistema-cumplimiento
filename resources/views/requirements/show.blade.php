<x-layouts.vigia :title="$requirement->template?->name ?? 'Requerimiento'" :nav-context="$navContext">

    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index', array_filter(['company_id' => request('company_id', $asset?->company_id)])) }}"
           class="text-gray-600 hover:underline">
            Energético
        </a>

        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.show', $asset) }}" class="text-gray-600 hover:underline">
            {{ $asset->name }}
        </a>

        <span class="text-gray-400">›</span>

        <span class="text-gray-700 font-medium">
            <x-truncate max="max-w-[400px]" class="font-semibold text-gray-700">
                {{ $requirement->template?->name ?? $requirement->type }}
            </x-truncate>
        </span>
    </x-slot>

    @php
        $assetInactive = ($asset->status ?? null) === \App\Models\Asset::STATUS_INACTIVE
            || (method_exists($asset, 'isInactive') && $asset->isInactive());

        $hasOfficialDocument = $requirement->documents()->exists();

        $nonRenewalTasks = $requirement->tasks->filter(
            fn ($task) => $task->type !== \App\Models\Task::TYPE_RENEWAL
        );

        $totalNonRenewalTasks = $nonRenewalTasks->count();
        $doneNonRenewalTasks = $nonRenewalTasks->whereNotNull('completed_at')->count();

        $canUploadOfficialDocument = !$assetInactive; // TODO: re-enable task gate after testing

        $titleReq = $requirement->template?->name ?? $requirement->type;

        $hasOpenCheckout = $requirement->tasks()
            ->where('title', "Check in - {$titleReq}")
            ->whereNull('completed_at')
            ->exists();

        $canCheckout = !$assetInactive
            && !$hasOpenCheckout
            && $hasOfficialDocument;
    @endphp

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-6">
            <div class="space-y-1 min-w-0 flex-1">
                <h1 class="text-2xl font-bold text-[#1A428A] break-words">
                    {{ $asset->name }} -
                    <x-truncate max="max-w-[700px]">
                        {{ $requirement->template?->name ?? $requirement->type }}
                    </x-truncate>
                </h1>

                @if(auth()->user()->hasGroupScope() && $asset->company)
                    <div class="text-sm text-gray-500">
                        Empresa:
                        <span class="font-semibold text-gray-700">{{ $asset->company->name }}</span>
                    </div>
                @endif

                <div class="mt-2 flex items-center gap-2 flex-wrap">
                    <span class="text-xs px-3 py-1 rounded border
                        {{ $assetInactive ? 'bg-gray-100 text-gray-700 border-gray-300' : 'bg-green-50 text-green-700 border-green-200' }}">
                        {{ $assetInactive ? 'Sin Operación' : 'Operando' }}
                    </span>

                    <span class="text-xs px-3 py-1 rounded border
                        {{ $hasOfficialDocument ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' }}">
                        {{ $hasOfficialDocument ? 'Documento oficial OK' : 'Falta documento oficial' }}
                    </span>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0 flex-wrap lg:justify-end">
                <a href="{{ route('assets.requirements.history', [$asset, $requirement]) }}"
                   class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                    Ver historial
                </a>

                @if($canUploadOfficialDocument)
                    <a href="{{ route('assets.requirements.documents.index', [$asset, $requirement]) }}"
                       class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                        Documentación oficial
                    </a>
                @else
                    <button type="button" disabled
                        title="Debe existir al menos una tarea y todas las tareas no relacionadas con renovación deben estar completadas"
                        class="px-4 py-2 rounded-md border bg-gray-100 text-gray-500 border-gray-300 font-semibold cursor-not-allowed">
                        Documentación oficial
                    </button>
                @endif

                @if(!$assetInactive && (auth()->user()->isAdmin() || auth()->user()->isOperative()))
                    @if($requirement->status === \App\Enums\RequirementStatus::IN_TRANSIT)
                        <form method="POST" action="{{ route('assets.requirements.reopen', [$asset, $requirement]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="px-4 py-2 rounded-md border bg-white text-amber-700 border-amber-400 font-semibold hover:bg-amber-50">
                                Reabrir
                            </button>
                        </form>
                    @elseif($requirement->canBeMarkedInTransit())
                        <form method="POST" action="{{ route('assets.requirements.transit', [$asset, $requirement]) }}">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                class="px-4 py-2 rounded-md bg-indigo-600 text-white font-semibold hover:bg-indigo-700">
                                Marcar en trámite
                            </button>
                        </form>
                    @endif
                @endif

                <a href="{{ route('assets.show', $asset) }}"
                   class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                    Volver
                </a>
            </div>
        </div>

        {{-- Card resumen --}}
        <div class="mt-6 bg-gray-50 border rounded-xl p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 text-sm text-gray-700">
                <div class="space-y-1">
                    <div><strong>Tipo:</strong> {{ $asset->assetType->name ?? '-' }}</div>
                    <div><strong>Vence:</strong> {{ $requirement->due_date?->format('Y-m-d') ?? 'Sin fecha' }}</div>
                    <div>
                        <strong>Riesgo:</strong>
                        @switch(strtolower($requirement->risk_level ?? 'normal'))
                            @case('danger')
                                Peligro
                                @break
                            @case('warning')
                                Crítico
                                @break
                            @default
                                Normal
                        @endswitch
                    </div>
                    <div>
                        <strong>Estado:</strong>
                        @php
                            $reqStatus = $requirement->status?->value ?? '';
                            $reqStatusLabel = match($reqStatus) {
                                'in_transit' => 'En trámite',
                                default      => \App\Enums\RequirementStatus::tryFrom($reqStatus)?->label()
                                                ?? $requirement->status?->label()
                                                ?? 'Pendiente',
                            };
                        @endphp
                        <span class="inline-flex px-2 py-0.5 rounded border text-xs
                            {{ $reqStatus === 'in_transit'  ? 'bg-indigo-50 text-indigo-700 border-indigo-200' : '' }}
                            {{ $reqStatus === 'completed'   ? 'bg-green-50 text-green-700 border-green-200' : '' }}
                            {{ $reqStatus === 'expired'     ? 'bg-red-50 text-red-700 border-red-200' : '' }}
                            {{ $reqStatus === 'in_progress' ? 'bg-yellow-50 text-yellow-700 border-yellow-200' : '' }}
                            {{ !in_array($reqStatus, ['in_transit','completed','expired','in_progress']) ? 'bg-gray-100 text-gray-700 border-gray-300' : '' }}
                        ">{{ $reqStatusLabel }}</span>
                    </div>
                </div>

                <div class="space-y-1">
                    <div><strong>Progreso:</strong> {{ $requirement->progress }}%</div>
                    <div><strong>Tareas:</strong> {{ $doneNonRenewalTasks }}/{{ $totalNonRenewalTasks }}</div>

                    @if(auth()->user()->hasGroupScope() && $asset->company)
                        <div><strong>Empresa:</strong> {{ $asset->company->name }}</div>
                    @endif
                </div>

                <div class="space-y-1">
                    <div class="font-semibold text-[#1A428A] mb-2">
                        Vigencia del documento oficial
                    </div>

                    <div>
                        <strong>Emisión:</strong>
                        {{ $requirement->issued_at?->format('Y-m-d') ?? 'No registrada' }}
                    </div>

                    <div>
                        <strong>Vencimiento:</strong>
                        {{ $requirement->expires_at?->format('Y-m-d') ?? 'No registrado' }}
                    </div>

                    <div>
                        <strong>Estado de vigencia:</strong>
                        @if(!$requirement->expires_at)
                            <span class="inline-flex px-2 py-0.5 rounded border text-xs bg-gray-100 text-gray-700 border-gray-300">
                                Sin vigencia
                            </span>
                        @elseif($requirement->expires_at->isPast())
                            <span class="inline-flex px-2 py-0.5 rounded border text-xs bg-red-50 text-red-700 border-red-200">
                                Vencido
                            </span>
                        @elseif($requirement->expires_at->lte(now()->addDays(60)))
                            <span class="inline-flex px-2 py-0.5 rounded border text-xs bg-yellow-50 text-yellow-700 border-yellow-200">
                                Próximo a vencer
                            </span>
                        @else
                            <span class="inline-flex px-2 py-0.5 rounded border text-xs bg-green-50 text-green-700 border-green-200">
                                Vigente
                            </span>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Tareas --}}
        <div class="mt-8 bg-white border rounded-xl overflow-hidden">
            <div class="p-5 border-b flex items-center justify-between">
                <div class="font-semibold text-[#1A428A]">Tareas</div>

                @if((auth()->user()->isAdmin() || auth()->user()->isOperative()) && !$assetInactive)
                    <div class="flex items-center gap-2">
                        @if(!$hasOfficialDocument)
                            <button type="button" disabled
                                title="Primero debes subir la documentación oficial."
                                class="px-4 py-2 rounded-md border bg-gray-100 text-gray-400 cursor-not-allowed">
                                Check out
                            </button>
                        @elseif($hasOpenCheckout)
                            <button type="button" disabled
                                title="Ya hay un Check in pendiente. Completa esa tarea."
                                class="px-4 py-2 rounded-md border bg-gray-100 text-gray-400 cursor-not-allowed">
                                Check out
                            </button>
                        @else
                            <button type="button"
                                onclick="document.getElementById('checkout-modal').showModal()"
                                class="px-4 py-2 rounded-md border bg-white text-[#1A428A] font-semibold hover:bg-gray-50">
                                Check out
                            </button>
                        @endif

                        <a href="{{ route('requirements.tasks.create', $requirement) }}"
                           class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]
                           {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                            Añadir tarea
                        </a>
                    </div>
                @endif
            </div>

            <div class="p-5">
                <div class="space-y-4">
                    @forelse($requirement->tasks as $task)
                        @php
                            $hasDocs = ($task->documents_count ?? 0) > 0;
                            $taskCompleted = (bool) $task->completed_at;
                            $taskResponsible = $task->users->first();
                            $isRenewal = $task->type === \App\Models\Task::TYPE_RENEWAL;
                        @endphp

                        <div class="rounded-xl border border-gray-200 bg-white p-5">
                            <div class="font-semibold text-gray-900 text-xl">
                                {{ $task->title }}
                            </div>

                            <div class="mt-2 text-sm text-gray-500">
                                Responsable:
                                <span class="font-medium text-gray-700">
                                    {{ $taskResponsible?->name ?? 'Sin asignar' }}
                                </span>
                            </div>

                            <div class="text-sm text-gray-600 mt-2 flex flex-wrap items-center gap-x-3 gap-y-2">
                                <span>Estado:
                                    <span class="px-2 py-0.5 rounded border text-xs bg-gray-50">
                                        {{ $task->status?->label() ?? 'Pendiente' }}
                                    </span>
                                </span>

                                <span class="text-gray-300">|</span>
                                <span>Vence: {{ $task->due_date?->format('Y-m-d') ?? '-' }}</span>

                                <span class="text-gray-300">|</span>

                                @unless($isRenewal)
                                    <span>Evidencias: {{ $task->documents_count ?? 0 }}</span>

                                    @if($hasDocs)
                                        <span class="text-xs px-2 py-0.5 rounded border bg-green-50 text-green-700 border-green-200">
                                            Evidencia OK
                                        </span>
                                    @else
                                        <span class="text-xs px-2 py-0.5 rounded border bg-red-50 text-red-700 border-red-200">
                                            Falta evidencia
                                        </span>
                                    @endif
                                @endunless

                                @if($isRenewal)
                                    <span class="text-xs px-2 py-0.5 rounded border bg-blue-50 text-[#1A428A] border-blue-200">
                                        Renovación
                                    </span>
                                @endif
                            </div>

                                <div class="mt-4 pt-3 border-t flex flex-wrap justify-end gap-2">
                                    @if($isRenewal)
                                        <a href="{{ route('assets.requirements.documents.index', [$asset, $requirement]) }}"
                                           class="px-4 py-2 rounded-md border font-semibold
                                           {{ $assetInactive ? 'opacity-50 pointer-events-none bg-gray-100 text-gray-500 border-gray-300' : 'bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50' }}">
                                            Documentación oficial
                                        </a>

                                        @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                            <form method="POST" action="{{ route('requirements.tasks.destroy', [$requirement, $task]) }}"
                                                  onsubmit="return confirm('¿Eliminar esta tarea?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-md bg-[#DB0000] text-white font-semibold hover:bg-red-700
                                                    {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    @else
                                        <a href="{{ route('tasks.documents.index', $task) }}"
                                           class="px-4 py-2 rounded-md border font-semibold bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
                                            {{ $hasDocs ? 'Ver evidencias (' . ($task->documents_count ?? 0) . ')' : 'Subir evidencia' }}
                                        </a>

                                        @if(auth()->user()->isAdmin() || auth()->user()->isOperative())
                                            @if(!$taskCompleted)
                                                <form method="POST" action="{{ route('requirements.tasks.complete', [$requirement, $task]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="px-4 py-2 rounded-md font-semibold
                                                        {{ ($assetInactive || !$hasDocs) ? 'bg-gray-100 text-gray-500 border border-gray-300 cursor-not-allowed' : 'bg-[#1A428A] text-white hover:bg-[#15356d]' }}"
                                                        {{ ($assetInactive || !$hasDocs) ? 'disabled' : '' }}>
                                                        Completar
                                                    </button>
                                                </form>
                                            @else
                                                <form method="POST" action="{{ route('requirements.tasks.reopen', [$requirement, $task]) }}">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                        class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50
                                                        {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                                                        Reabrir
                                                    </button>
                                                </form>
                                            @endif

                                            <a href="{{ route('requirements.tasks.edit', [$requirement, $task]) }}"
                                               class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50
                                               {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                                                Editar
                                            </a>

                                            <form method="POST" action="{{ route('requirements.tasks.destroy', [$requirement, $task]) }}"
                                                  onsubmit="return confirm('¿Eliminar esta tarea?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit"
                                                    class="px-4 py-2 rounded-md bg-[#DB0000] text-white font-semibold hover:bg-red-700
                                                    {{ $assetInactive ? 'opacity-50 pointer-events-none' : '' }}">
                                                    Eliminar
                                                </button>
                                            </form>
                                        @endif
                                    @endif
                                </div>
                        </div>
                    @empty
                        <div class="text-sm text-gray-500">No hay tareas todavía.</div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

    {{-- Modal Check out --}}
    <dialog id="checkout-modal" class="rounded-xl p-0 w-full max-w-md backdrop:bg-black/40">
        <form id="checkout-form"
              method="POST"
              action="{{ route('assets.requirements.checkout', [$asset, $requirement]) }}"
              class="p-6 space-y-4">
            @csrf

            <div class="text-lg font-semibold text-[#1A428A]">
                Check out de carpeta física
            </div>

            <p class="text-sm text-gray-600">
                Indica cuándo vas a regresar el documento físico y quién será responsable del check in.
            </p>

            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Fecha de regreso
                </label>

                <input type="date"
                       name="return_at"
                       required
                       class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A]">
            </div>

            <div>
                <label class="block text-xs text-gray-500 mb-1">
                    Responsable del check in
                </label>

                <select name="responsible_user_id"
                        required
                        class="w-full rounded-md border-gray-300 focus:border-[#1A428A] focus:ring-[#1A428A]">
                    <option value="">Selecciona un responsable</option>

                    @foreach($responsibles as $responsible)
                        <option value="{{ $responsible->id }}"
                            {{ (int) old('responsible_user_id', $asset->responsible_user_id) === (int) $responsible->id ? 'selected' : '' }}>
                            {{ $responsible->name }}
                        </option>
                    @endforeach
                </select>

                @error('responsible_user_id')
                    <div class="mt-1 text-sm text-red-600">{{ $message }}</div>
                @enderror
            </div>
        </form>

        <div class="flex justify-end gap-2 pt-2 p-6">
            <button type="button"
                    onclick="document.getElementById('checkout-modal').close()"
                    class="px-4 py-2 rounded-md border bg-white text-gray-700 font-semibold hover:bg-gray-50">
                Cancelar
            </button>

            <button type="submit"
                    form="checkout-form"
                    class="px-4 py-2 rounded-md bg-[#1A428A] text-white font-semibold hover:bg-[#15356d]">
                Confirmar check out
            </button>
        </div>
    </dialog>
</x-layouts.vigia>