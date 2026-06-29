<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Iniciar Sesión - VIGIA</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#123B82] flex items-center justify-center">

    <div class="w-full max-w-5xl flex flex-col items-center">

        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-10">

            <div class="flex flex-col items-center mb-8">
                <img src="{{ asset('images/vigia-logo.svg') }}" alt="VIGIA" class="h-12 mb-4">
                <h1 class="text-2xl font-semibold text-gray-800">
                    Iniciar Sesión
                </h1>
            </div>

            @if (session('status'))
                <div class="mb-4 text-sm px-3 py-2 rounded border bg-green-50 text-green-800">
                    {{ session('status') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm text-gray-700 mb-1">Correo</label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        autofocus
                        class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                    >
                    @error('email')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-sm text-gray-700">Contraseña</label>
                        <a href="{{ route('password.request') }}"
                           class="text-xs text-[#1F3F82] hover:underline">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>
                    <input
                        type="password"
                        name="password"
                        required
                        class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                    >
                    @error('password')
                        <p class="text-sm text-red-600 mt-1">{{ $message }}</p>
                    @enderror
                </div>

                <button
                    type="submit"
                    class="w-full py-3 bg-[#1F3F82] text-white rounded-md font-semibold hover:bg-[#173066] transition"
                >
                    Iniciar Sesión
                </button>
            </form>
        </div>

        <div class="mt-8 text-center text-xs text-white/80">
            <a href="#" class="underline">Política de Privacidad</a>
            <div class="mt-2">© {{ date('Y') }} Grupo Vigia. Todos los derechos reservados.</div>
        </div>

    </div>

</body>
</html>