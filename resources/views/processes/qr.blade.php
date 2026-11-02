<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('partials.brand-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>QR — {{ $regulation->code }} — {{ $regulation->name }}</title>
    @vite(['resources/css/app.css'])
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
        }
        body { background: #f3f4f6; }
    </style>
</head>
<body class="text-gray-900">

    {{-- Toolbar --}}
    <div class="no-print sticky top-0 z-10 bg-[#002060] text-white px-6 py-3 flex items-center justify-between shadow">
        <a href="{{ route('processes.show', $regulation) }}" class="text-white/80 hover:text-white text-sm">
            Volver
        </a>
        <button onclick="window.print()"
                class="px-4 py-1.5 rounded bg-white text-[#002060] text-sm font-semibold hover:bg-brand-50">
            Imprimir
        </button>
    </div>

    <div class="min-h-screen flex items-center justify-center p-10">
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-10 max-w-md w-full text-center space-y-6">
            <div>
                <p class="text-xs font-semibold tracking-wide text-gray-400 uppercase">
                    @if($regulation->code) {{ $regulation->code }} @endif
                </p>
                <h1 class="text-lg font-bold text-brand mt-1">
                    {{ $regulation->name }}
                </h1>
            </div>

            <div class="flex justify-center">
                {!! QrCode::size(320)->margin(1)->generate($url) !!}
            </div>

            <p class="text-sm text-gray-500">
                Escanea para ver siempre la versión vigente de este documento.
            </p>

            <p class="no-print text-xs text-gray-400 break-all">
                {{ $url }}
            </p>
        </div>
    </div>

</body>
</html>
