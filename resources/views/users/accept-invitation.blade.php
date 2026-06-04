<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Activar cuenta - VIGIA</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#123B82] flex items-center justify-center">

    <div class="w-full max-w-5xl flex flex-col items-center">

        <div class="w-full max-w-md bg-white rounded-2xl shadow-xl p-10">

            <div class="flex flex-col items-center mb-8">
                <img src="{{ asset('images/vigia-logo.svg') }}" alt="VIGIA" class="h-12 mb-4">
                <h1 class="text-2xl font-semibold text-gray-800">Activar cuenta</h1>
            </div>

            <div class="mb-6 space-y-1 text-sm text-gray-600 bg-gray-50 rounded-lg px-4 py-3 border border-gray-200">
                <div><span class="font-medium text-gray-700">Nombre:</span> {{ $user->name }}</div>
                <div><span class="font-medium text-gray-700">Correo:</span> {{ $user->email }}</div>
                <div><span class="font-medium text-gray-700">Empresa:</span> {{ $user->company->name ?? '—' }}</div>
                <div><span class="font-medium text-gray-700">Rol:</span> {{ $user->role->name ?? '—' }}</div>
            </div>

            @if ($errors->any())
                <div class="mb-4 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
                    <ul class="list-disc list-inside text-sm text-red-700">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('invitation.store', $user->invite_token) }}" class="space-y-6">
                @csrf

                <div>
                    <label class="block text-sm text-gray-700 mb-1">Contraseña</label>
                    <input
                        type="password"
                        name="password"
                        required
                        autofocus
                        class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                    >
                </div>

                <div>
                    <label class="block text-sm text-gray-700 mb-1">Confirmar contraseña</label>
                    <input
                        type="password"
                        name="password_confirmation"
                        required
                        class="w-full rounded-md border-gray-300 focus:border-blue-600 focus:ring-blue-600"
                    >
                </div>

                <button
                    type="submit"
                    class="w-full py-3 bg-[#1F3F82] text-white rounded-md font-semibold hover:bg-[#173066] transition"
                >
                    Activar cuenta
                </button>
            </form>
        </div>

        <div class="mt-8 text-center text-xs text-white/80">
            © {{ date('Y') }} Grupo Vigia. Todos los derechos reservados.
        </div>

    </div>

</body>
</html>
