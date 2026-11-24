<x-layouts.vigia :title="$section['label']">
    <div class="mx-auto max-w-2xl rounded-2xl border border-gray-200 bg-white px-6 py-14 text-center shadow-sm">
        <div class="mx-auto mb-5 flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
            <x-icon :name="$section['icon']" class="h-7 w-7" />
        </div>
        <p class="text-xs font-semibold uppercase tracking-wider text-gray-400">{{ $moduleLabel }}</p>
        <h1 class="mt-1 text-2xl font-bold text-gray-900">{{ $section['label'] }}</h1>
        <p class="mt-3 text-gray-600">Esta sección está en construcción. Muy pronto podrás usarla desde aquí.</p>
    </div>
</x-layouts.vigia>
