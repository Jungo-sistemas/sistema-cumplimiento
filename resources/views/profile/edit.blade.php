<x-layouts.vigia :title="'Mi perfil'" :minimal="true">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Mi perfil</span>
    </x-slot>

    <h1 class="text-2xl font-semibold text-[#1A428A]">Mi perfil</h1>

    <div class="mt-6 space-y-6 max-w-xl">
        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            @include('profile.partials.update-profile-information-form')
        </div>

        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            @include('profile.partials.update-password-form')
        </div>

        <div class="p-4 sm:p-8 bg-white shadow sm:rounded-lg">
            @include('profile.partials.delete-user-form')
        </div>
    </div>
</x-layouts.vigia>
