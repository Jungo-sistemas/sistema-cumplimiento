@php
    use App\Models\AuditFinding;

    $audit = $finding->audit;
    $regulation = $audit->regulation;
    $maxFiles = AuditFinding::MAX_FILES;
    $severity = $finding->severity ?? 'no_critico';
    $currentStage = $finding->stage();
    $closed = $finding->status === AuditFinding::STATUS_CLOSED;

    // Los 5 pasos de la lámina del cliente; "documentar" ya ocurrió cuando el hallazgo existe.
    $steps = [
        ['key' => 'documentar', 'title' => 'Documentar',              'who' => 'Auditor'],
        ['key' => 'causa',      'title' => 'Causa raíz y acciones',   'who' => 'Tú'],
        ['key' => 'validar',    'title' => 'Validar',                 'who' => 'Auditor'],
        ['key' => 'corregir',   'title' => 'Corregir y evidencia',    'who' => 'Tú'],
        ['key' => 'verificar',  'title' => 'Verificar y cerrar',      'who' => 'Auditor'],
    ];
    $order = array_column($steps, 'key');
    $currentIndex = $closed ? count($steps) : array_search($currentStage, $order, true);

    $fileAccept = '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.heic,.heif,image/*';
@endphp
<x-layouts.vigia :title="'Hallazgo: ' . $finding->point">
    <x-slot name="breadcrumb">
        <a href="{{ route('mi-espacio.findings') }}" class="text-gray-600 hover:underline">Mis hallazgos</a>
        <span class="text-gray-400">›</span>
        <span class="font-medium text-gray-700">{{ \Illuminate\Support\Str::limit($finding->point, 60) }}</span>
    </x-slot>

    {{-- Encabezado del hallazgo --}}
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6">
        <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    @if($regulation?->code)
                        <span class="rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600">{{ $regulation->code }}</span>
                    @endif
                    @include('mi-espacio.partials.finding-severity', ['finding' => $finding])
                </div>
                <h1 class="mt-2 text-2xl font-extrabold tracking-tight text-brand-ink">{{ $finding->point }}</h1>
                <p class="mt-3 whitespace-pre-line text-[15px] text-gray-700">{{ $finding->description }}</p>

                <dl class="mt-4 grid grid-cols-1 gap-x-8 gap-y-1 text-sm text-gray-600 sm:grid-cols-2">
                    <div><dt class="inline text-gray-400">Proceso:</dt> <dd class="inline">{{ $regulation?->name ?? '—' }}</dd></div>
                    <div><dt class="inline text-gray-400">Empresa:</dt> <dd class="inline">{{ $audit->company?->procesos_name ?? $audit->company?->name ?? '—' }}</dd></div>
                    <div><dt class="inline text-gray-400">Auditoría:</dt> <dd class="inline">{{ $audit->scheduledLabel() ?? ucfirst($audit->plannedMonthLabel()) }}</dd></div>
                    <div>
                        <dt class="inline text-gray-400">Auditor:</dt>
                        <dd class="inline">{{ $audit->auditor?->name ?? '—' }}
                            @if($audit->auditor?->email)
                                <a href="mailto:{{ $audit->auditor->email }}" class="text-brand hover:underline">{{ $audit->auditor->email }}</a>
                            @endif
                        </dd>
                    </div>
                    @unless($isResponsible)
                        <div><dt class="inline text-gray-400">Responsable:</dt> <dd class="inline">{{ $finding->responsible?->name ?? '—' }}</dd></div>
                    @endunless
                </dl>
            </div>

            <div class="shrink-0 md:text-right">
                @include('mi-espacio.partials.finding-status', ['finding' => $finding])
                <p class="mt-2 text-sm {{ $finding->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">
                    @if($closed)
                        Cerrado el {{ $finding->closed_at?->format('d/m/Y') }}
                    @elseif($finding->due_at)
                        {{ $finding->needsAuditee() ? 'Fecha límite' : 'El auditor responde a más tardar' }}: {{ $finding->due_at->format('d/m/Y') }}
                        @if($finding->isOverdue()) · Vencido @endif
                    @endif
                </p>
                @if($regulation?->publishedVersion)
                    <a href="{{ route('mi-espacio.documents.show', $regulation) }}"
                       class="mt-3 inline-flex items-center gap-2 rounded-lg bg-brand-50 px-4 py-2 text-sm font-semibold text-brand hover:bg-brand-100">
                        <x-icon name="document" class="h-4 w-4" />Ver proceso
                    </a>
                @endif
            </div>
        </div>
    </div>

    {{-- Crítico: contención inmediata --}}
    @if($finding->isCritical() && ! $closed)
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-sm font-semibold text-red-800">Hallazgo crítico: pone en riesgo la operación o a una persona</p>
            <p class="mt-1 text-[15px] text-red-900">
                Aplica una contención inmediata (en las primeras {{ config('auditorias.contencion_critico_horas', 24) }} horas) mientras defines la causa raíz.
                Todo el hallazgo se cierra en {{ AuditFinding::totalDays('critico') }} días hábiles.
            </p>
        </div>
    @endif

    {{-- Etapas y plazos --}}
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Etapas</h2>
        <ol class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-5">
            @foreach($steps as $i => $step)
                @php
                    $done = $i < $currentIndex;
                    $current = $i === $currentIndex;
                    $days = $step['key'] === 'documentar' ? null : AuditFinding::stageDays($severity, $step['key']);
                @endphp
                <li class="rounded-xl border p-3 {{ $current ? 'border-brand bg-brand-50' : ($done ? 'border-green-200 bg-green-50' : 'border-gray-200') }}">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $current ? 'bg-brand text-white' : ($done ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-500') }}">{{ $i + 1 }}</span>
                        <span class="text-sm font-semibold {{ $current ? 'text-brand-ink' : 'text-gray-800' }}">{{ $step['title'] }}</span>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ $step['who'] }}@if($days) · {{ $days }} {{ $days === 1 ? 'día hábil' : 'días hábiles' }}@endif
                    </p>
                </li>
            @endforeach
        </ol>
    </div>

    {{-- Lo que devolvió el auditor --}}
    @if($lastReject)
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-sm font-semibold text-red-800">
                {{ $lastReject->isRootCause() ? 'El auditor devolvió tu causa raíz con observaciones' : 'El auditor rechazó tu evidencia' }}
                ({{ $lastReject->reviewed_at?->format('d/m/Y') }})
            </p>
            <p class="mt-1 whitespace-pre-line text-[15px] text-red-900">{{ $lastReject->review_comment }}</p>
            <p class="mt-2 text-sm text-red-700">
                {{ $lastReject->isRootCause() ? 'Corrige el análisis o las acciones y vuelve a enviarlos.' : 'Corrige lo que indica y sube una nueva evidencia.' }}
            </p>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
        </div>
    @endif

    {{-- Paso 2: causa raíz y acciones --}}
    @if($canSendRootCause)
        @php $previous = $lastReject?->isRootCause() ? $lastReject : null; @endphp
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6"
             x-data="{ files: [], max: {{ $maxFiles }}, pick(e) { this.files = Array.from(e.target.files); }, get tooMany() { return this.files.length > this.max; } }">
            <h2 class="text-lg font-semibold text-gray-900">{{ $previous ? 'Corregir causa raíz y acciones' : 'Causa raíz y acciones' }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                Explica por qué pasó (no solo qué pasó) y qué vas a hacer para corregirlo. Puedes apoyarte en los responsables del procedimiento.
                Cuando el auditor lo apruebe, empieza el seguimiento: ejecutar las acciones y subir la evidencia.
            </p>

            <form method="POST" action="{{ route('mi-espacio.findings.root-cause', $finding) }}" enctype="multipart/form-data"
                  class="mt-4 space-y-4" @submit="if (tooMany) { $event.preventDefault(); return; } $el.querySelector('[type=submit]').disabled = true">
                @csrf
                <div>
                    <label for="root_cause" class="block text-sm font-medium text-gray-700">Causa raíz</label>
                    <textarea id="root_cause" name="root_cause" rows="4" maxlength="5000" required
                              class="mt-1 w-full rounded-xl border-gray-200 text-[15px] focus:border-brand focus:ring-brand"
                              placeholder="¿Por qué ocurrió? Por ejemplo: el supervisor no sabía que debía firmar antes de iniciar.">{{ old('root_cause', $previous?->root_cause) }}</textarea>
                </div>
                <div>
                    <label for="actions" class="block text-sm font-medium text-gray-700">Acciones para corregirlo</label>
                    <textarea id="actions" name="actions" rows="4" maxlength="5000" required
                              class="mt-1 w-full rounded-xl border-gray-200 text-[15px] focus:border-brand focus:ring-brand"
                              placeholder="Qué se va a hacer, quién y para cuándo.">{{ old('actions', $previous?->actions) }}</textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700">Archivos de apoyo (opcional, hasta {{ $maxFiles }})</label>
                    <input type="file" name="files[]" multiple @change="pick" accept="{{ $fileAccept }}"
                           class="mt-1 block w-full text-sm text-gray-600 file:mr-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-4 file:py-2 file:font-semibold file:text-brand hover:file:bg-brand-100">
                    <p x-show="tooMany" x-cloak class="mt-1 text-sm font-semibold text-red-600">
                        Elegiste <span x-text="files.length"></span> archivos; el máximo es {{ $maxFiles }}.
                    </p>
                </div>
                <button type="submit" :disabled="tooMany"
                        class="inline-flex rounded-lg bg-brand px-5 py-3 text-[15px] font-semibold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50">
                    Enviar al auditor
                </button>
            </form>
        </div>
    @endif

    {{-- Seguimiento: el plan aprobado, para tenerlo a la mano --}}
    @if($approvedRootCause && ! $closed)
        <div class="mb-6 rounded-2xl border border-green-200 bg-green-50 p-5">
            <p class="text-sm font-semibold text-green-800">Plan aprobado por el auditor ({{ $approvedRootCause->reviewed_at?->format('d/m/Y') }})</p>
            <p class="mt-2 text-sm text-green-900"><span class="font-semibold">Causa raíz:</span> {{ $approvedRootCause->root_cause }}</p>
            <p class="mt-1 whitespace-pre-line text-sm text-green-900"><span class="font-semibold">Acciones:</span> {{ $approvedRootCause->actions }}</p>
        </div>
    @endif

    {{-- Paso 4: evidencia del seguimiento --}}
    @if($canSubmit)
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6"
             x-data="{ files: [], max: {{ $maxFiles }}, pick(e) { this.files = Array.from(e.target.files); }, get tooMany() { return this.files.length > this.max; } }">
            <h2 class="text-lg font-semibold text-gray-900">{{ $lastReject ? 'Subir nueva evidencia' : 'Subir evidencia del seguimiento' }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                Muestra que las acciones se aplicaron. Hasta {{ $maxFiles }} archivos: PDF, Word, Excel, PowerPoint o fotos (JPG, PNG, HEIC). Máximo 20 MB cada uno.
            </p>

            <form method="POST" action="{{ route('mi-espacio.findings.evidence', $finding) }}" enctype="multipart/form-data"
                  class="mt-4 space-y-4" @submit="if (tooMany || ! files.length) { $event.preventDefault(); return; } $el.querySelector('[type=submit]').disabled = true">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-8 text-center hover:border-brand hover:bg-brand-50/40">
                    <x-icon name="document" class="h-6 w-6 text-brand" />
                    <span class="mt-2 text-[15px] font-semibold text-brand">Elegir archivos o tomar foto</span>
                    <span class="text-xs text-gray-500">Puedes elegir varios a la vez</span>
                    <input type="file" name="files[]" multiple required class="sr-only" @change="pick" accept="{{ $fileAccept }}">
                </label>

                <template x-if="files.length">
                    <ul class="divide-y divide-gray-100 rounded-xl border border-gray-200 text-sm">
                        <template x-for="f in files" :key="f.name + f.size">
                            <li class="flex items-center justify-between gap-3 px-4 py-2">
                                <span class="truncate text-gray-800" x-text="f.name"></span>
                                <span class="shrink-0 text-xs text-gray-400" x-text="(f.size / 1048576).toFixed(1) + ' MB'"></span>
                            </li>
                        </template>
                    </ul>
                </template>
                <p x-show="tooMany" x-cloak class="text-sm font-semibold text-red-600">
                    Elegiste <span x-text="files.length"></span> archivos; el máximo es {{ $maxFiles }}. Vuelve a elegirlos.
                </p>

                <div>
                    <label for="comment" class="block text-sm font-medium text-gray-700">Comentario para el auditor (opcional)</label>
                    <textarea id="comment" name="comment" rows="3" maxlength="2000"
                              class="mt-1 w-full rounded-xl border-gray-200 text-[15px] focus:border-brand focus:ring-brand"
                              placeholder="Explica brevemente qué estás mostrando...">{{ old('comment') }}</textarea>
                </div>

                <button type="submit" :disabled="tooMany || ! files.length"
                        class="inline-flex rounded-lg bg-brand px-5 py-3 text-[15px] font-semibold text-white hover:bg-brand-dark disabled:cursor-not-allowed disabled:opacity-50">
                    Enviar evidencia
                </button>
            </form>
        </div>
    @endif

    @if(in_array($finding->status, AuditFinding::NEEDS_AUDITOR, true))
        <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-[15px] text-blue-900">
            {{ $finding->status === AuditFinding::STATUS_ROOT_CAUSE_REVIEW
                ? 'Tu causa raíz y acciones están en revisión del auditor. Te avisaremos si las aprueba o tiene observaciones.'
                : 'Tu evidencia está en revisión del auditor. Te avisaremos cuando confirme que la acción se implementó.' }}
        </div>
    @endif

    {{-- Entregas --}}
    <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Entregas</h2>

        @forelse($finding->submissions as $submission)
            <div class="mt-4 rounded-xl border border-gray-100 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600">
                        <span class="font-semibold text-gray-800">{{ $submission->kindLabel() }}</span> ·
                        {{ $submission->created_at->format('d/m/Y H:i') }} · {{ $submission->user?->name ?? '—' }}
                    </p>
                    @php
                        [$label, $classes] = match ($submission->status) {
                            'aceptada'  => [$submission->isRootCause() ? 'Aprobada' : 'Verificada', 'bg-green-50 text-green-700'],
                            'rechazada' => [$submission->isRootCause() ? 'Devuelta' : 'Rechazada', 'bg-red-50 text-red-700'],
                            default     => ['En revisión', 'bg-blue-50 text-blue-700'],
                        };
                    @endphp
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $classes }}">{{ $label }}</span>
                </div>

                @if($submission->isRootCause())
                    <p class="mt-2 text-[15px] text-gray-700"><span class="font-semibold">Causa raíz:</span> {{ $submission->root_cause }}</p>
                    <p class="mt-1 whitespace-pre-line text-[15px] text-gray-700"><span class="font-semibold">Acciones:</span> {{ $submission->actions }}</p>
                @elseif($submission->comment)
                    <p class="mt-2 whitespace-pre-line text-[15px] text-gray-700">{{ $submission->comment }}</p>
                @endif

                @if($submission->files->isNotEmpty())
                    <ul class="mt-3 flex flex-wrap gap-2">
                        @foreach($submission->files as $file)
                            <li>
                                <a href="{{ route('mi-espacio.findings.file', $file) }}"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-gray-700 hover:border-brand hover:text-brand">
                                    <x-icon name="document" class="h-4 w-4" />{{ $file->original_name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if($submission->review_comment)
                    <p class="mt-3 rounded-lg px-3 py-2 text-sm {{ $submission->status === 'rechazada' ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800' }}">
                        <span class="font-semibold">{{ $submission->status === 'rechazada' ? 'Observaciones del auditor' : 'Comentario del auditor' }}:</span>
                        {{ $submission->review_comment }}
                    </p>
                @endif
            </div>
        @empty
            <p class="mt-3 text-[15px] text-gray-500">Todavía no hay entregas.</p>
        @endforelse
    </div>

    {{-- Bitácora --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Historial</h2>
        <ol class="mt-4 space-y-2">
            @foreach($finding->events as $event)
                <li class="text-sm text-gray-700">
                    <span class="text-gray-400">{{ $event->created_at->format('d/m/Y H:i') }}</span> ·
                    <span class="font-semibold">{{ $event->user?->name ?? 'Sistema' }}:</span>
                    {{ $event->label() }}@if($event->comment) — <span class="text-gray-600">{{ $event->comment }}</span>@endif
                </li>
            @endforeach
        </ol>
    </div>
</x-layouts.vigia>
