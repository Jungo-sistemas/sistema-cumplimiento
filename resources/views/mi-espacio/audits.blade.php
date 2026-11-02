<x-layouts.vigia title="Mis auditorías">
    <x-slot name="breadcrumb">
        <span>Auditorías</span>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">Mis auditorías</span>
    </x-slot>

    @include('mi-espacio.partials.header', [
        'title'    => 'Mis auditorías',
        'subtitle' => 'Completa tus auditorías activas y consulta tus resultados.',
    ])

    @include('mi-espacio.partials.tabs', [
        'tabs'  => ['activas' => 'Activas', 'historial' => 'Historial'],
        'route' => 'mi-espacio.audits',
    ])

    {{-- Filtros listos para cuando existan las auditorías (AUD-1); por ahora la lista viene vacía. --}}
    <form method="GET" action="{{ route('mi-espacio.audits') }}" class="mb-7 flex flex-col gap-3 sm:flex-row sm:items-center">
        <input type="hidden" name="tab" value="{{ $tab }}">
        @include('mi-espacio.partials.company-filter', ['selectedCompanyId' => request()->integer('company_id') ?: null])
        <input type="search" name="q" value="{{ request('q') }}" placeholder="Buscar por código o nombre..."
               class="h-14 w-full rounded-xl border-gray-200 px-4 text-[15px] focus:border-brand focus:ring-brand">
        @if($tab === 'activas')
            <select name="estado" onchange="this.form.submit()"
                    class="h-14 shrink-0 rounded-xl border-gray-200 pr-10 text-[15px] focus:border-brand focus:ring-brand sm:w-72">
                <option value="">Todos</option>
                @foreach(['programada' => 'Programada', 'en_captura' => 'En captura', 'correcciones' => 'Con correcciones'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('estado') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </form>

    <div class="rounded-2xl border border-gray-200 bg-white p-4 sm:p-8">
        <div class="overflow-x-auto rounded-xl border border-gray-200">
            <table class="w-full text-left">
                <thead class="bg-gray-50 text-xs font-bold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-4 py-5">Proceso</th>
                        <th class="px-4 py-5">Estado</th>
                        <th class="px-4 py-5">{{ $tab === 'historial' ? 'Calificación' : 'Siguiente paso' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($audits as $audit)
                    @empty
                        <tr>
                            <td colspan="3" class="px-4 py-12 text-center text-[15px] text-gray-600">No hay auditorías en esta vista.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.vigia>
