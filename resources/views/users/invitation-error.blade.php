<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Invitación no disponible - VIGIA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#123B82] flex items-center justify-center">

    <div class="w-full max-w-5xl flex flex-col items-center">

        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-10">

            <div class="flex flex-col items-center mb-8">
                <img src="{{ asset('images/vigia-logo.svg') }}" alt="VIGIA" class="h-12 mb-4">
                <div class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100 mb-3">
                    <svg class="h-6 w-6 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
                    </svg>
                </div>
                <h1 class="text-xl font-semibold text-gray-800 text-center">Invitación no disponible</h1>
            </div>

            <p class="text-sm text-gray-600 text-center mb-8">{{ $message }}</p>

            @if($showLoginLink ?? false)
                <a href="{{ route('login') }}"
                   class="block w-full py-3 bg-[#1F3F82] text-white rounded-md font-semibold text-center hover:bg-[#173066] transition">
                    Ir a iniciar sesión
                </a>
            @endif
        </div>

        <div class="mt-8 text-center text-xs text-white/80">
            © {{ date('Y') }} Grupo Vigia. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
