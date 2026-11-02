<x-layouts.auth title="Restablecer contraseña" heading="Restablecer contraseña">
    <form method="POST" action="{{ route('password.store') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label for="email" class="mb-1.5 block text-sm font-medium text-gray-700">Correo electrónico</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus autocomplete="username"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('email')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Nueva contraseña</label>
            <input id="password" type="password" name="password" required autocomplete="new-password"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('password')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
            @error('password_confirmation')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="h-12 w-full rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Restablecer contraseña
        </button>
    </form>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="underline hover:text-white">Volver al inicio de sesión</a>
    </x-slot:footer>
</x-layouts.auth>
