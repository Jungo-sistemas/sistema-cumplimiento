{{-- Barra de filtros de Auditorías y Hallazgos: buscador a lo ancho y, a la derecha, cada lista con su etiqueta.
     Recibe: $action, $tab, $filters, $placeholder, $companies (la lista de Empresa solo sale si hay más de una
     o si ya está filtrada) y $selects: [['name' => 'estado', 'label' => 'Estado', 'all' => 'Todos los estados',
     'options' => [valor => texto]]]. Las listas se envían solas al cambiar y la búsqueda con Enter. --}}
@php
    $showCompanies = $companies->count() > 1 || filled($filters['empresa'] ?? null);
@endphp
<form method="GET" action="{{ $action }}" class="mb-7 rounded-2xl border border-gray-200 bg-white p-4">
    <input type="hidden" name="tab" value="{{ $tab }}">

    <div class="flex flex-col gap-3 lg:flex-row lg:items-end">
        <label class="block min-w-0 flex-1">
            <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Buscar</span>
            <span class="relative block">
                <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="{{ $placeholder }}"
                       class="h-11 w-full rounded-lg border-gray-200 pl-10 text-[15px] focus:border-brand focus:ring-brand">
            </span>
        </label>

        @if($showCompanies)
            <label class="block lg:w-56">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">Empresa</span>
                <select name="empresa" onchange="this.form.submit()"
                        class="h-11 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <option value="">Todas las empresas</option>
                    @foreach($companies as $company)
                        <option value="{{ $company }}" @selected(($filters['empresa'] ?? '') === $company)>{{ $company }}</option>
                    @endforeach
                </select>
            </label>
        @endif

        @foreach($selects as $select)
            <label class="block lg:w-60">
                <span class="mb-1.5 block text-xs font-semibold uppercase tracking-wider text-gray-500">{{ $select['label'] }}</span>
                <select name="{{ $select['name'] }}" onchange="this.form.submit()"
                        class="h-11 w-full rounded-lg border-gray-200 text-[15px] focus:border-brand focus:ring-brand">
                    <option value="">{{ $select['all'] }}</option>
                    @foreach($select['options'] as $value => $text)
                        <option value="{{ $value }}" @selected(($filters[$select['name']] ?? '') === (string) $value)>{{ $text }}</option>
                    @endforeach
                </select>
            </label>
        @endforeach

        @if($filters)
            <a href="{{ $action . '?' . http_build_query(['tab' => $tab]) }}"
               class="inline-flex h-11 shrink-0 items-center justify-center rounded-lg px-3 text-sm font-semibold text-brand hover:bg-brand-50">Limpiar filtros</a>
        @endif
    </div>
</form>
