<x-layouts.vigia title="Solicitud de Inversiones">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Solicitud de Inversiones</span>
    </x-slot>

    @php
        $user = auth()->user();
        // Razones sociales agrupadas por tenedora, en el orden de la tabla del cliente.
        $entityGroups = $entities->groupBy(fn ($e) => $e->holding ?: 'Otras');
        $showCompany = $entities->count() > 1 || $user->hasGroupScope() || $user->hasMultipleCompanies();
    @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-brand">Solicitud de Inversiones</h1>
        <div class="flex items-center gap-3">
            @if($user->isAdmin() || $user->isOperative())
                <button type="button"
                        x-data
                        @click="$dispatch('open-modal', 'create-investment-request')"
                        class="px-4 py-2 rounded-md bg-brand text-white text-sm font-semibold hover:bg-brand-dark">
                    + Nueva solicitud
                </button>
            @endif
        </div>
    </div>

    {{-- FILTROS --}}
    <form method="GET" action="{{ route('investment-requests.index') }}"
          class="mt-4 flex flex-wrap items-end gap-3">
        <div class="flex-1 min-w-[220px]">
            <label class="block text-xs text-gray-500 mb-1">Buscar</label>
            <input type="text"
                   name="q"
                   value="{{ request('q') }}"
                   placeholder="Nombre del Anexo 8 o solicitante"
                   class="w-full rounded-md border-gray-300 text-sm">
        </div>

        @if($entities->count() > 1)
            <div class="min-w-[180px]">
                <label class="block text-xs text-gray-500 mb-1">Empresa</label>
                <select name="entity_id" class="w-full rounded-md border-gray-300 text-sm">
                    <option value="">Todas</option>
                    @foreach($entityGroups as $holding => $group)
                        <optgroup label="{{ $holding }}">
                            @foreach($group as $entity)
                                <option value="{{ $entity->id }}" @selected($selectedEntityId === $entity->id)>{{ $entity->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
        @endif

        <button type="submit"
                class="px-5 py-2 rounded-md bg-brand text-white text-sm font-semibold hover:bg-brand-dark">
            Filtrar
        </button>

        <a href="{{ route('investment-requests.index') }}"
           class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
            Limpiar
        </a>
    </form>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 p-3 text-green-800 text-sm">
            {{ session('success') }}
        </div>
    @endif

    {{-- TABLA --}}
    <div class="mt-6 bg-white border rounded-lg shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">

                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                        <th class="text-left px-4 py-3">Concepto</th>
                        @if($showCompany)
                            <th class="text-left px-4 py-3">Empresa</th>
                        @endif
                        <th class="text-left px-4 py-3">Monto</th>
                        <th class="text-left px-4 py-3">Solicitante</th>
                        <th class="text-left px-4 py-3">Fecha límite</th>
                        <th class="text-left px-4 py-3">Estado</th>
                        <th class="text-left px-4 py-3">Archivo</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>

                <tbody>
                    @forelse($investmentRequests as $investmentRequest)
                        <tr class="border-t border-gray-100 text-sm text-gray-700 hover:bg-gray-50 cursor-pointer"
                            ondblclick="window.location.href='{{ route('investment-requests.show', $investmentRequest) }}'">

                            {{-- Concepto --}}
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900 max-w-xs truncate">{{ $investmentRequest->concept }}</div>
                            </td>

                            {{-- Empresa --}}
                            @if($showCompany)
                                <td class="px-4 py-3">
                                    {{ $investmentRequest->displayCompanyName() ?? '—' }}
                                </td>
                            @endif

                            {{-- Monto --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                ${{ number_format($investmentRequest->amount) }}
                            </td>

                            {{-- Solicitante --}}
                            <td class="px-4 py-3">
                                {{ $investmentRequest->requester?->name ?? '—' }}
                            </td>

                            {{-- Fecha límite --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                {{ $investmentRequest->deadline_at?->format('d/m/Y') ?? '—' }}
                            </td>

                            {{-- Estado --}}
                            <td class="px-4 py-3">
                                <span class="inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium {{ $investmentRequest->status->badgeClasses() }}">
                                    {{ $investmentRequest->status->label() }}
                                </span>
                                @if($investmentRequest->approvals->isNotEmpty())
                                    @php
                                        $done = $investmentRequest->approvals->where('status', 'approved')->count();
                                        $myTurn = $investmentRequest->approvals->contains(fn ($a) => $a->isPending() && $a->user_id === $user->id);
                                    @endphp
                                    <div class="text-xs text-gray-500 mt-1 whitespace-nowrap">
                                        {{ $done }} de {{ $investmentRequest->approvals->count() }} aprobaciones
                                    </div>
                                    @if($myTurn)
                                        <div class="text-xs font-semibold text-brand mt-0.5 whitespace-nowrap">Es tu turno de aprobar</div>
                                    @endif
                                @endif
                            </td>

                            {{-- Archivo --}}
                            <td class="px-4 py-3">
                                @if($investmentRequest->hasFile())
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">
                                        ✓ Anexo 8
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-2.5 py-0.5 text-xs font-medium text-gray-500">
                                        Sin archivo
                                    </span>
                                @endif
                            </td>

                            {{-- Acción --}}
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('investment-requests.show', $investmentRequest) }}"
                                   class="font-semibold text-blue-600 hover:underline whitespace-nowrap">
                                    Ver →
                                </a>
                            </td>

                        </tr>
                    @empty
                        <tr class="border-t">
                            <td colspan="{{ $showCompany ? 8 : 7 }}"
                                class="px-6 py-6 text-center text-gray-500">
                                {{ request()->filled('q') ? 'No hay solicitudes que coincidan con la búsqueda.' : 'No hay solicitudes de inversión registradas.' }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>

            </table>
        </div>

        @if($investmentRequests->hasPages())
            <div class="px-4 py-3 border-t bg-gray-50">
                {{ $investmentRequests->links() }}
            </div>
        @endif
    </div>

    {{-- MODAL: Nueva solicitud --}}
    @if($user->isAdmin() || $user->isOperative())
        <x-modal name="create-investment-request" :show="$errors->createInvestmentRequest->isNotEmpty()" focusable maxWidth="lg">
            <form method="POST"
                  action="{{ route('investment-requests.store') }}"
                  enctype="multipart/form-data"
                  x-data="{
                      entityId: @js((string) old('entity_id', $entities->count() === 1 ? $entities->first()->id : ($selectedEntityId ?? ''))),
                      flows: @js((object) $approvalFlows),
                      get flow() { return this.flows[this.entityId] ?? null; },
                  }"
                  x-init="$nextTick(() => initPersonPicker($refs.requester, { multiple: false }))"
                  class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-brand mb-4">Nueva solicitud de inversión</h2>

                <div class="space-y-4">

                    {{-- Empresa --}}
                    @if($entities->count() > 1)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Empresa <span class="text-red-500">*</span>
                            </label>
                            <select name="entity_id" required x-model="entityId"
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">— Seleccionar empresa —</option>
                                @foreach($entityGroups as $holding => $group)
                                    <optgroup label="{{ $holding }}">
                                        @foreach($group as $entity)
                                            <option value="{{ $entity->id }}">{{ $entity->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                        </div>
                    @elseif($entities->count() === 1)
                        <input type="hidden" name="entity_id" value="{{ $entities->first()->id }}">
                        <div class="text-sm text-gray-700">
                            <span class="font-medium">Empresa:</span> {{ $entities->first()->name }}
                        </div>
                    @else
                        <p class="rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800">
                            Tus empresas no tienen flujo de aprobación de Anexos 8. Pide a un administrador que lo configure.
                        </p>
                    @endif

                    {{-- Flujo de aprobación: fijo por empresa, solo se muestra --}}
                    <div class="rounded-lg border border-gray-200 p-4">
                        <div class="text-sm font-medium text-gray-700">Flujo de aprobación</div>

                        <template x-if="!entityId">
                            <p class="text-xs text-gray-500 mt-1">Elige la empresa para ver quiénes aprueban.</p>
                        </template>

                        <template x-if="entityId && flow">
                            <div>
                                <p class="text-xs text-gray-500 mt-1">
                                    Aprueban uno tras otro en este orden. A cada persona se le avisa por correo cuando es su turno.
                                </p>
                                <ol class="mt-3 space-y-2">
                                    <template x-for="(step, i) in flow.steps" :key="i">
                                        <li class="flex items-center gap-3 text-sm">
                                            <span class="h-6 w-6 shrink-0 rounded-full text-xs font-semibold flex items-center justify-center"
                                                  :class="step.missing ? 'bg-gray-200 text-gray-500' : 'bg-brand text-white'"
                                                  x-text="i + 1"></span>
                                            <span class="w-20 shrink-0 text-xs font-semibold uppercase tracking-wide text-gray-500" x-text="step.position"></span>
                                            <span :class="step.missing ? 'text-gray-400' : 'text-gray-900'" x-text="step.name"></span>
                                            <span x-show="step.missing" class="text-xs text-amber-700">(sin usuario)</span>
                                        </li>
                                    </template>
                                </ol>
                                <p x-show="flow.problem" x-text="flow.problem"
                                   class="mt-3 rounded-md bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800"></p>
                            </div>
                        </template>

                        @if($errors->createInvestmentRequest->has('entity_id'))
                            <p class="text-sm text-red-600 mt-2">{{ $errors->createInvestmentRequest->first('entity_id') }}</p>
                        @endif
                    </div>

                    {{-- Concepto --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Motivo de inversión o de gasto <span class="text-red-500">*</span>
                        </label>
                        <textarea name="concept"
                                  rows="3"
                                  required
                                  class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">{{ old('concept') }}</textarea>
                        @if($errors->createInvestmentRequest->has('concept'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('concept') }}</p>
                        @endif
                    </div>

                    {{-- Monto: se muestra como dinero sin centavos ("1,250,000"); el servidor quita las comas --}}
                    <div x-data="{
                            amount: @js(is_numeric(old('amount')) ? number_format((float) old('amount')) : ''),
                            format() { const digits = this.amount.replace(/\D/g, ''); this.amount = digits ? Number(digits).toLocaleString('en-US') : ''; }
                         }">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Monto solicitado <span class="text-red-500">*</span>
                        </label>
                        <div class="relative">
                            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-sm text-gray-500">$</span>
                            <input type="text"
                                   name="amount"
                                   inputmode="numeric"
                                   x-model="amount"
                                   @input="format()"
                                   placeholder="0"
                                   required
                                   class="w-full rounded-md border-gray-300 pl-7 text-sm focus:border-blue-600 focus:ring-blue-600">
                        </div>
                        @if($errors->createInvestmentRequest->has('amount'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('amount') }}</p>
                        @endif
                    </div>

                    {{-- Fecha límite --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Fecha límite
                        </label>
                        <input type="date"
                               name="deadline_at"
                               value="{{ old('deadline_at') }}"
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                        @if($errors->createInvestmentRequest->has('deadline_at'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('deadline_at') }}</p>
                        @endif
                    </div>

                    {{-- Solicitante --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Solicitante
                        </label>
                        <select name="requested_by" x-ref="requester">
                            <option value="">— Seleccionar persona —</option>
                            @foreach($groupUsers as $u)
                                <option value="{{ $u->id }}" @selected((int) old('requested_by') === $u->id)>
                                    {{ $u->name }}{{ $u->role ? ' — ' . $u->role->name : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    {{-- Anexo 8 --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Anexo 8 (justificante del gasto) <span class="text-red-500">*</span>
                        </label>
                        <input type="file"
                               name="file"
                               required
                               accept=".pdf,.jpg,.jpeg,.png,.xls,.xlsx"
                               class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                        @if($errors->createInvestmentRequest->has('file'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('file') }}</p>
                        @endif
                        <div class="text-xs text-gray-500 mt-1">
                            PDF, JPG, PNG o Excel. Máximo 10 MB. El nombre del archivo debe incluir "Anexo 8".
                        </div>
                    </div>

                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button"
                            x-on:click="$dispatch('close')"
                            class="px-4 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                        Cancelar
                    </button>
                    <button type="submit"
                            :disabled="!entityId || (flow && flow.problem)"
                            class="px-4 py-2 rounded-md bg-brand text-white text-sm font-semibold hover:bg-brand-dark disabled:opacity-50 disabled:cursor-not-allowed">
                        Guardar
                    </button>
                </div>
            </form>
        </x-modal>
    @endif

</x-layouts.vigia>
