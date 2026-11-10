<x-layouts.vigia title="Inicio · Procesos y Auditorías">
    @include('mi-espacio.partials.header', [
        'title'    => 'Inicio',
        'subtitle' => 'Hola, ' . \Illuminate\Support\Str::of(auth()->user()->name)->before(' ') . '. Esto es lo que está pasando en procesos y auditorías.',
    ])

    @php
        $cards = [
            ['area' => 'Procesos', 'label' => 'Liberados', 'valor' => $released, 'texto' => "de {$total} procesos vigentes",
             'boton' => 'Ver procedimientos', 'url' => route('processes.index')],
            ['area' => 'Procesos', 'label' => 'Por aprobar', 'valor' => $inReview,
             'texto' => $myApprovals ? "{$myApprovals} te " . ($myApprovals === 1 ? 'toca' : 'tocan') . ' a ti' : 'Ninguno te toca a ti',
             'boton' => 'Mis aprobaciones', 'url' => route('my-approvals.index')],
            ['area' => 'Procesos', 'label' => 'Por vencer', 'valor' => $dueSoon, 'texto' => 'en los próximos 30 días',
             'boton' => 'Ver procedimientos', 'url' => route('processes.index')],
        ];
    @endphp

    <div @class(['grid grid-cols-1 gap-6 md:grid-cols-2', 'xl:grid-cols-4' => $showAudits, 'xl:grid-cols-3' => ! $showAudits])>
        @foreach($cards as $card)
            <div class="flex flex-col rounded-2xl border border-gray-200 bg-white p-6">
                <span class="self-start rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand">{{ $card['area'] }}</span>
                <h2 class="mt-3 text-lg font-semibold text-gray-900">{{ $card['label'] }}</h2>
                <div class="mt-2 text-5xl font-extrabold text-brand-ink">{{ $card['valor'] }}</div>
                <p class="mt-3 text-[15px] text-gray-600">{{ $card['texto'] }}</p>
                <div class="mt-auto pt-5">
                    <a href="{{ $card['url'] }}" class="inline-flex rounded-lg bg-brand-50 px-4 py-2.5 text-sm font-semibold text-brand hover:bg-brand-100">{{ $card['boton'] }}</a>
                </div>
            </div>
        @endforeach

        @if($showAudits)
            {{-- Las auditorías todavía no tienen tablas (AUD-1): no se muestran números de ejemplo. --}}
            <div class="flex flex-col rounded-2xl border border-dashed border-gray-300 bg-white p-6">
                <span class="self-start rounded-full bg-sky-50 px-2.5 py-0.5 text-xs font-semibold text-sky-700">Auditorías</span>
                <h2 class="mt-3 text-lg font-semibold text-gray-900">Auditorías activas</h2>
                <div class="mt-2 text-5xl font-extrabold text-gray-300">—</div>
                <p class="mt-3 text-[15px] text-gray-600">Aquí se verán las auditorías en curso y los hallazgos abiertos cuando se registren.</p>
                <div class="mt-auto pt-5">
                    <a href="{{ route('auditorias.audits.index') }}" class="inline-flex rounded-lg bg-sky-50 px-4 py-2.5 text-sm font-semibold text-sky-700 hover:bg-sky-100">Ver auditorías</a>
                </div>
            </div>
        @endif
    </div>

    <div class="mt-8 rounded-2xl border border-gray-200 bg-white">
        <h2 class="border-b border-gray-100 px-7 py-5 text-xl font-semibold text-gray-900">Requiere atención</h2>

        @forelse($attention as $item)
            <a href="{{ $item['url'] }}" class="flex flex-wrap items-center gap-x-4 gap-y-1 border-b border-gray-100 px-7 py-4 last:border-b-0 hover:bg-gray-50">
                <span class="rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-semibold text-brand">{{ $item['area'] }}</span>
                <span class="min-w-0 flex-1">
                    <span class="font-semibold text-gray-900">{{ $item['name'] }}</span>
                    @if($item['company'])
                        <span class="text-sm text-gray-500"> · {{ $item['company'] }}</span>
                    @endif
                </span>
                <span @class([
                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                    'bg-amber-50 text-amber-800' => $item['tone'] === 'warn',
                    'bg-red-50 text-red-700'     => $item['tone'] === 'bad',
                ])>{{ $item['state'] }}</span>
            </a>
        @empty
            <p class="px-7 py-5 text-[15px] text-gray-600">Todo al día: nada requiere tu atención.</p>
        @endforelse
    </div>
</x-layouts.vigia>
