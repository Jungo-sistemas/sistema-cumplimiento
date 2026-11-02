<x-layouts.auth title="Verificación" heading="Verificación en dos pasos"
                subtitle="Enviamos un código de 6 dígitos a tu correo electrónico. Ingrésalo para continuar.">
    <form method="POST" action="{{ route('two-factor.store') }}" class="space-y-5">
        @csrf

        <div>
            <label for="code" class="mb-1.5 block text-center text-sm font-medium text-gray-700">Código de verificación</label>
            <input id="code" type="text" name="code" inputmode="numeric" maxlength="6" autocomplete="one-time-code" required autofocus
                   placeholder="——————"
                   class="w-full rounded-xl border-gray-300 bg-brand-50/40 py-3 text-center text-3xl font-bold tracking-[0.5em] text-brand-ink focus:border-brand focus:ring-brand">
            @error('code')
                <p class="mt-1 text-center text-sm text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit" class="h-12 w-full rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Verificar
        </button>
    </form>

    <div class="mt-6 text-center">
        <p class="text-sm text-gray-500">¿No recibiste el código?</p>
        <form method="POST" action="{{ route('two-factor.resend') }}" class="inline">
            @csrf
            <button type="submit" class="mt-1 text-sm font-semibold text-brand hover:underline">Reenviar código</button>
        </form>
    </div>

    <x-slot:footer>
        <a href="{{ route('login') }}" class="underline hover:text-white">Volver al inicio de sesión</a>
    </x-slot:footer>
</x-layouts.auth>
