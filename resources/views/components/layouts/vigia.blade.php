{{-- resources/views/components/layouts/vigia.blade.php --}}
@props([
    'title' => null,
    'navContext' => [
        'asset' => null,
        'requirement' => null,
        'task' => null,
        'documentSection' => false,
        'documentOwner' => null,
    ],
])

@php
    $user = auth()->user();
    $currentModule = request()->routeIs('processes.*', 'job-positions.*', 'my-approvals.*') ? 'procesos' : 'cumplimiento';
    $allModules = [
        'cumplimiento' => ['label' => 'Cumplimiento', 'route' => 'dashboard'],
        'procesos'     => ['label' => 'Procesos',     'route' => 'processes.dashboard'],
    ];
    $modules = collect($allModules)
        ->filter(fn ($_, $key) => $user?->canAccessModule($key) ?? true)
        ->all();
    if (!isset($modules[$currentModule])) {
        $currentModule = array_key_first($modules) ?? 'cumplimiento';
    }
    $pendingApprovalsCount = $user && !$user->isSuperAdmin()
        ? \App\Models\RegulationApproval::where('user_id', $user->id)->where('status', 'pending')->count()
        : 0;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ? $title . ' · Vigia' : 'Vigia' }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-screen overflow-hidden bg-gray-50 text-gray-900">
    <div x-data="{ mobileMenuOpen: false }" class="flex h-screen flex-col">
        <header class="shrink-0 bg-[#1A428A] text-white">
            <div class="mx-auto flex max-w-[1680px] items-center justify-between px-4 py-3 sm:px-6">
                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center justify-center rounded-md p-2 hover:bg-white/10 lg:hidden"
                        @click="mobileMenuOpen = true"
                        aria-label="Abrir menú"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                    </button>

                    <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
                        <img src="{{ asset('images/vigia.svg') }}" alt="VIGIA" class="h-8 w-auto">
                    </a>

                    @if(!$user?->isSuperAdmin())
                        <span class="hidden h-5 w-px bg-white/30 sm:block"></span>

                        <div x-data="{ open: false }" class="relative hidden sm:block">
                            <button
                                type="button"
                                @click="open = !open"
                                @keydown.escape="open = false"
                                class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-semibold text-white/90 hover:bg-white/10 focus:outline-none"
                            >
                                {{ $modules[$currentModule]['label'] }}
                                <svg :class="{ 'rotate-180': open }" class="h-3.5 w-3.5 text-white/60 transition-transform duration-150"
                                     xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                                     stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                </svg>
                            </button>

                            <div
                                x-show="open"
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                x-transition:leave="transition ease-in duration-75"
                                x-transition:leave-start="opacity-100 scale-100"
                                x-transition:leave-end="opacity-0 scale-95"
                                @click.outside="open = false"
                                class="absolute left-0 top-full z-50 mt-2 w-44 origin-top-left rounded-lg border border-gray-100 bg-white py-1 shadow-lg"
                                style="display:none;"
                            >
                                @foreach($modules as $key => $mod)
                                    <a
                                        href="{{ route($mod['route']) }}"
                                        @click="open = false"
                                        class="flex items-center gap-2 px-3 py-2 text-sm {{ $currentModule === $key ? 'font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}"
                                    >
                                        @if($currentModule === $key)
                                            <svg class="h-3.5 w-3.5 text-[#1A428A]" xmlns="http://www.w3.org/2000/svg"
                                                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                        @else
                                            <span class="h-3.5 w-3.5"></span>
                                        @endif
                                        {{ $mod['label'] }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>

                <div class="flex items-center gap-3">
                    @if(!$user?->isSuperAdmin() && $user?->isAdmin())
                        <a href="{{ route('users.index') }}"
                           title="Usuarios"
                           class="flex items-center gap-1.5 rounded-md px-2 py-1.5 text-sm text-white/80 hover:bg-white/10 {{ request()->routeIs('users.*') ? 'bg-white/15 font-semibold text-white' : '' }}">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                            </svg>
                            <span class="hidden sm:inline">Usuarios</span>
                        </a>
                    @endif

                    <div class="hidden text-sm opacity-90 sm:block">
                        {{ $user?->name }}
                    </div>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="rounded-md bg-white px-3 py-2 text-sm font-semibold text-[#1A428A] shadow-sm sm:px-4">
                            Cerrar Sesión
                        </button>
                    </form>
                </div>
            </div>
        </header>

        <div
            x-show="mobileMenuOpen"
            x-transition.opacity
            class="fixed inset-0 z-40 bg-black/40 lg:hidden"
            @click="mobileMenuOpen = false"
            style="display: none;"
        ></div>

        <aside
            x-show="mobileMenuOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="-translate-x-full"
            x-transition:enter-end="translate-x-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="translate-x-0"
            x-transition:leave-end="-translate-x-full"
            class="fixed inset-y-0 left-0 z-50 w-[290px] bg-white shadow-xl lg:hidden"
            style="display: none;"
        >
            <div class="flex h-full flex-col">
                <div class="flex items-center justify-between border-b px-4 py-4">
                    <div class="flex items-center gap-2 text-sm font-semibold text-[#1A428A]">
                        <span>☰</span>
                        <span>Menú</span>
                    </div>

                    <button
                        type="button"
                        class="rounded-md p-2 text-gray-500 hover:bg-gray-100"
                        @click="mobileMenuOpen = false"
                        aria-label="Cerrar menú"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none"
                             viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                  d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-4">
                    <nav class="space-y-1 text-sm">
                        @if($user?->isSuperAdmin())
                            <p class="mb-1 px-3 text-xs font-bold uppercase tracking-wider text-gray-400">Sistema</p>
                            <a href="{{ route('superadmin.dashboard') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Panel general</a>
                            <a href="{{ route('superadmin.groups') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.groups') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Grupos</a>
                            <a href="{{ route('superadmin.companies') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.companies') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Empresas</a>
                            <a href="{{ route('superadmin.users') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.users') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Usuarios</a>
                        @elseif($currentModule === 'procesos')
                            {{-- ── MÓDULO: PROCESOS ── --}}
                            <a href="{{ route('processes.dashboard') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('processes.dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Tablero</a>

                            <a href="{{ route('processes.index') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('processes.index', 'processes.show', 'processes.create') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Documentos</a>

                            @if($pendingApprovalsCount > 0 || request()->routeIs('my-approvals.*'))
                                <a href="{{ route('my-approvals.index') }}" @click="mobileMenuOpen = false"
                                   class="flex items-center justify-between rounded-md px-3 py-2 {{ request()->routeIs('my-approvals.*') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <span>Mis aprobaciones</span>
                                    @if($pendingApprovalsCount > 0)
                                        <span class="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-yellow-400 text-yellow-900 text-xs font-bold">{{ $pendingApprovalsCount }}</span>
                                    @endif
                                </a>
                            @endif
                        @else
                            {{-- ── MÓDULO: CUMPLIMIENTO ── --}}
                            <a href="{{ route('dashboard') }}" @click="mobileMenuOpen = false"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Tablero</a>

                            @if($user?->isAdmin() || $user?->isOperative() || $user?->isReadOnly())
                                <a href="{{ route('documents.index') }}" @click="mobileMenuOpen = false"
                                   class="block rounded-md px-3 py-2 {{ request()->routeIs('documents.*') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Documentos generales</a>
                                <a href="{{ route('processes.index') }}" @click="mobileMenuOpen = false"
                                   class="block rounded-md px-3 py-2 {{ request()->routeIs('processes.index', 'processes.show', 'processes.create') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Procesos</a>
                            @endif

                            @php $inCumplimiento = request()->routeIs('assets.*') || !empty($navContext['asset']); @endphp
                            <div x-data="{ open: {{ $inCumplimiento ? 'true' : 'false' }} }">
                                <button type="button" @click="open = !open"
                                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm {{ $inCumplimiento ? 'font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <span>Cumplimiento</span>
                                    <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform duration-200"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" class="mt-1 space-y-1 pl-2">
                                    <a href="{{ route('assets.index') }}" @click="mobileMenuOpen = false"
                                       class="block rounded-md px-3 py-2 {{ $inCumplimiento ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Energético</a>

                                    @if(!empty($navContext['asset']))
                                        <a href="{{ route('assets.show', $navContext['asset']) }}" @click="mobileMenuOpen = false"
                                           class="ml-4 block rounded-md px-3 py-2 {{ empty($navContext['requirement']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['asset']->name }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['requirement']))
                                        <a href="{{ route('assets.requirements.show', [$navContext['asset']->id, $navContext['requirement']->id]) }}" @click="mobileMenuOpen = false"
                                           class="ml-8 block rounded-md px-3 py-2 {{ empty($navContext['task']) && empty($navContext['documentSection']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['requirement']->name ?? $navContext['requirement']->title ?? $navContext['requirement']->template?->name ?? 'Requerimiento' }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['task']))
                                        <a href="{{ route('requirements.tasks.show', [$navContext['requirement']->id, $navContext['task']->id]) }}" @click="mobileMenuOpen = false"
                                           class="ml-12 block rounded-md px-3 py-2 {{ empty($navContext['documentSection']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['task']->title ?? $navContext['task']->name ?? 'Tarea' }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['documentSection']) && ($navContext['documentOwner'] ?? null) === 'requirement')
                                        <a href="{{ route('assets.requirements.documents.index', [$navContext['asset']->id, $navContext['requirement']->id]) }}" @click="mobileMenuOpen = false"
                                           class="ml-12 block rounded-md bg-blue-50 px-3 py-2 font-semibold text-[#1A428A]">
                                            <span class="mr-2 text-gray-400">└</span>Documentos
                                        </a>
                                    @endif
                                    @if(!empty($navContext['documentSection']) && ($navContext['documentOwner'] ?? null) === 'task')
                                        <a href="{{ route('tasks.documents.index', $navContext['task']->id) }}" @click="mobileMenuOpen = false"
                                           class="ml-16 block rounded-md bg-blue-50 px-3 py-2 font-semibold text-[#1A428A]">
                                            <span class="mr-2 text-gray-400">└</span>Documentos
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif

                        @if(!$user?->isSuperAdmin() && $user?->isAdmin())
                            <hr class="my-2 border-gray-100">
                            <a href="{{ route('users.index') }}" @click="mobileMenuOpen = false"
                               class="flex items-center gap-2 rounded-md px-3 py-2 {{ request()->routeIs('users.*') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                Usuarios
                            </a>
                        @endif
                    </nav>
                </div>
            </div>
        </aside>

        <div class="mx-auto flex min-h-0 w-full max-w-[1680px] flex-1 gap-8 px-4 py-4 sm:px-6 sm:py-6">
            <aside class="hidden shrink-0 lg:block lg:w-[280px] xl:w-[250px]">
                <div class="h-full rounded-xl bg-white p-4 shadow">
                    <div class="mb-3 flex items-center gap-2 text-sm font-semibold text-[#1A428A]">
                        <span>☰</span>
                        <span>Menú</span>
                    </div>

                    <nav class="space-y-1 text-sm">
                        @if($user?->isSuperAdmin())
                            <p class="mb-1 px-3 text-xs font-bold uppercase tracking-wider text-gray-400">Sistema</p>
                            <a href="{{ route('superadmin.dashboard') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Panel general</a>
                            <a href="{{ route('superadmin.groups') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.groups') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Grupos</a>
                            <a href="{{ route('superadmin.companies') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.companies') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Empresas</a>
                            <a href="{{ route('superadmin.users') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('superadmin.users') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Usuarios</a>
                        @elseif($currentModule === 'procesos')
                            {{-- ── MÓDULO: PROCESOS ── --}}
                            <a href="{{ route('processes.dashboard') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('processes.dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Tablero</a>

                            <a href="{{ route('processes.index') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('processes.index', 'processes.show', 'processes.create') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Documentos</a>

                            @if($pendingApprovalsCount > 0 || request()->routeIs('my-approvals.*'))
                                <a href="{{ route('my-approvals.index') }}"
                                   class="flex items-center justify-between rounded-md px-3 py-2 {{ request()->routeIs('my-approvals.*') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <span>Mis aprobaciones</span>
                                    @if($pendingApprovalsCount > 0)
                                        <span class="inline-flex items-center justify-center h-5 min-w-5 px-1.5 rounded-full bg-yellow-400 text-yellow-900 text-xs font-bold">{{ $pendingApprovalsCount }}</span>
                                    @endif
                                </a>
                            @endif
                        @else
                            {{-- ── MÓDULO: CUMPLIMIENTO ── --}}
                            <a href="{{ route('dashboard') }}"
                               class="block rounded-md px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Tablero</a>

                            @if($user?->isAdmin() || $user?->isOperative() || $user?->isReadOnly())
                                <a href="{{ route('documents.index') }}"
                                   class="block rounded-md px-3 py-2 {{ request()->routeIs('documents.*') ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Documentos generales</a>
                            @endif

                            @php $inCumplimiento = request()->routeIs('assets.*') || !empty($navContext['asset']); @endphp
                            <div x-data="{ open: {{ $inCumplimiento ? 'true' : 'false' }} }">
                                <button type="button" @click="open = !open"
                                    class="flex w-full items-center justify-between rounded-md px-3 py-2 text-left text-sm {{ $inCumplimiento ? 'font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                    <span>Cumplimiento</span>
                                    <svg :class="{ 'rotate-180': open }" class="h-4 w-4 transition-transform duration-200"
                                         xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                                    </svg>
                                </button>
                                <div x-show="open" class="mt-1 space-y-1 pl-2">
                                    <a href="{{ route('assets.index') }}"
                                       class="block rounded-md px-3 py-2 {{ $inCumplimiento ? 'bg-gray-100 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">Energético</a>

                                    @if(!empty($navContext['asset']))
                                        <a href="{{ route('assets.show', $navContext['asset']) }}"
                                           class="ml-4 block rounded-md px-3 py-2 {{ empty($navContext['requirement']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['asset']->name }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['requirement']))
                                        <a href="{{ route('assets.requirements.show', [$navContext['asset']->id, $navContext['requirement']->id]) }}"
                                           class="ml-8 block rounded-md px-3 py-2 {{ empty($navContext['task']) && empty($navContext['documentSection']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['requirement']->name ?? $navContext['requirement']->title ?? $navContext['requirement']->template?->name ?? 'Requerimiento' }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['task']))
                                        <a href="{{ route('requirements.tasks.show', [$navContext['requirement']->id, $navContext['task']->id]) }}"
                                           class="ml-12 block rounded-md px-3 py-2 {{ empty($navContext['documentSection']) ? 'bg-blue-50 font-semibold text-[#1A428A]' : 'text-gray-700 hover:bg-gray-50' }}">
                                            <span class="mr-2 text-gray-400">└</span>{{ $navContext['task']->title ?? $navContext['task']->name ?? 'Tarea' }}
                                        </a>
                                    @endif
                                    @if(!empty($navContext['documentSection']) && ($navContext['documentOwner'] ?? null) === 'requirement')
                                        <a href="{{ route('assets.requirements.documents.index', [$navContext['asset']->id, $navContext['requirement']->id]) }}"
                                           class="ml-12 block rounded-md bg-blue-50 px-3 py-2 font-semibold text-[#1A428A]">
                                            <span class="mr-2 text-gray-400">└</span>Documentos
                                        </a>
                                    @endif
                                    @if(!empty($navContext['documentSection']) && ($navContext['documentOwner'] ?? null) === 'task')
                                        <a href="{{ route('tasks.documents.index', $navContext['task']->id) }}"
                                           class="ml-16 block rounded-md bg-blue-50 px-3 py-2 font-semibold text-[#1A428A]">
                                            <span class="mr-2 text-gray-400">└</span>Documentos
                                        </a>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </nav>
                </div>
            </aside>

            <div class="min-h-0 flex-1 overflow-y-auto pr-0 sm:pr-1">
                @isset($breadcrumb)
                    <div class="mb-4 flex min-w-0 flex-wrap items-center gap-x-1 gap-y-1 text-sm text-gray-500">
                        <span class="shrink-0 text-gray-400">⌂</span>
                        {{ $breadcrumb }}
                    </div>
                @endisset

                @if(session('success'))
                    <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {{ session('success') }}
                    </div>
                @endif

                <main>
                    {{ $slot }}
                </main>
            </div>
        </div>
    </div>
</body>
</html>