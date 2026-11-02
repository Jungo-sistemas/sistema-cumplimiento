<x-layouts.auth title="Invitación no disponible" heading="Invitación no disponible" :subtitle="$message">
    <div class="-mt-2 mb-6 flex justify-center">
        <span class="flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
            <x-icon name="alert" class="h-6 w-6 text-red-500" />
        </span>
    </div>

    @if($showLoginLink ?? false)
        <a href="{{ route('login') }}"
           class="flex h-12 w-full items-center justify-center rounded-xl bg-brand font-semibold text-white transition hover:bg-brand-dark">
            Ir a iniciar sesión
        </a>
    @endif
</x-layouts.auth>
