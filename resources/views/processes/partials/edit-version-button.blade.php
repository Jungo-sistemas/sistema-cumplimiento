{{--
    Botón "Editar" de la versión actual — 4 estados: no editable (no es .docx), bloqueado por
    otro usuario, borrador propio disponible, o libre para editar. Requiere $regulation,
    $currentVersion, $editLock (mismo shape que RegulationController::buildEditLock()).
--}}
@if($currentVersion && $regulation->isEditableBy(auth()->user()))
    @php
        $cvExt   = strtolower(pathinfo($currentVersion->original_name ?? $currentVersion->file_path, PATHINFO_EXTENSION));
        $canEdit = $cvExt === 'docx' && ! $regulation->is_legacy;
    @endphp
    @if($regulation->is_legacy)
        <span title="Documento antiguo — actualízalo con el wizard o subiendo una nueva versión para poder editarlo aquí"
              class="px-3 py-2 rounded-md border font-semibold text-sm bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed select-none">
            Editar
        </span>
    @elseif(! $canEdit)
        <span title="Solo se pueden editar archivos .docx — este es .{{ $cvExt ?: 'desconocido' }}"
              class="px-3 py-2 rounded-md border font-semibold text-sm bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed select-none">
            Editar
        </span>
    @elseif($editLock && ! $editLock['by_me'])
        <span title="Editando: {{ $editLock['user_name'] }} · Bloqueo expira a las {{ $editLock['expires'] }}"
              class="inline-flex items-center gap-1 px-3 py-2 rounded-md border font-semibold text-sm bg-orange-50 text-orange-600 border-orange-300 cursor-not-allowed select-none">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
            Editando
        </span>
    @elseif($editLock && $editLock['by_me'] && $editLock['has_draft'])
        <a href="{{ route('regulation-versions.edit', $currentVersion) }}"
           title="Tienes un borrador guardado a las {{ $editLock['draft_at'] }}. Haz clic para retomarlo."
           class="inline-flex items-center gap-1 px-3 py-2 rounded-md border font-semibold text-sm bg-yellow-50 text-yellow-700 border-yellow-400 hover:bg-yellow-100">
            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Borrador
        </a>
    @else
        <a href="{{ route('regulation-versions.edit', $currentVersion) }}"
           class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-[#1A428A] border-[#1A428A] hover:bg-blue-50">
            Editar
        </a>
    @endif
@elseif($currentVersion && $regulation->hasActiveApprovalFlow() && (auth()->user()->isAdmin() || $regulation->isResponsable(auth()->user())))
    <span title="No se puede editar mientras el documento está en proceso de aprobación"
          class="px-3 py-2 rounded-md border font-semibold text-sm bg-gray-50 text-gray-400 border-gray-200 cursor-not-allowed select-none">
        En revisión
    </span>
@elseif($currentVersion && $regulation->canRequestAccessFrom(auth()->user()))
    <form method="POST" action="{{ route('processes.requestAccess', $regulation) }}"
          onsubmit="return confirm('¿Enviar solicitud de acceso a los administradores de Procesos?');">
        @csrf
        <button type="submit"
                title="No eres responsable de este reglamento — pide que te agreguen para poder editarlo"
                class="px-3 py-2 rounded-md border font-semibold text-sm bg-white text-orange-600 border-orange-400 hover:bg-orange-50">
            Solicitar edición
        </button>
    </form>
@endif
