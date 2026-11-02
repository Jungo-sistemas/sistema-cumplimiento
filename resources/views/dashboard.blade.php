<x-layouts.vigia :title="'Tablero'">
    <x-slot name="breadcrumb">
        <span class="text-gray-700 font-medium">Tablero</span>
    </x-slot>

    <div class="bg-white rounded-xl shadow p-6">
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold text-brand">Tablero de cumplimiento</h1>
        </div>

        {{-- Cards --}}
        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-brand">Activos</div>
                <div class="mt-2 text-2xl font-bold text-gray-800">{{ $stats['assets'] ?? 0 }}</div>
            </div>

            <div class="bg-white border rounded-lg shadow-sm p-4">
                <div class="text-sm font-semibold text-brand">Tareas pendientes</div>
                <div class="mt-2 text-2xl font-bold text-gray-800">{{ $stats['tasks'] ?? 0 }}</div>
            </div>

            <div class="bg-[#FFB529] rounded-lg shadow-sm p-4 text-white">
                <div class="text-sm font-semibold">Críticos</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['due_soon'] ?? 0 }}</div>
            </div>

            <div class="bg-[#DB0000] rounded-lg shadow-sm p-4 text-white">
                <div class="text-sm font-semibold">En riesgo</div>
                <div class="mt-2 text-2xl font-bold">{{ $stats['overdue'] ?? 0 }}</div>
            </div>
        </div>

        {{-- Tabs principales --}}
        <div class="mt-8">
            <div class="flex flex-wrap items-center justify-between gap-4 border-b pb-3">
                <div class="flex gap-2">
                    <button
                        type="button"
                        class="dashboard-tab-btn px-4 py-2 rounded-md bg-brand text-white text-sm font-semibold"
                        data-tab="requirements"
                    >
                        Requerimientos
                    </button>

                    <button
                        type="button"
                        class="dashboard-tab-btn px-4 py-2 rounded-md border text-sm font-semibold text-gray-700"
                        data-tab="tasks"
                    >
                        Tareas
                    </button>
                </div>

                <div id="tasks-switch-wrapper" class="hidden">
                    <form method="GET" action="{{ route('dashboard') }}" class="flex items-center gap-2">
                        <input type="hidden" name="tab" value="tasks">

                        <label for="all_tasks" class="text-sm text-gray-700">Ver todas</label>

                        <input
                            id="all_tasks"
                            type="checkbox"
                            name="all_tasks"
                            value="1"
                            {{ $showAllTasks ? 'checked' : '' }}
                            onchange="this.form.submit()"
                            class="rounded border-gray-300 text-brand focus:ring-brand"
                        >
                    </form>
                </div>
            </div>

            {{-- TAB: Requerimientos --}}
            <div id="dashboard-tab-requirements" class="mt-6">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    {{-- Próximos a vencer --}}
                    <div>
                        <div class="text-sm font-semibold text-[#FFB529] mb-3">Fechas críticas</div>

                        <div class="border rounded-lg overflow-hidden">
                            @forelse($upcoming as $r)
                                <div class="p-4 border-b last:border-b-0">
                                    <div class="font-semibold text-gray-800">
                                        {{ $r->template?->name ?? $r->type ?? 'Requerimiento' }}
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        Activo: {{ $r->asset?->name ?? '—' }}
                                        · Tipo: {{ $r->asset?->assetType?->name ?? '—' }}
                                        · Vence: {{ $r->due_date?->format('Y-m-d') ?? '—' }}
                                    </div>

                                    @if($r->asset)
                                        <div class="mt-2">
                                            <a
                                                href="{{ route('assets.requirements.show', [$r->asset, $r]) }}"
                                                class="text-sm font-medium text-brand hover:underline"
                                            >
                                                Abrir
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-sm text-gray-500">
                                    No hay requerimientos próximos a vencer.
                                </div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Críticos --}}
                    <div>
                        <div class="text-sm font-semibold text-[#DB0000] mb-3">Fechas en riesgo</div>

                        <div class="border rounded-lg overflow-hidden">
                            @forelse($critical as $r)
                                <div class="p-4 border-b last:border-b-0">
                                    <div class="flex items-center justify-between gap-4">
                                        <div class="font-semibold text-gray-800">
                                            {{ $r->template?->name ?? $r->type ?? 'Requerimiento' }}
                                        </div>

                                        <span class="text-xs font-semibold px-2 py-1 rounded-full
                                            {{ $r->risk_level === 'expired'
                                                ? 'bg-red-100 text-red-700'
                                                : 'bg-yellow-100 text-yellow-700' }}">
                                            {{ $r->risk_level === 'expired' ? 'Vencido' : 'En riesgo' }}
                                        </span>
                                    </div>

                                    <div class="text-xs text-gray-500 mt-1">
                                        Activo: {{ $r->asset?->name ?? '—' }}
                                        · Tipo: {{ $r->asset?->assetType?->name ?? '—' }}
                                        · Vence: {{ $r->due_date?->format('Y-m-d') ?? '—' }}
                                    </div>

                                    @if($r->asset)
                                        <div class="mt-2">
                                            <a
                                                href="{{ route('assets.requirements.show', [$r->asset, $r]) }}"
                                                class="text-sm font-medium text-brand hover:underline"
                                            >
                                                Abrir
                                            </a>
                                        </div>
                                    @endif
                                </div>
                            @empty
                                <div class="p-4 text-sm text-gray-500">
                                    No hay requerimientos críticos.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- TAB: Tareas --}}
            <div id="dashboard-tab-tasks" class="mt-6 hidden">
                <div class="flex flex-wrap gap-2 mb-4">
                    <button
                        type="button"
                        class="task-subtab-btn px-3 py-2 rounded-md bg-brand text-white text-sm font-semibold"
                        data-subtab="pending"
                    >
                        En tiempo
                    </button>

                    <button
                        type="button"
                        class="task-subtab-btn px-3 py-2 rounded-md border text-sm font-semibold text-gray-700"
                        data-subtab="soon"
                    >
                        Próximas a vencer
                    </button>

                    <button
                        type="button"
                        class="task-subtab-btn px-3 py-2 rounded-md border text-sm font-semibold text-gray-700"
                        data-subtab="overdue"
                    >
                        Vencidas
                    </button>
                </div>

                {{-- Subtab: Pendientes --}}
                <div id="task-subtab-pending">
                    <div class="border rounded-lg overflow-hidden">
                        @forelse($tasksPending as $task)
                            <div class="p-4 border-b last:border-b-0">
                                <div class="font-semibold text-gray-800">
                                    {{ $task->title }}
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    Activo: {{ $task->requirement?->asset?->name ?? '—' }}
                                    · Carpeta: {{ $task->requirement?->template?->name ?? $task->requirement?->type ?? '—' }}
                                    · Vence: {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                                </div>

                                @if($task->requirement && $task->requirement->asset)
                                    <div class="mt-2">
                                        <a
                                            href="{{ route('assets.requirements.show', [$task->requirement->asset, $task->requirement]) }}"
                                            class="text-sm font-medium text-brand hover:underline"
                                        >
                                            Abrir
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-4 text-sm text-gray-500">
                                No hay tareas pendientes.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Subtab: Próximas --}}
                <div id="task-subtab-soon" class="hidden">
                    <div class="border rounded-lg overflow-hidden">
                        @forelse($tasksDueSoon as $task)
                            <div class="p-4 border-b last:border-b-0">
                                <div class="font-semibold text-gray-800">
                                    {{ $task->title }}
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    Activo: {{ $task->requirement?->asset?->name ?? '—' }}
                                    · Carpeta: {{ $task->requirement?->template?->name ?? $task->requirement?->type ?? '—' }}
                                    · Vence: {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                                </div>

                                @if($task->requirement && $task->requirement->asset)
                                    <div class="mt-2">
                                        <a
                                            href="{{ route('assets.requirements.show', [$task->requirement->asset, $task->requirement]) }}"
                                            class="text-sm font-medium text-brand hover:underline"
                                        >
                                            Abrir
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-4 text-sm text-gray-500">
                                No hay tareas próximas a vencer.
                            </div>
                        @endforelse
                    </div>
                </div>

                {{-- Subtab: Vencidas --}}
                <div id="task-subtab-overdue" class="hidden">
                    <div class="border rounded-lg overflow-hidden">
                        @forelse($tasksOverdue as $task)
                            <div class="p-4 border-b last:border-b-0">
                                <div class="font-semibold text-gray-800">
                                    {{ $task->title }}
                                </div>

                                <div class="text-xs text-gray-500 mt-1">
                                    Activo: {{ $task->requirement?->asset?->name ?? '—' }}
                                    · Carpeta: {{ $task->requirement?->template?->name ?? $task->requirement?->type ?? '—' }}
                                    · Vence: {{ $task->due_date?->format('Y-m-d') ?? '—' }}
                                </div>

                                @if($task->requirement && $task->requirement->asset)
                                    <div class="mt-2">
                                        <a
                                            href="{{ route('assets.requirements.show', [$task->requirement->asset, $task->requirement]) }}"
                                            class="text-sm font-medium text-brand hover:underline"
                                        >
                                            Abrir
                                        </a>
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="p-4 text-sm text-gray-500">
                                No hay tareas vencidas.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const dashboardTabButtons = document.querySelectorAll('.dashboard-tab-btn');
        const requirementsTab = document.getElementById('dashboard-tab-requirements');
        const tasksTab = document.getElementById('dashboard-tab-tasks');
        const tasksSwitchWrapper = document.getElementById('tasks-switch-wrapper');

        dashboardTabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const isRequirements = btn.dataset.tab === 'requirements';

                requirementsTab.classList.toggle('hidden', !isRequirements);
                tasksTab.classList.toggle('hidden', isRequirements);
                tasksSwitchWrapper.classList.toggle('hidden', isRequirements);

                dashboardTabButtons.forEach(b => {
                    b.classList.remove('bg-brand', 'text-white');
                    b.classList.add('border', 'text-gray-700');
                });

                btn.classList.remove('border', 'text-gray-700');
                btn.classList.add('bg-brand', 'text-white');
            });
        });

        const taskSubtabButtons = document.querySelectorAll('.task-subtab-btn');
        const pendingTab = document.getElementById('task-subtab-pending');
        const soonTab = document.getElementById('task-subtab-soon');
        const overdueTab = document.getElementById('task-subtab-overdue');

        taskSubtabButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                const target = btn.dataset.subtab;

                pendingTab.classList.toggle('hidden', target !== 'pending');
                soonTab.classList.toggle('hidden', target !== 'soon');
                overdueTab.classList.toggle('hidden', target !== 'overdue');

                taskSubtabButtons.forEach(b => {
                    b.classList.remove('bg-brand', 'text-white');
                    b.classList.add('border', 'text-gray-700');
                });

                btn.classList.remove('border', 'text-gray-700');
                btn.classList.add('bg-brand', 'text-white');
            });
        });

        const params = new URLSearchParams(window.location.search);
        if (params.get('tab') === 'tasks') {
            document.querySelector('.dashboard-tab-btn[data-tab="tasks"]')?.click();
        }
    </script>
</x-layouts.vigia>