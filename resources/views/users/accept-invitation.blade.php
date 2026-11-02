<x-layouts.auth title="Activar cuenta" heading="Activar cuenta" subtitle="Crea tu contraseña para entrar por primera vez.">
    <div class="mb-6 space-y-1 rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-600">
        <div><span class="font-medium text-gray-700">Nombre:</span> {{ $user->name }}</div>
        <div><span class="font-medium text-gray-700">Correo:</span> {{ $user->email }}</div>
        <div><span class="font-medium text-gray-700">Empresa:</span> {{ $user->company->name ?? '—' }}</div>
        <div><span class="font-medium text-gray-700">Rol:</span> {{ $user->role->name ?? '—' }}</div>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-lg border border-red-300 bg-red-50 px-4 py-3">
            <ul class="list-inside list-disc text-sm text-red-700">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="POST" action="{{ route('invitation.store', $user->invite_token) }}" class="space-y-5">
        @csrf

        <div>
            <label for="password" class="mb-1.5 block text-sm font-medium text-gray-700">Contraseña</label>
            <input id="password" type="password" name="password" required autofocus autocomplete="new-password"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
        </div>

        <div>
            <label for="password_confirmation" class="mb-1.5 block text-sm font-medium text-gray-700">Confirmar contraseña</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password"
                   class="h-12 w-full rounded-xl border-gray-300 px-4 focus:border-brand focus:ring-brand">
        </div>

        <button type="submit" class="h-12 w-full rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Activar cuenta
        </button>
    </form>
</x-layouts.auth>
