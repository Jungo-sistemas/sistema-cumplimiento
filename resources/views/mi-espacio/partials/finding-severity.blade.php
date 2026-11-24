{{-- Etiqueta de severidad de un hallazgo. $finding --}}
@if($finding->severityLabel())
    <span class="rounded px-2 py-0.5 text-xs font-semibold {{ $finding->isCritical() ? 'bg-red-50 text-red-700' : 'bg-gray-100 text-gray-700' }}">{{ $finding->severityLabel() }}</span>
@endif
