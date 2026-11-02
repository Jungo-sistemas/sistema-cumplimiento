<x-layouts.auth title="Olvidé mi contraseña" heading="Olvidé mi contraseña"
                subtitle="Ingresa tu correo y te enviaremos un enlace para restablecerla.">
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="h-12 w-full rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Enviar enlace de restablecimiento
        </button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="underline hover:text-white">Volver al inicio de sesión</a>
    </x-slot:footer>
</x-layouts.auth>
