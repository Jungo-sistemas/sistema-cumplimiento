@php
    $audit = $finding->audit;
    $regulation = $audit->regulation;
    $maxFiles = \App\Models\AuditFinding::MAX_FILES;
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
                    @if($finding->severityLabel())
                        <span class="rounded bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand">Severidad {{ $finding->severityLabel() }}</span>
                    @endif
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
                </dl>
            </div>

            <div class="shrink-0 md:text-right">
                @include('mi-espacio.partials.finding-status', ['finding' => $finding])
                <p class="mt-2 text-sm {{ $finding->isOverdue() ? 'font-semibold text-red-600' : 'text-gray-600' }}">
                    @if($finding->status === \App\Models\AuditFinding::STATUS_CLOSED)
                        Cerrado el {{ $finding->closed_at?->format('d/m/Y') }}
                    @else
                        {{ $finding->due_at ? 'Fecha límite: ' . $finding->due_at->format('d/m/Y') : 'Fecha límite por definir' }}
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

    {{-- Motivo del último rechazo --}}
    @if($lastReject)
        <div class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-5">
            <p class="text-sm font-semibold text-red-800">El auditor rechazó tu evidencia del {{ $lastReject->created_at->format('d/m/Y') }}</p>
            <p class="mt-1 whitespace-pre-line text-[15px] text-red-900">{{ $lastReject->review_comment }}</p>
            <p class="mt-2 text-sm text-red-700">Corrige lo que indica y sube una nueva evidencia.</p>
        </div>
    @endif

    {{-- Subir evidencia --}}
    @if($canSubmit)
        <div class="mb-6 rounded-2xl border border-gray-200 bg-white p-6"
             x-data="{
                files: [],
                max: {{ $maxFiles }},
                pick(e) { this.files = Array.from(e.target.files); },
                get tooMany() { return this.files.length > this.max; },
             }">
            <h2 class="text-lg font-semibold text-gray-900">{{ $lastReject ? 'Subir nueva evidencia' : 'Subir evidencia' }}</h2>
            <p class="mt-1 text-sm text-gray-500">
                Hasta {{ $maxFiles }} archivos: PDF, Word, Excel, PowerPoint o fotos (JPG, PNG, HEIC). Máximo 20 MB cada uno.
            </p>

            @if($errors->any())
                <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('mi-espacio.findings.evidence', $finding) }}" enctype="multipart/form-data"
                  class="mt-4 space-y-4" @submit="if (tooMany || ! files.length) { $event.preventDefault(); return; } $el.querySelector('[type=submit]').disabled = true">
                @csrf
                <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-gray-300 px-6 py-8 text-center hover:border-brand hover:bg-brand-50/40">
                    <x-icon name="document" class="h-6 w-6 text-brand" />
                    <span class="mt-2 text-[15px] font-semibold text-brand">Elegir archivos o tomar foto</span>
                    <span class="text-xs text-gray-500">Puedes elegir varios a la vez</span>
                    <input type="file" name="files[]" multiple required class="sr-only" @change="pick"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.webp,.heic,.heif,image/*">
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
    @elseif($finding->status === \App\Models\AuditFinding::STATUS_IN_REVIEW)
        <div class="mb-6 rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 text-[15px] text-blue-900">
            Tu evidencia está en revisión del auditor. Te avisaremos cuando la acepte o si necesita algo más.
        </div>
    @endif

    {{-- Historial de entregas --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6">
        <h2 class="text-lg font-semibold text-gray-900">Evidencias enviadas</h2>

        @forelse($finding->submissions as $submission)
            <div class="mt-4 rounded-xl border border-gray-100 p-4">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-gray-600">
                        {{ $submission->created_at->format('d/m/Y H:i') }} · {{ $submission->user?->name ?? '—' }}
                    </p>
                    @php
                        [$label, $classes] = match ($submission->status) {
                            'aceptada'  => ['Aceptada', 'bg-green-50 text-green-700'],
                            'rechazada' => ['Rechazada', 'bg-red-50 text-red-700'],
                            default     => ['En revisión', 'bg-blue-50 text-blue-700'],
                        };
                    @endphp
                    <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $classes }}">{{ $label }}</span>
                </div>

                @if($submission->comment)
                    <p class="mt-2 whitespace-pre-line text-[15px] text-gray-700">{{ $submission->comment }}</p>
                @endif

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

                @if($submission->review_comment)
                    <p class="mt-3 rounded-lg px-3 py-2 text-sm {{ $submission->status === 'rechazada' ? 'bg-red-50 text-red-800' : 'bg-green-50 text-green-800' }}">
                        <span class="font-semibold">{{ $submission->status === 'rechazada' ? 'Motivo del rechazo' : 'Comentario del auditor' }}:</span>
                        {{ $submission->review_comment }}
                    </p>
                @endif
            </div>
        @empty
            <p class="mt-3 text-[15px] text-gray-500">Todavía no has enviado evidencia.</p>
        @endforelse
    </div>
</x-layouts.vigia>
