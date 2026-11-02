{{-- Marco de las pantallas de acceso (inicio de sesión, doble verificación, contraseña).
     Uso: <x-layouts.auth title="..." heading="..." subtitle="..."> formulario <x-slot:footer>...</x-slot> --}}
@props(['title', 'heading', 'subtitle' => null])

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('partials.brand-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} · Jungo Cumple</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-brand-ink bg-[radial-gradient(ellipse_at_top,_rgba(124,58,237,0.35),_transparent_60%)] text-gray-900">
    <div class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
        <x-brand-logo class="mb-8 h-11" />

        <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-2xl sm:p-10">
            <div class="mb-8 text-center">
                <h1 class="text-[28px] font-extrabold leading-tight tracking-tight text-brand-ink">{{ $heading }}</h1>
                @if($subtitle)
                    <p class="mt-2 text-[15px] text-gray-600">{{ $subtitle }}</p>
                @endif
            </div>

            @if (session('status'))
                <div class="mb-5 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            {{ $slot }}
        </div>

        <div class="mt-8 text-center text-xs text-white/70">
            @isset($footer)
                <div class="mb-2">{{ $footer }}</div>
            @endisset
            <div>© {{ date('Y') }} Grupo Vigia. Todos los derechos reservados.</div>
        </div>
    </div>
</body>
</html>
