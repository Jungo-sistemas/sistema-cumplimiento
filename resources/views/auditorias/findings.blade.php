@use('App\Http\Controllers\AuditorController')
<x-layouts.vigia title="Hallazgos · Auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="text-gray-700 font-medium">Hallazgos</span>
    </x-slot>

    @php
        $severidades = AuditorController::SEVERITY_COLORS;
        $seguimientos = AuditorController::TRACKING_COLORS;
    @endphp

    @include('mi-espacio.partials.header', [
        'title'    => 'Hallazgos',
        'subtitle' => 'Causas raíz, acciones y evidencias de cada hallazgo en un solo seguimiento.',
    ])

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    <div class="mb-7 flex flex-wrap gap-6 border-b border-gray-200">
        @foreach(AuditorController::FINDING_TABS as $key => $label)
            <a href="{{ route('auditorias.findings.index', ['tab' => $key] + $filters) }}"
                class="-mb-px border-b-2 pb-3 text-base font-semibold {{ $tab === $key ? 'border-brand text-brand' : 'border-transparent text-gray-500 hover:text-gray-700' }}">{{ $label }}</a>
        @endforeach
    </div>

    @include('auditorias.partials.filters', [
        'action'      => route('auditorias.findings.index'),
        'placeholder' => 'Buscar por código, título o responsable...',
        // En las pestañas de revisión el seguimiento ya está dado.
        'selects'     => $tab === 'todos'
            ? [['name' => 'seguimiento', 'label' => 'Seguimiento', 'all' => 'Todo el seguimiento', 'options' => AuditorController::TRACKING_FILTERS]]
            : [],
    ])

    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-8">
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-5">Hallazgo</th>
                        <th class="px-4 py-5">Responsable</th>
                        <th class="px-4 py-5">Clasificación</th>
                        <th class="px-4 py-5">Seguimiento</th>
                        <th class="px-4 py-5">Plazo de la etapa</th>
                        <th class="px-4 py-5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($rows as $finding)
                        @php $h = $payloads[$finding->id]; @endphp
                        <tr>
                            <td class="px-4 py-5">
                                <div class="font-semibold text-gray-900">{{ $h['titulo'] }}</div>
                                <div class="mt-1 text-sm text-gray-500">{{ $h['empresa'] }} · {{ $h['codigo'] }}</div>
                            </td>
                            <td class="px-4 py-5 text-gray-700">{{ $h['responsable'] }}</td>
                            <td class="px-4 py-5">
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $severidades[$h['severidad']] ?? 'bg-gray-100 text-gray-700' }}">{{ $h['severidad'] }}</span>
                            </td>
                            <td class="px-4 py-5">
                                <span class="whitespace-nowrap rounded-full px-3 py-1 text-xs font-semibold {{ $seguimientos[$h['seguimiento']] ?? 'bg-gray-100 text-gray-700' }}">{{ $h['seguimiento'] }}</span>
                            </td>
                            <td class="px-4 py-5 whitespace-nowrap">
                                @if($h['plazo'])
                                    <div class="{{ $h['vencido'] ? 'font-semibold text-red-700' : 'text-gray-700' }}">{{ $h['plazo'] }}</div>
                                    @if($h['vencido'])
                                        <span class="mt-1 inline-flex rounded-full bg-red-50 px-2 py-0.5 text-xs font-semibold text-red-700">Vencido</span>
                                    @endif
                                @else
                                    <span class="text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-5 text-right">
                                <button type="button" @click="$dispatch('abrir-hallazgo', @js($h))" class="whitespace-nowrap inline-flex rounded-lg border-2 border-brand px-3 py-1.5 text-sm font-semibold text-brand hover:bg-brand-50">{{ ['causa' => 'Revisar causa raíz', 'evidencia' => 'Revisar evidencia'][$h['revision']] ?? 'Abrir' }}</button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-6 text-center text-gray-500">{{ $filters ? 'Ningún hallazgo coincide con los filtros.' : 'No hay hallazgos en esta pestaña.' }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @include('auditorias.partials.finding-detail-modal')
</x-layouts.vigia>
