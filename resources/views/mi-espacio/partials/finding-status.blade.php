{{-- Etiqueta de estado de un hallazgo, vista del responsable. $finding --}}
@php
    $badge = match ($finding->status) {
        \App\Models\AuditFinding::STATUS_ROOT_CAUSE_PENDING  => ['Escribe la causa raíz', 'bg-amber-50 text-amber-700', 'bg-amber-400'],
        \App\Models\AuditFinding::STATUS_ROOT_CAUSE_REJECTED => ['Causa raíz devuelta', 'bg-red-50 text-red-700', 'bg-red-500'],
        \App\Models\AuditFinding::STATUS_ROOT_CAUSE_REVIEW   => ['Causa raíz en revisión', 'bg-blue-50 text-blue-700', 'bg-blue-500'],
        \App\Models\AuditFinding::STATUS_EVIDENCE_PENDING    => ['Seguimiento: sube evidencia', 'bg-amber-50 text-amber-700', 'bg-amber-400'],
        \App\Models\AuditFinding::STATUS_EVIDENCE_REJECTED   => ['Evidencia rechazada', 'bg-red-50 text-red-700', 'bg-red-500'],
        \App\Models\AuditFinding::STATUS_EVIDENCE_REVIEW     => ['Evidencia en revisión', 'bg-blue-50 text-blue-700', 'bg-blue-500'],
        default                                               => ['Cerrado', 'bg-green-50 text-green-700', 'bg-green-500'],
    };
@endphp
<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold {{ $badge[1] }}">
    <span class="h-2 w-2 rounded-full {{ $badge[2] }}"></span>{{ $badge[0] }}
</span>
