<x-layouts.vigia title="Solicitud de Inversiones">

    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Solicitud de Inversiones</span>
    </x-slot>

    @php $user = auth()->user(); @endphp

    {{-- HEADER --}}
    <div class="flex items-center justify-between">
        <h1 class="text-2xl font-semibold text-[#1A428A]">Solicitud de Inversiones</h1>
        <div class="flex items-center gap-3">
            @if($user->isAdmin() || $user->isOperative())
                <button type="button"
                        x-data
                        @click="$dispatch('open-modal', 'create-investment-request')"
                        class="px-4 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                    + Nueva solicitud
                </button>
            @endif
        </div>
    </div>

    {{-- FILTROS --}}
    @if(($user->hasGroupScope() || $user->hasMultipleCompanies()))
        <form method="GET" action="{{ route('investment-requests.index') }}"
              class="mt-4 flex flex-wrap items-end gap-3">
            <div class="min-w-[180px]">
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

            <button type="submit"
                    class="px-5 py-2 rounded-md bg-[#1A428A] text-white text-sm font-semibold hover:bg-[#15356d]">
                Filtrar
            </button>

            <a href="{{ route('investment-requests.index') }}"
               class="px-5 py-2 rounded-md border border-gray-300 bg-white text-sm text-gray-700 font-semibold hover:bg-gray-50">
                Limpiar
            </a>
        </form>
    @endif

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
                        @if(($user->hasGroupScope() || $user->hasMultipleCompanies()))
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
                            @if(($user->hasGroupScope() || $user->hasMultipleCompanies()))
                                <td class="px-4 py-3">
                                    {{ $investmentRequest->company?->name ?? '—' }}
                                </td>
                            @endif

                            {{-- Monto --}}
                            <td class="px-4 py-3 whitespace-nowrap">
                                ${{ number_format($investmentRequest->amount, 2) }}
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
                                <span class="inline-flex items-center rounded-full bg-yellow-100 px-2.5 py-0.5 text-xs font-medium text-yellow-700">
                                    {{ $investmentRequest->status->label() }}
                                </span>
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
                            <td colspan="{{ ($user->hasGroupScope() || $user->hasMultipleCompanies()) ? 8 : 7 }}"
                                class="px-6 py-6 text-center text-gray-500">
                                No hay solicitudes de inversión registradas.
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
                  x-data
                  x-init="$nextTick(() => initPersonPicker($refs.requester, { multiple: false }))"
                  class="p-6">
                @csrf

                <h2 class="text-lg font-semibold text-[#1A428A] mb-4">Nueva solicitud de inversión</h2>

                <div class="space-y-4">

                    {{-- Empresa --}}
                    @if(($user->hasGroupScope() || $user->hasMultipleCompanies()) && $companies->isNotEmpty())
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                Empresa <span class="text-red-500">*</span>
                            </label>
                            <select name="company_id" required
                                    class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
                                <option value="">— Seleccionar empresa —</option>
                                @foreach($companies as $company)
                                    <option value="{{ $company->id }}"
                                        @selected(old('company_id', $selectedCompanyId) == $company->id)>
                                        {{ $company->name }}
                                    </option>
                                @endforeach
                            </select>
                            @if($errors->createInvestmentRequest->has('company_id'))
                                <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('company_id') }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Concepto --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Concepto / Motivo del gasto <span class="text-red-500">*</span>
                        </label>
                        <textarea name="concept"
                                  rows="3"
                                  required
                                  class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">{{ old('concept') }}</textarea>
                        @if($errors->createInvestmentRequest->has('concept'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('concept') }}</p>
                        @endif
                    </div>

                    {{-- Monto --}}
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Monto solicitado <span class="text-red-500">*</span>
                        </label>
                        <input type="number"
                               name="amount"
                               step="0.01"
                               min="0"
                               value="{{ old('amount') }}"
                               required
                               class="w-full rounded-md border-gray-300 text-sm focus:border-blue-600 focus:ring-blue-600">
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
                                    {{ $u->name }}
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
                               class="block w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600 text-sm">
                        @if($errors->createInvestmentRequest->has('file'))
                            <p class="text-sm text-red-600 mt-1">{{ $errors->createInvestmentRequest->first('file') }}</p>
                        @endif
                        <div class="text-xs text-gray-500 mt-1">PDF, JPG o PNG. Máximo 10 MB.</div>
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
