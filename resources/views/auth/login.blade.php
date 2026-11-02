<x-layouts.auth title="Iniciar sesión" heading="Iniciar sesión" subtitle="Ingresa con tu correo y contraseña.">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Correo</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <div class="mb-1.5 flex items-center justify-between">
                <label for="password" class="block text-sm font-medium text-gray-700">Contraseña</label>
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-brand hover:underline">¿Olvidaste tu contraseña?</a>
            </div>
            <input id="password" type="password" name="password" required autocomplete="current-password"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="h-12 w-full rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Iniciar sesión
        </button>
    </form>

    <x-slot:footer>
        <a href="#" class="underline hover:text-white">Política de Privacidad</a>
    </x-slot:footer>
</x-layouts.auth>
