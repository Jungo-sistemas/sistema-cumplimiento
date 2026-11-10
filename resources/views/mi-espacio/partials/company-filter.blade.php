{{-- Empresa del filtro: etiqueta fija si el usuario tiene una sola, lista si tiene varias.
     Va dentro del <form> GET de la página. Variables: $companies, $selectedCompanyId. --}}
@if($companies->count() > 1)
    <select name="company_id" onchange="this.form.submit()"
            class="h-14 shrink-0 rounded-xl border-gray-200 bg-brand-50 pr-9 text-sm font-bold text-brand focus:border-brand focus:ring-brand">
        <option value="">Todas mis empresas</option>
        @foreach($companies as $company)
            <option value="{{ $company->id }}" @selected($selectedCompanyId === $company->id)>{{ $company->procesos_name }}</option>
        @endforeach
    </select>
@elseif($companies->count() === 1)
    <span class="inline-flex shrink-0 items-center rounded-full bg-brand-50 px-3.5 py-1.5 text-sm font-bold text-brand">{{ $companies->first()->procesos_name }}</span>
@endif
