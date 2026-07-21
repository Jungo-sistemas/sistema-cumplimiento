@php
    $isActive = $license && $license->status === 'active' && $license->expires_at->isFuture();
    $isExpired = $license && !$isActive;
@endphp

<div x-data="{ editing: false, includesProcesos: {{ $license?->includes_procesos ? 'true' : 'false' }} }">
    <div class="space-y-1">
        @if($isActive)
            @php $daysLeft = now()->startOfDay()->diffInDays($license->expires_at->copy()->startOfDay(), false); @endphp
            <div class="flex items-center gap-2">
                <span class="text-xs px-2 py-0.5 rounded-full font-medium {{ $daysLeft <= 7 ? 'bg-orange-100 text-orange-700' : 'bg-green-100 text-green-700' }}">
                    Activa hasta {{ $license->expires_at->format('d/m/Y') }}
                </span>
                <span class="text-xs text-gray-500">({{ $daysLeft }}d)</span>
            </div>
            <p class="text-xs text-gray-500">
                Procesos: <strong>{{ $license->includes_procesos ? 'Sí' : 'No' }}</strong>
                · ${{ number_format((float) $license->price, 2) }}/mes
            </p>
        @elseif($isExpired)
            <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-red-100 text-red-700">
                Vencida el {{ $license->expires_at->format('d/m/Y') }}
            </span>
        @else
            <span class="text-xs px-2 py-0.5 rounded-full font-medium bg-gray-100 text-gray-500">
                Sin licencia
            </span>
        @endif

        <button type="button" @click="editing = !editing" class="block text-xs text-[#1A428A] hover:underline">
            {{ $isActive ? 'Renovar' : 'Activar' }} licencia
        </button>
    </div>

    <form x-show="editing" method="POST" action="{{ $activateRoute }}" class="mt-2 space-y-2" style="display:none">
        @csrf
        <label class="flex items-center gap-2 text-xs text-gray-700">
            <input type="checkbox" name="includes_procesos" value="1" x-model="includesProcesos"
                   class="h-3.5 w-3.5 rounded border-gray-300 text-[#1A428A] focus:ring-[#1A428A]">
            Incluye módulo de Procesos (+${{ number_format(\App\Services\LicenseService::PROCESOS_ADDON_PRICE, 0) }}/mes)
        </label>
        <div class="flex gap-2">
            <button type="submit" class="text-xs px-3 py-1.5 rounded bg-[#1A428A] text-white hover:bg-[#15356d]">
                Activar por 1 mes
            </button>
            <button type="button" @click="editing = false" class="text-xs text-gray-500 hover:underline">Cancelar</button>
        </div>
    </form>
</div>
