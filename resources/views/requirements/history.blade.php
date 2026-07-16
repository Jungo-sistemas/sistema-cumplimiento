<x-layouts.vigia :title="'Historial: ' . ($requirement->template?->name ?? $requirement->type)">
    <x-slot name="breadcrumb">
        <a href="{{ route('assets.index') }}" class="text-gray-600 hover:underline">Energético</a>
        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.show', $asset) }}" class="text-gray-600 hover:underline">
            {{ $asset->name }}
        </a>
        <span class="text-gray-400">›</span>

        <a href="{{ route('assets.requirements.show', [$asset, $requirement]) }}"
           class="text-gray-600 hover:underline">
            <x-truncate max="max-w-[400px]" class="font-semibold text-gray-700">
                {{ $requirement->template?->name ?? $requirement->type }}
            </x-truncate>
        </a>
        <span class="text-gray-400">›</span>

        <span class="text-gray-700 font-medium">Historial</span>
    </x-slot>

    @php
        /**
         * Traduce actions a etiquetas humanas
         */
        $actionLabel = function (?string $action): string {
            return match($action) {
                'task.created' => 'Tarea creada',
                'task.updated' => 'Tarea actualizada',
                'task.completed' => 'Tarea completada',
                'task.reopened' => 'Tarea reabierta',
                'task.deleted' => 'Tarea eliminada',

                'requirement.created' => 'Carpeta creada',
                'requirement.updated' => 'Carpeta actualizada',
                'requirement.completed' => 'Carpeta completada',
                'requirement.reopened' => 'Carpeta reabierta',
                'requirement.deactivated' => 'Carpeta desactivada',
                'requirement.activated' => 'Carpeta activada',

                'document.uploaded' => 'Documento subido',
                'document.deleted' => 'Documento eliminado',

                default => $action ? str_replace('.', ' · ', $action) : 'Cambio registrado',
            };
        };

        /**
         * Pretty print del meta (muestra info útil según action)
         */
        $metaLines = function ($event): array {
            $meta = $event->meta ?? null;

            // Por si viniera como string JSON (aunque tu toArray ya lo castea)
            if (is_string($meta)) {
                $decoded = json_decode($meta, true);
                $meta = is_array($decoded) ? $decoded : null;
            }

            if (!is_array($meta)) return [];

            $lines = [];

            // helpers
            $fmtDate = function ($value) {
                try {
                    if (!$value) return null;
                    return \Carbon\Carbon::parse($value)->format('Y-m-d');
                } catch (\Throwable $e) {
                    return is_scalar($value) ? (string)$value : null;
                }
            };

            // Casos típicos por acción
            switch ($event->action) {
                case 'task.created':
                case 'task.updated':
                    if (!empty($meta['title'])) $lines[] = ['label' => 'Título', 'value' => $meta['title']];
                    if (!empty($meta['due_date'])) $lines[] = ['label' => 'Vence', 'value' => $fmtDate($meta['due_date'])];
                    if (array_key_exists('requires_document', $meta)) {
                        $lines[] = ['label' => 'Requiere evidencia', 'value' => $meta['requires_document'] ? 'Sí' : 'No'];
                    }
                    break;

                case 'task.completed':
                case 'task.reopened':
                    if (!empty($meta['title'])) $lines[] = ['label' => 'Tarea', 'value' => $meta['title']];
                    break;

                case 'requirement.completed':
                case 'requirement.updated':
                    if (!empty($meta['name'])) $lines[] = ['label' => 'Carpeta', 'value' => $meta['name']];
                    if (!empty($meta['due_date'])) $lines[] = ['label' => 'Vence', 'value' => $fmtDate($meta['due_date'])];
                    break;

                default:
                    // Fallback: imprime pares clave/valor
                    foreach ($meta as $k => $v) {
                        if (is_array($v) || is_object($v)) {
                            $v = json_encode($v);
                        } elseif (is_bool($v)) {
                            $v = $v ? 'true' : 'false';
                        }
                        $lines[] = ['label' => $k, 'value' => (string)$v];
                    }
                    break;
            }

            return $lines;
        };
    @endphp

    <div class="bg-white rounded-xl shadow p-6">

        {{-- Header --}}
        <div class="flex items-start justify-between gap-6">
            <div>
                <h1 class="text-2xl font-bold text-[#1A428A]">
                    Historial de cambios
                </h1>

                <div class="text-sm text-gray-500 mt-1">
                    Activo: <span class="font-semibold text-gray-700">{{ $asset->name }}</span>
                    · Carpeta: <span class="font-semibold text-gray-700">
                        {{ $requirement->template?->name ?? $requirement->type }}
                    </span>
                </div>
            </div>

            <a href="{{ route('assets.requirements.show', [$asset, $requirement]) }}"
               class="px-4 py-2 rounded-md border bg-white text-[#1A428A] border-[#1A428A] font-semibold hover:bg-blue-50">
                Volver
            </a>
        </div>

        {{-- Timeline --}}
        <div class="mt-8 max-h-[calc(100vh-300px)] overflow-y-auto pr-3">
            @forelse($logs as $event)
                @php
                    $lines = $metaLines($event);
                    $who = $event->actor?->name ?? 'Sistema';
                    $title = $actionLabel($event->action);
                @endphp

                <div class="relative pl-6 pb-6 border-l border-gray-200">
                    <div class="absolute -left-2 top-1 w-3 h-3 bg-[#1A428A] rounded-full"></div>

                    <div class="bg-gray-50 border rounded-lg p-4">
                        <div class="flex items-center justify-between gap-4">
                            <div class="font-semibold text-gray-900">
                                {{ $title }}
                            </div>

                            <div class="text-xs text-gray-500">
                                {{ $event->created_at->format('Y-m-d H:i') }}
                            </div>
                        </div>

                        <div class="text-sm text-gray-600 mt-1">
                            Por: {{ $who }}
                        </div>

                        @if(count($lines))
                            <div class="mt-3 text-sm text-gray-700">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-2">
                                    @foreach($lines as $row)
                                        <div class="bg-white border rounded-md px-3 py-2">
                                            <div class="text-xs text-gray-500">{{ $row['label'] }}</div>
                                            <div class="font-semibold text-gray-800 break-words">{{ $row['value'] }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-gray-700 text-sm">
                    No hay movimientos registrados todavía.
                </div>
            @endforelse

            <div class="mt-4">
                @if($logs->hasPages())
                    <div class="mt-6 pt-4 border-t">
                        {{ $logs->links() }}
                    </div>
                @endif
            </div>
        </div>

    </div>
</x-layouts.vigia>