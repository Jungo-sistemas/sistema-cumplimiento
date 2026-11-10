{{-- resources/views/components/layouts/vigia.blade.php --}}
@props([
    'title' => null,
    'minimal' => false,
    'backRoute' => null,
    'backLabel' => null,
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

    // Procesos y Auditorías son un solo módulo (nov 2026): Auditorías va con la licencia de Procesos
    // y los dos lados están conectados (una auditoría revisa procesos).
    $allModules = [
        'cumplimiento' => ['label' => 'Cumplimiento',          'route' => 'dashboard'],
        'procesos'     => ['label' => 'Procesos y Auditorías', 'route' => 'gestion.home'],
    ];
    // Solo los módulos a los que el usuario de verdad puede entrar: Procesos y Auditorías también
    // depende de la licencia, no solo de module_access (si no, el menú y "Volver a…" mandaban a
    // pantallas que luego lo rechazaban).
    $modules = collect($allModules)
        ->filter(fn ($_, $key) => match ($key) {
            'procesos' => $user?->canUseProcesos() ?? false,
            default    => $user?->canAccessModule($key) ?? true,
        })
        ->all();

    // Módulo de la página actual. Las páginas que no son de un módulo (Usuarios, Mi perfil…) se
    // muestran con el último módulo en el que anduvo el usuario, y "Volver a…" regresa a ese.
    $routeModule = match (true) {
        request()->routeIs('gestion.*', 'auditorias.*', 'processes.*', 'my-approvals.*', 'regulation-versions.*') => 'procesos',
        request()->routeIs('dashboard', 'assets.*', 'requirements.*', 'tasks.*', 'documents.*', 'investment-requests.*') => 'cumplimiento',
        default => null,
    };
    if ($routeModule && isset($modules[$routeModule])) {
        session(['last_module' => $routeModule]);
    }
    $currentModule = $routeModule ?? session('last_module');
    if (!isset($modules[$currentModule])) {
        // Sin historial (o ya sin acceso a ese módulo): Cumplimiento si lo tiene, si no el primero.
        $currentModule = isset($modules['cumplimiento']) ? 'cumplimiento' : (array_key_first($modules) ?? 'cumplimiento');
    }
    $pendingApprovalsCount = $user && !$user->isSuperAdmin()
        ? \App\Models\RegulationApproval::where('user_id', $user->id)->where('status', 'pending')->count()
        : 0;
    $pendingInvestmentApprovalsCount = $user
        ? \App\Models\InvestmentRequestApproval::where('user_id', $user->id)->where('status', 'pending')->count()
        : 0;

    // ── Vista: Gestión o Mi espacio ──
    // Quien no tiene Gestión (solo lectura) solo tiene Mi espacio; admins, auditores y operativos
    // cambian con el botón del encabezado (que guarda la elección en sesión). Las páginas
    // compartidas entre ambas vistas — consultar un proceso, por ejemplo — se muestran con el menú
    // de la última vista elegida.
    $canSwitchView = $user && $user->hasGestion() && $user->canUseAuditeeView();
    $sharedRoute = request()->routeIs('processes.show', 'processes.print', 'processes.preview', 'regulation-versions.preview', 'profile.*');
    $viewMode = match (true) {
        ! $user || $user->isSuperAdmin()     => 'gestion',
        $user->isAuditeeOnly()              => 'auditado',
        request()->routeIs('mi-espacio.*')  => 'auditado',
        $canSwitchView && $sharedRoute && session('view_mode') === 'auditado' => 'auditado',
        default                             => 'gestion',
    };

    // Vistas "minimal" no muestran el menú lateral contextual — en su lugar, un solo botón de
    // regreso. Por defecto regresa al módulo "de casa" del usuario (ver abajo), pero una página
    // que sea un sub-paso dentro de otra sección (p. ej. "Agregar usuario" dentro de Usuarios)
    // puede pasar backRoute/backLabel para regresar a esa sección en concreto en vez de saltarse
    // directo al módulo.
    if ($backRoute) {
        $homeModule = ['label' => $backLabel ?? $title, 'route' => $backRoute];
    } elseif ($user?->isSuperAdmin()) {
        // El superadmin no vive en Cumplimiento/Procesos (esos son los módulos de cliente) — su
        // "inicio" real es el panel de Sistema, igual que ya es el primer bloque del menú lateral
        // para ese rol.
        $homeModule = ['label' => 'Panel general', 'route' => 'superadmin.dashboard'];
    } elseif ($viewMode === 'auditado') {
        $homeModule = ['label' => 'Mi espacio', 'route' => 'mi-espacio.home'];
    } else {
        // Regresa al módulo en el que andaba el usuario (ya validado arriba contra lo que tiene
        // autorizado), no siempre a Cumplimiento.
        $homeModule = $modules[$currentModule] ?? $allModules['cumplimiento'];
    }

    // ── Menú lateral ──
    // Secciones con título y ligas; el mismo arreglo pinta el menú de escritorio (colapsable) y el
    // del celular. Cada liga: label, url, icon, active y, opcionales, badge, tour (data-tour de la
    // guía de Procesos), children (el árbol activo → requisito → tarea de Cumplimiento).
    $item = fn (string $label, string $route, string $icon, bool $active, array $extra = []) =>
        ['label' => $label, 'url' => route($route), 'icon' => $icon, 'active' => $active] + $extra;

    $sections = [];
    $helpItem = null;

    if ($minimal) {
        $sections[] = ['title' => null, 'items' => [
            $item('Volver a ' . $homeModule['label'], $homeModule['route'], 'back', true),
        ]];
    } elseif ($user?->isSuperAdmin()) {
        $sections[] = ['title' => 'Sistema', 'items' => [
            $item('Panel general', 'superadmin.dashboard', 'chart', request()->routeIs('superadmin.dashboard')),
            $item('Grupos', 'superadmin.groups', 'layers', request()->routeIs('superadmin.groups')),
            $item('Empresas', 'superadmin.companies', 'building', request()->routeIs('superadmin.companies')),
            $item('Usuarios', 'superadmin.users', 'users', request()->routeIs('superadmin.users')),
        ]];
    } elseif ($viewMode === 'auditado') {
        $sections[] = ['title' => 'Mi espacio', 'items' => [
            $item('Inicio', 'mi-espacio.home', 'home', request()->routeIs('mi-espacio.home')),
            $item('Mis solicitudes', 'mi-espacio.requests', 'clock', request()->routeIs('mi-espacio.requests')),
        ]];
        // Mis aprobaciones vive en Gestión (Procesos). Solo quien no tiene Gestión (solo lectura) y
        // tiene aprobaciones asignadas la ve aquí.
        $miEspacioProcesos = [
            $item('Mis documentos', 'mi-espacio.documents', 'document', request()->routeIs('mi-espacio.documents') || $sharedRoute && ! request()->routeIs('profile.*')),
        ];
        if (! $user->hasGestion() && $pendingApprovalsCount > 0) {
            $miEspacioProcesos[] = $item('Mis aprobaciones', 'my-approvals.index', 'badge', request()->routeIs('my-approvals.*'), ['badge' => $pendingApprovalsCount]);
        }
        $sections[] = ['title' => 'Procesos', 'items' => $miEspacioProcesos];
        $sections[] = ['title' => 'Auditorías', 'items' => [
            $item('Mis auditorías', 'mi-espacio.audits', 'check', request()->routeIs('mi-espacio.audits')),
            $item('Mis actividades', 'mi-espacio.activities', 'alert', request()->routeIs('mi-espacio.activities')),
        ]];
        $helpItem = $item('Ayuda', 'mi-espacio.help', 'help', request()->routeIs('mi-espacio.help'));
    } elseif ($currentModule === 'procesos') {
        // Gestión de Procesos y Auditorías. Admin: todo, con los dos tableros de indicadores.
        // Operativo: solo Procesos, sin indicadores (lo de auditorías lo ve en Mi espacio).
        // Auditor: Procesos para consultar y Auditorías.
        $sections[] = ['title' => null, 'items' => [
            $item('Inicio', 'gestion.home', 'home', request()->routeIs('gestion.home')),
        ]];

        $procesosItems = [
            $item('Procedimientos', 'processes.index', 'document', request()->routeIs('processes.index', 'processes.show', 'processes.create'), ['tour' => 'nav-procedimientos']),
        ];
        if (! $user->isAuditor()) {
            // Siempre visible: si quien aprueba se queda sin pendientes, la pantalla dice "Todo al día".
            $procesosItems[] = $item('Mis aprobaciones', 'my-approvals.index', 'badge', request()->routeIs('my-approvals.*'), ['tour' => 'nav-mis-aprobaciones', 'badge' => $pendingApprovalsCount]);
        }
        if ($user->isAdmin()) {
            $procesosItems[] = $item('Indicadores de procesos', 'processes.dashboard', 'chart', request()->routeIs('processes.dashboard'), ['tour' => 'nav-tablero']);
        }
        $sections[] = ['title' => 'Procesos', 'items' => $procesosItems];

        if ($user->isAdmin() || $user->isAuditor()) {
            $auditoriasItems = [
                $item('Auditorías', 'auditorias.audits.index', 'clipboard', request()->routeIs('auditorias.audits.*', 'auditorias.home')),
                $item('Hallazgos', 'auditorias.findings.index', 'flag', request()->routeIs('auditorias.findings.*')),
            ];
            if ($user->isAdmin()) {
                $auditoriasItems[] = $item('Indicadores de auditorías', 'auditorias.indicators', 'chart', request()->routeIs('auditorias.indicators'));
            }
            $sections[] = ['title' => 'Auditorías', 'items' => $auditoriasItems];
        }

        $helpItem = request()->routeIs('auditorias.*')
            ? $item('Ayuda', 'auditorias.help', 'help', request()->routeIs('auditorias.help'))
            : $item('Ayuda', 'processes.guide.manual', 'help', false, ['newTab' => true]);
    } else {
        $cumplimientoItems = [
            $item('Tablero', 'dashboard', 'chart', request()->routeIs('dashboard')),
        ];
        if ($user?->isAdmin() || $user?->isOperative() || $user?->isReadOnly()) {
            $cumplimientoItems[] = $item('Documentos generales', 'documents.index', 'folder', request()->routeIs('documents.*'));
            $cumplimientoItems[] = $item('Solicitud de Inversiones', 'investment-requests.index', 'cash', request()->routeIs('investment-requests.*'), ['badge' => $pendingInvestmentApprovalsCount]);
        }

        // Árbol del activo abierto: Regulatorio → activo → requisito → tarea / documentos.
        $ctx = $navContext;
        $children = [];
        if (!empty($ctx['asset'])) {
            $children[] = ['label' => $ctx['asset']->name, 'url' => route('assets.show', $ctx['asset']), 'depth' => 1,
                'active' => empty($ctx['requirement']) && ($ctx['documentOwner'] ?? null) !== 'asset'];
        }
        if (!empty($ctx['requirement'])) {
            $children[] = ['label' => $ctx['requirement']->name ?? $ctx['requirement']->title ?? $ctx['requirement']->template?->name ?? 'Requerimiento',
                'url' => route('assets.requirements.show', [$ctx['asset']->id, $ctx['requirement']->id]), 'depth' => 2,
                'active' => empty($ctx['task']) && empty($ctx['documentSection'])];
        }
        if (!empty($ctx['task'])) {
            $children[] = ['label' => $ctx['task']->title ?? $ctx['task']->name ?? 'Tarea',
                'url' => route('requirements.tasks.show', [$ctx['requirement']->id, $ctx['task']->id]), 'depth' => 3,
                'active' => empty($ctx['documentSection'])];
        }
        if (!empty($ctx['documentSection'])) {
            $children[] = match ($ctx['documentOwner'] ?? null) {
                'requirement' => ['label' => 'Documentos', 'url' => route('assets.requirements.documents.index', [$ctx['asset']->id, $ctx['requirement']->id]), 'depth' => 3, 'active' => true],
                'task'        => ['label' => 'Documentos', 'url' => route('tasks.documents.index', $ctx['task']->id), 'depth' => 4, 'active' => true],
                'asset'       => ['label' => 'Info extra', 'url' => route('assets.extra-documents.index', $ctx['asset']->id), 'depth' => 2, 'active' => true],
                default       => null,
            };
        }
        $inCumplimiento = request()->routeIs('assets.*') || !empty($ctx['asset']);
        $cumplimientoItems[] = $item('Regulatorio', 'assets.index', 'bolt', $inCumplimiento, ['children' => array_values(array_filter($children))]);

        $sections[] = ['title' => 'Cumplimiento', 'items' => $cumplimientoItems];
    }

    if (!$minimal && $viewMode === 'gestion' && !$user?->isSuperAdmin() && $user?->isAdmin()) {
        $sections[] = ['title' => 'Administración', 'items' => [
            $item('Usuarios', 'users.index', 'users', request()->routeIs('users.*')),
        ]];
    }

    $notifications = array_values(array_filter([
        $pendingApprovalsCount > 0 ? ['label' => 'Procesos por aprobar', 'count' => $pendingApprovalsCount, 'url' => route('my-approvals.index')] : null,
        $pendingInvestmentApprovalsCount > 0 ? ['label' => 'Solicitudes de inversión por aprobar', 'count' => $pendingInvestmentApprovalsCount, 'url' => route('investment-requests.index')] : null,
    ]));
    $notificationsCount = collect($notifications)->sum('count');

    $initials = collect(preg_split('/\s+/', trim($user?->name ?? '')))
        ->filter()->take(2)->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))->implode('');
    $companyLabel = $user?->hasGroupScope() ? $user->group?->name : $user?->company?->name;
@endphp

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    @include('partials.brand-head')
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}" @auth data-keep-alive="{{ route('session.keepAlive') }}" @endauth>
    <title>{{ $title ? $title . ' · Jungo Cumple' : 'Jungo Cumple' }}</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="h-screen overflow-hidden bg-[#F7F7FA] text-gray-900">
    {{-- El menú colapsado se recuerda en este navegador (localStorage puede fallar en modo privado).
         En pantallas de menos de 1536 px (laptops de 1366/1440 px o con zoom del sistema) arranca colapsado
         siempre: abierto ocupa 300 px y el contenido quedaba apretado y deformado. Ahí se puede
         abrir con el botón, pero no se recuerda. --}}
    <div x-data="{
            mobileMenuOpen: false,
            userCollapsed: (() => { try { return localStorage.getItem('jc.sidebarCollapsed') === '1' } catch (e) { return false } })(),
            narrow: window.matchMedia('(max-width: 1535px)').matches,
            narrowExpanded: false,
            get collapsed() { return this.narrow ? ! this.narrowExpanded : this.userCollapsed },
            init() {
                window.matchMedia('(max-width: 1535px)').addEventListener('change', (e) => { this.narrow = e.matches; this.narrowExpanded = false; });
            },
            toggleSidebar() {
                if (this.narrow) { this.narrowExpanded = ! this.narrowExpanded; return; }
                this.userCollapsed = ! this.userCollapsed;
                try { localStorage.setItem('jc.sidebarCollapsed', this.userCollapsed ? '1' : '0') } catch (e) {}
            },
         }"
         class="flex h-screen flex-col">
        <header class="shrink-0 bg-brand-ink text-white">
            <div class="flex items-center justify-between gap-3 px-4 py-4 sm:px-7 sm:py-5">
                <div class="flex min-w-0 items-center gap-3">
                    <button type="button" class="inline-flex items-center justify-center rounded-md p-2 hover:bg-white/10 lg:hidden"
                            @click="mobileMenuOpen = true" aria-label="Abrir menú">
                        <x-icon name="menu" class="h-6 w-6" />
                    </button>

                    {{-- El logo es el "inicio" de la vista/módulo en el que ya estás. --}}
                    <a href="{{ route(match (true) {
                            (bool) $user?->isSuperAdmin() => 'superadmin.dashboard',
                            $viewMode === 'auditado'      => 'mi-espacio.home',
                            default                       => $modules[$currentModule]['route'] ?? 'dashboard',
                        }) }}" class="flex items-center">
                        <x-brand-logo class="h-9" />
                    </a>

                    {{-- En la vista de auditado también sale, para admins y auditores: elegir un
                         módulo los pasa a Gestión en ese módulo. Quien solo es auditado no lo ve
                         (los tableros de los módulos lo regresan a Mi espacio). --}}
                    @if(!$user?->isSuperAdmin() && ($viewMode === 'gestion' || $canSwitchView))
                        <span class="ml-2 hidden h-6 w-px bg-white/25 sm:block"></span>

                        <div x-data="{ open: false }" class="relative hidden sm:block">
                            <button type="button" @click="open = !open" @keydown.escape="open = false"
                                    class="flex items-center gap-1.5 rounded-md px-2 py-1 text-sm font-semibold text-white/90 hover:bg-white/10 focus:outline-none">
                                {{ $viewMode === 'auditado' ? 'Mi espacio' : $modules[$currentModule]['label'] }}
                                <x-icon name="chevron" class="h-3.5 w-3.5 text-white/60 transition-transform duration-150" ::class="{ 'rotate-180': open }" />
                            </button>

                            <div x-show="open" x-transition.opacity @click.outside="open = false" style="display:none;"
                                 class="absolute left-0 top-full z-50 mt-2 w-44 rounded-lg border border-gray-100 bg-white py-1 text-gray-700 shadow-lg">
                                @foreach($modules as $key => $mod)
                                    @php $isCurrent = $viewMode === 'gestion' && $currentModule === $key; @endphp
                                    <a href="{{ $viewMode === 'auditado' ? route('view-mode.switch', ['mode' => 'gestion', 'modulo' => $key]) : route($mod['route']) }}" @click="open = false"
                                       class="flex items-center gap-2 px-3 py-2 text-sm {{ $isCurrent ? 'font-semibold text-brand' : 'hover:bg-gray-50' }}">
                                        @if($isCurrent)
                                            <x-icon name="check" class="h-3.5 w-3.5" />
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

                <div class="flex items-center gap-3 sm:gap-6">
                    {{-- El cambio de vista es de Procesos y Auditorías: "Gestión" es donde se trabaja
                         y "Mi espacio" lo que le toca a la persona como auditada. En Cumplimiento no aparece. --}}
                    @if($canSwitchView && ($viewMode === 'auditado' || $currentModule === 'procesos'))
                        <div class="flex rounded-xl border border-white/20 p-1 text-sm font-semibold">
                            <a href="{{ route('view-mode.switch', 'gestion') }}"
                               class="rounded-lg px-3 py-2 sm:px-4 {{ $viewMode === 'gestion' ? 'bg-white text-brand-ink' : 'text-white/85 hover:bg-white/10' }}">Gestión</a>
                            <a href="{{ route('view-mode.switch', 'auditado') }}"
                               class="rounded-lg px-3 py-2 sm:px-4 {{ $viewMode === 'auditado' ? 'bg-white text-brand-ink' : 'text-white/85 hover:bg-white/10' }}">Mi espacio</a>
                        </div>
                    @endif

                    <div class="flex items-center gap-2 sm:gap-3">
                        {{-- Notificaciones: por ahora, lo que el usuario tiene por aprobar. --}}
                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @keydown.escape="open = false" aria-label="Notificaciones"
                                    class="relative inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 hover:bg-white/10 sm:h-12 sm:w-12">
                                <x-icon name="bell" class="h-5 w-5" />
                                @if($notificationsCount > 0)
                                    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-yellow-400 px-1.5 text-xs font-bold text-yellow-900">{{ $notificationsCount }}</span>
                                @endif
                            </button>

                            <div x-show="open" x-transition.opacity @click.outside="open = false" style="display:none;"
                                 class="absolute right-0 top-full z-50 mt-2 w-72 rounded-xl border border-gray-100 bg-white py-2 text-sm text-gray-700 shadow-lg">
                                <div class="px-4 pb-2 text-xs font-semibold uppercase tracking-wider text-gray-400">Notificaciones</div>
                                @forelse($notifications as $notification)
                                    <a href="{{ $notification['url'] }}" class="flex items-center justify-between gap-3 px-4 py-2 hover:bg-gray-50">
                                        <span>{{ $notification['label'] }}</span>
                                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-yellow-400 px-1.5 text-xs font-bold text-yellow-900">{{ $notification['count'] }}</span>
                                    </a>
                                @empty
                                    <p class="px-4 py-2 text-gray-500">No tienes notificaciones.</p>
                                @endforelse
                            </div>
                        </div>

                        <div x-data="{ open: false }" class="relative">
                            <button type="button" @click="open = !open" @keydown.escape="open = false" aria-label="Mi cuenta"
                                    class="inline-flex h-11 w-11 items-center justify-center rounded-xl border border-white/20 hover:bg-white/10 sm:h-12 sm:w-12">
                                <x-icon name="user" class="h-6 w-6" />
                            </button>

                            <div x-show="open" x-transition.opacity @click.outside="open = false" style="display:none;"
                                 class="absolute right-0 top-full z-50 mt-2 w-64 rounded-xl border border-gray-100 bg-white py-2 text-sm text-gray-700 shadow-lg">
                                <div class="border-b border-gray-100 px-4 pb-3">
                                    <div class="font-semibold text-gray-900">{{ $user?->name }}</div>
                                    <div class="truncate text-xs text-gray-500">{{ $user?->email }}</div>
                                </div>
                                <a href="{{ route('profile.edit') }}" class="block px-4 py-2 hover:bg-gray-50">Mi perfil</a>
                                @if(!$user?->isSuperAdmin() && $user?->isAdmin())
                                    <a href="{{ route('users.index') }}" class="block px-4 py-2 hover:bg-gray-50">Usuarios</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button class="block w-full px-4 py-2 text-left font-semibold text-brand hover:bg-gray-50">Cerrar sesión</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">
            {{-- Menú lateral de escritorio: ancho con textos, o angosto con ícono y texto abajo. --}}
            <aside class="hidden shrink-0 border-r border-gray-200 bg-white transition-[width] duration-200 lg:block"
                   :class="collapsed ? 'w-[80px]' : 'w-[300px]'">
                @include('layouts.partials.sidebar', ['mobile' => false])
            </aside>

            <div class="min-h-0 flex-1 overflow-y-auto">
                <div class="px-4 py-6 sm:px-6 sm:py-8 xl:px-10 xl:py-10">
                    @isset($breadcrumb)
                        <div class="mb-6 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1 text-sm text-gray-500">
                            {{ $breadcrumb }}
                        </div>
                    @endisset

                    @if(session('success'))
                        <div class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if(session('session_notice'))
                        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                            {{ session('session_notice') }}
                        </div>
                    @endif

                    <main>
                        {{ $slot }}
                    </main>
                </div>
            </div>
        </div>

        {{-- Menú del celular: el mismo menú, siempre abierto, en un panel que se desliza. --}}
        <div x-show="mobileMenuOpen" x-transition.opacity class="fixed inset-0 z-40 bg-black/40 lg:hidden"
             @click="mobileMenuOpen = false" style="display: none;"></div>

        <aside x-show="mobileMenuOpen"
               x-transition:enter="transition ease-out duration-200"
               x-transition:enter-start="-translate-x-full"
               x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-150"
               x-transition:leave-start="translate-x-0"
               x-transition:leave-end="-translate-x-full"
               class="fixed inset-y-0 left-0 z-50 w-[300px] max-w-[85vw] bg-white shadow-xl lg:hidden"
               style="display: none;">
            @include('layouts.partials.sidebar', ['mobile' => true])
        </aside>
    </div>

    @if($currentModule === 'procesos' && $viewMode === 'gestion')
        @if($user->needsProcesosGuide())
            <x-processes-guide mode="mandatory" />
        @elseif(session()->pull('show_procesos_guide_reminder'))
            <x-processes-guide mode="reminder" />
        @endif
    @endif

    @include('layouts.partials.confirm-dialog')
</body>
</html>
