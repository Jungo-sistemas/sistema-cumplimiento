<?php

namespace App\Models;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class Regulation extends Model
{
    use HasFactory, SoftDeletes;

    public const DOCUMENT_TYPES = [
        'Procedimiento',
        'Política',
        'Instructivo',
        'Formato',
        'Registro',
    ];

    public const IMPACT_LEVELS = [
        'alto'       => 'Alto',
        'medio_alto' => 'Medio - Alto',
        'medio'      => 'Medio',
        'bajo'       => 'Bajo',
    ];

    // Impacto para definir después la periodicidad de evaluación del proceso. No tiene que ver
    // con IMPACT_LEVELS (esos eligen el flujo de aprobación).
    public const EVALUATION_IMPACTS = [
        'bajo'  => 'Bajo',
        'medio' => 'Medio',
        'alto'  => 'Alto',
    ];

    public const APPROVAL_STATUSES = [
        'pending_review'        => 'En revisión',
        'pending_authorization' => 'En autorización',
        'approved'              => 'Aprobado',
        'rejected'              => 'Rechazado',
        // Documento antiguo cargado solo de referencia, que se reemplazará más adelante por una
        // versión nueva — a propósito NO pasa por ningún flujo de aprobación (ver
        // ImportLegacyRegulations) ni genera recordatorios, pero tampoco se muestra como "Aprobado"
        // porque no lo está.
        'outdated'              => 'Desactualizado',
    ];

    protected $fillable = [
        'group_id',
        'company_id',
        'process_type_id',
        'document_type',
        'is_annex',
        'is_legacy',
        'code',
        'name',
        'details',
        'previous_details',
        'is_active',
        'created_by',
        'impact_level',
        'evaluation_impact',
        'approval_status',
        'direct_approved_by',
        'direct_approved_at',
        'flow_locked',
        'flow_user_map',
        'public_share_token',
        'deleted_by',
        'permanently_delete_at',
        'change_summary_path',
        'change_summary_original_name',
        'change_summary_mime_type',
        'change_summary_uploaded_by',
        'change_summary_uploaded_at',
        'obsoleted_at',
        'obsoleted_by',
    ];

    protected $casts = [
        'details'                     => 'array',
        'previous_details'            => 'array',
        'flow_user_map'               => 'array',
        'is_annex'                    => 'boolean',
        'is_legacy'                   => 'boolean',
        'permanently_delete_at'       => 'datetime',
        'change_summary_uploaded_at'  => 'datetime',
        'obsoleted_at'                => 'datetime',
        'direct_approved_at'          => 'datetime',
    ];

    protected static function booted(): void
    {
        // Procesos confidenciales (los de la empresa DIRECCIÓN, Company::is_restricted): para quien no puede verlos no existen —
        // ni en listados, tableros, reportes o menciones, ni abriéndolos por URL (404). Sin usuario
        // (comandos, colas, enlace público sin sesión) no se filtra nada.
        static::addGlobalScope('restricted', function (Builder $query) {
            if (Auth::user()?->canSeeRestrictedProcesses() === false) {
                $query->whereNotIn($query->qualifyColumn('company_id'), DB::table('companies')->select('id')->where('is_restricted', true));
            }
        });

        // Tampoco puede crear uno ni mover un proceso a la carpeta confidencial.
        static::saving(function (Regulation $regulation) {
            if (Auth::user()?->canSeeRestrictedProcesses() === false && $regulation->isDirty('company_id') && $regulation->isRestricted()) {
                throw new AuthorizationException('No tienes acceso a esta carpeta.');
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function processType()
    {
        return $this->belongsTo(ProcessType::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function changeSummaryUploader()
    {
        return $this->belongsTo(User::class, 'change_summary_uploaded_by');
    }

    public function obsoletedBy()
    {
        return $this->belongsTo(User::class, 'obsoleted_by');
    }

    public function directApprovedBy()
    {
        return $this->belongsTo(User::class, 'direct_approved_by');
    }

    /**
     * Guarda (o reemplaza) el documento con la síntesis de cambios que ven los aprobadores. Es uno
     * por proceso, no por versión: se adjunta al establecer el flujo y sirve para las aprobaciones
     * que vengan después hasta que se adjunte otro.
     */
    public function replaceChangeSummary(\Illuminate\Http\UploadedFile $file, User $uploader): void
    {
        $path = $file->store("regulations/{$this->company_id}/{$this->id}/change-summary", 'private');

        if ($old = $this->change_summary_path) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete([$old, $old . '.preview.pdf']);
        }

        $this->update([
            'change_summary_path'          => $path,
            'change_summary_original_name' => $file->getClientOriginalName(),
            'change_summary_mime_type'     => $file->getMimeType(),
            'change_summary_uploaded_by'   => $uploader->id,
            'change_summary_uploaded_at'   => now(),
        ]);
    }

    public const MAX_CHANGE_EVIDENCES = 3;

    public function changeEvidences()
    {
        return $this->hasMany(RegulationChangeEvidence::class)->orderBy('id');
    }

    /** Reglas para las evidencias de apoyo que se suben junto con la síntesis. */
    public static function changeEvidenceRules(): array
    {
        return [
            'change_evidences'   => ['nullable', 'array', 'max:' . self::MAX_CHANGE_EVIDENCES],
            'change_evidences.*' => ['file', 'mimes:pdf,jpg,jpeg,png', 'max:20480'],
            'remove_evidences'   => ['nullable', 'array'],
            'remove_evidences.*' => ['integer'],
        ];
    }

    public static function changeEvidenceMessages(): array
    {
        return [
            'change_evidences.max'     => 'Solo se permiten ' . self::MAX_CHANGE_EVIDENCES . ' evidencias de apoyo.',
            'change_evidences.*.mimes' => 'Las evidencias deben ser imagen (JPG/PNG) o PDF.',
            'change_evidences.*.max'   => 'Cada evidencia puede pesar máximo 20 MB.',
        ];
    }

    /**
     * Quita las evidencias indicadas y agrega las nuevas. Devuelve un mensaje de error (sin tocar
     * nada) si el total quedaría arriba del máximo.
     */
    public function syncChangeEvidences(array $newFiles, array $removeIds, User $uploader): ?string
    {
        $toRemove = $this->changeEvidences()->whereIn('id', $removeIds)->get();
        $total    = $this->changeEvidences()->count() - $toRemove->count() + count($newFiles);

        if ($total > self::MAX_CHANGE_EVIDENCES) {
            return 'Solo se permiten ' . self::MAX_CHANGE_EVIDENCES . ' evidencias de apoyo; quita alguna antes de agregar otra.';
        }

        foreach ($toRemove as $evidence) {
            \Illuminate\Support\Facades\Storage::disk('private')->delete($evidence->path);
            $evidence->delete();
        }

        foreach ($newFiles as $file) {
            $this->changeEvidences()->create([
                'path'          => $file->store("regulations/{$this->company_id}/{$this->id}/change-evidence", 'private'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getMimeType(),
                'uploaded_by'   => $uploader->id,
            ]);
        }

        return null;
    }

    public function responsables()
    {
        return $this->belongsToMany(User::class, 'regulation_responsables')->withTimestamps();
    }

    public function versions()
    {
        return $this->hasMany(RegulationVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->hasOne(RegulationVersion::class)
            ->where('is_current', true)
            ->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Green = active and valid
     * Yellow = near expiry (≤ 60 days) or no version yet
     * Red    = expired
     */
    public function statusColor(): string
    {
        $version = $this->currentVersion;

        // Sin versión, o con versión pero sin vigencia asignada todavía (se asigna solo al
        // aprobarse — ver ApprovalFlowService::processApproval()): no se puede saber si está
        // vigente o no, así que nunca cae en "Vigente" (verde) por default.
        if ($this->approval_status === 'outdated') {
            return 'gray';
        }

        if (! $version || ! $version->valid_until) {
            return $this->approval_status === 'approved' ? 'blue' : 'yellow';
        }

        if ($version->valid_until->isPast()) {
            return 'red';
        }

        if ($version->valid_until->lte(now()->addDays(60))) {
            return 'yellow';
        }

        return 'green';
    }

    public function statusLabel(): string
    {
        if ($this->approval_status === 'outdated') {
            return 'Desactualizado';
        }

        $version = $this->currentVersion;

        if (! $version || ! $version->valid_until) {
            return $this->approval_status === 'approved' ? 'Aprobado' : 'Pendiente de aprobación';
        }

        return match ($this->statusColor()) {
            'red'    => 'Vencido',
            'yellow' => 'Por vencer',
            default  => 'Vigente',
        };
    }

    public function daysUntilExpiry(): ?int
    {
        $version = $this->currentVersion;

        if (! $version?->valid_until) {
            return null;
        }

        return (int) now()->diffInDays($version->valid_until, false);
    }

    /*
    |--------------------------------------------------------------------------
    | Approval relationships
    |--------------------------------------------------------------------------
    */

    public function annexes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(
            Regulation::class,
            'regulation_annexes',
            'regulation_id',
            'annexed_regulation_id'
        )->withoutTrashed()->select(['regulations.id', 'regulations.code', 'regulations.name']);
    }

    public function approvals()
    {
        return $this->hasMany(RegulationApproval::class)->orderBy('step_number')->orderBy('id');
    }

    public function approvalStep(int $step)
    {
        return $this->hasMany(RegulationApproval::class)->where('step_number', $step);
    }

    public function pendingApprovals()
    {
        return $this->hasMany(RegulationApproval::class)->where('status', 'pending');
    }

    /*
    |--------------------------------------------------------------------------
    | Approval helpers
    |--------------------------------------------------------------------------
    */

    public function isFullyApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->approval_status === 'rejected';
    }

    /**
     * A medio flujo: ya se envió a aprobar pero todavía no hay una decisión final (ni aprobado
     * ni rechazado). Mientras esto sea true, isEditableBy() bloquea cualquier edición — nadie
     * debe alterar el contenido que los aprobadores están revisando en este momento.
     */
    public function hasActiveApprovalFlow(): bool
    {
        return in_array($this->approval_status, ['pending_review', 'pending_authorization'], true);
    }

    public function latestRejectionComment(): ?string
    {
        return $this->approvals()
            ->where('status', 'rejected')
            ->latest('decided_at')
            ->first()
            ?->comments;
    }

    /**
     * Etiqueta "Desactualizado": documento antiguo de referencia que todavía se tiene que
     * actualizar. Solo se puede ver — nadie lo descarga (ni la versión actual ni el historial), y
     * los operativos solo pueden actualizarlo contestando las preguntas del wizard.
     */
    public function isOutdated(): bool
    {
        return $this->approval_status === 'outdated';
    }

    public function allowsDownload(): bool
    {
        return ! $this->isOutdated();
    }

    /**
     * En un documento desactualizado un operativo (responsable) solo edita con el wizard de
     * preguntas: sin editor de texto en línea ni "Info básica". Los admins siguen igual.
     */
    public function onlyWizardEditFor(User $user): bool
    {
        return $this->isOutdated() && ! $user->isAdmin();
    }

    public function approvalStatusLabel(): string
    {
        return self::APPROVAL_STATUSES[$this->approval_status] ?? $this->approval_status;
    }

    public function approvalStatusColor(): string
    {
        return match ($this->approval_status) {
            'approved'              => 'green',
            'rejected'              => 'red',
            'pending_authorization' => 'blue',
            'outdated'              => 'gray',
            default                 => 'yellow',
        };
    }

    public function impactLevelLabel(): string
    {
        return self::IMPACT_LEVELS[$this->impact_level] ?? $this->impact_level ?? '—';
    }

    public function isResponsable(User $user): bool
    {
        return $this->responsables->contains('id', $user->id);
    }

    /**
     * Regla de edición del módulo de Procesos: los admins pueden editar cualquier reglamento al
     * que tengan acceso por empresa/grupo; los operativos SOLO los reglamentos de los que son
     * responsables (nunca por defecto). Ajustar el flujo de aprobación (setFlow) es aparte y sigue
     * siendo exclusivo de admins sin excepción — este método no aplica ahí.
     */
    public function isEditableBy(User $user): bool
    {
        // A medio flujo, nadie edita — ni siquiera un admin — hasta que se apruebe o rechace:
        // el contenido que se editaría es justo el que los aprobadores están revisando ahora.
        if ($this->hasActiveApprovalFlow()) {
            return false;
        }

        if (! $user->canAccessCompany($this->company)) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if ($user->isOperative()) {
            return $this->isResponsable($user);
        }

        return false;
    }

    /**
     * Un operativo sin acceso de edición (no es responsable) puede solicitar que lo agreguen como
     * responsable — así es como pide acceso en vez de editar directamente. No aplica a admins (ya
     * pueden editar) ni a operativos fuera de la empresa/grupo del reglamento.
     */
    public function canRequestAccessFrom(User $user): bool
    {
        return $user->isOperative()
            && $user->canAccessCompany($this->company)
            && ! $this->isResponsable($user);
    }

    /**
     * Token permanente (sin expiración, no atado a un usuario) para el enlace público — "cualquiera
     * con el enlace puede ver" — usado por el QR para pared. Se genera solo una vez y persiste
     * mientras exista el reglamento, así el mismo QR impreso sigue funcionando siempre.
     */
    /** Está en la carpeta confidencial DIRECCIÓN (solo Consejo y admins de solo Procesos). */
    public function isRestricted(): bool
    {
        return $this->company_id !== null && (bool) DB::table('companies')
            ->where('id', $this->company_id)
            ->value('is_restricted');
    }

    public function ensurePublicShareToken(): string
    {
        if (! $this->public_share_token) {
            $this->update(['public_share_token' => Str::random(48)]);
        }

        return $this->public_share_token;
    }
}
