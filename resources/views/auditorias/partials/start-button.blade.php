{{-- Botón "Iniciar auditoría" (solo el auditor asignado, el día de la auditoría). Un solo envío. --}}
@php $big = ($size ?? 'sm') === 'lg'; @endphp
<form method="POST" action="{{ route('auditorias.audits.start', $audit) }}" x-data="{ enviando: false }"
      @submit="if (enviando) { $event.preventDefault() } else { enviando = true }">
    @csrf
    <button type="submit" :disabled="enviando"
            class="whitespace-nowrap rounded-lg bg-brand font-semibold text-white hover:bg-brand-dark disabled:cursor-wait disabled:opacity-60 {{ $big ? 'px-5 py-2.5 text-sm' : 'px-3 py-1.5 text-sm' }}">
        <span x-show="! enviando">Iniciar auditoría</span>
        <span x-show="enviando" x-cloak>Iniciando…</span>
    </button>
</form>
