{{--
    Vista previa de la síntesis de cambios y sus evidencias de apoyo, con pestañas para pasar de un
    archivo a otro. Si no hay síntesis, muestra solo las evidencias.

    Parámetros: $regulation, $loadWhen (expresión Alpine; el iframe/imagen se carga solo cuando es
    verdadera, para no descargar todo al abrir la página).
--}}
@php
    $viewerItems = [];
    if ($regulation->change_summary_path) {
        $viewerItems[] = [
            'label' => 'Síntesis',
            'name'  => $regulation->change_summary_original_name,
            'meta'  => 'Subido por ' . ($regulation->changeSummaryUploader?->name ?? '—') . ' · ' . $regulation->change_summary_uploaded_at?->format('d/m/Y H:i'),
            'url'   => route('processes.changeSummary.show', $regulation),
            'image' => false,
        ];
    }
    foreach ($regulation->changeEvidences as $i => $evidence) {
        $viewerItems[] = [
            'label' => 'Evidencia ' . ($i + 1),
            'name'  => $evidence->original_name,
            'meta'  => 'Subido por ' . ($evidence->uploader?->name ?? '—') . ' · ' . $evidence->created_at?->format('d/m/Y H:i'),
            'url'   => route('processes.changeEvidence.show', [$regulation, $evidence]),
            'image' => $evidence->isImage(),
        ];
    }
@endphp

@if(count($viewerItems))
    <div x-data="{ tab: 0 }">
        {{-- Pestañas también con una sola evidencia, para que se lea como evidencia y no como síntesis --}}
        @if(count($viewerItems) > 1 || ! $regulation->change_summary_path)
            <div class="flex flex-wrap gap-1.5 mb-3" role="tablist">
                @foreach($viewerItems as $i => $viewerItem)
                    <button type="button" role="tab" @click="tab = {{ $i }}"
                            :aria-selected="tab === {{ $i }}"
                            :class="tab === {{ $i }} ? 'bg-brand text-white border-brand' : 'bg-white text-gray-600 border-gray-300 hover:bg-gray-50'"
                            class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition">
                        {{ $viewerItem['label'] }}
                    </button>
                @endforeach
            </div>
        @endif

        @foreach($viewerItems as $i => $viewerItem)
            <div x-show="tab === {{ $i }}" @if($i > 0) style="display:none;" @endif>
                <div class="flex items-center justify-between gap-3 flex-wrap mb-2">
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-800 truncate">{{ $viewerItem['name'] }}</p>
                        <p class="text-xs text-gray-500">{{ $viewerItem['meta'] }}</p>
                    </div>
                    <a href="{{ $viewerItem['url'] }}" target="_blank"
                       class="text-xs font-semibold text-brand hover:underline shrink-0">
                        Abrir en pestaña nueva
                    </a>
                </div>
                <template x-if="({{ $loadWhen ?? 'true' }}) && tab === {{ $i }}">
                    @if($viewerItem['image'])
                        <div class="rounded-lg border bg-gray-100 p-2 flex justify-center">
                            <img src="{{ $viewerItem['url'] }}" alt="{{ $viewerItem['name'] }}"
                                 class="max-h-[600px] w-auto object-contain">
                        </div>
                    @else
                        <iframe src="{{ $viewerItem['url'] }}" title="{{ $viewerItem['label'] }}"
                                class="w-full h-[600px] rounded-lg border bg-gray-100"></iframe>
                    @endif
                </template>
            </div>
        @endforeach
    </div>
@endif
