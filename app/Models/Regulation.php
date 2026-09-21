<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
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

    public const APPROVAL_STATUSES = [
        'pending_review'        => 'En revisión',
        'pending_authorization' => 'En autorización',
        'approved'              => 'Aprobado',
        'rejected'              => 'Rechazado',
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
        'approval_status',
        'flow_locked',
        'flow_user_map',
        'public_share_token',
    ];

    protected $casts = [
        'details'          => 'array',
        'previous_details' => 'array',
        'flow_user_map'    => 'array',
        'is_annex'         => 'boolean',
        'is_legacy'        => 'boolean',
    ];

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
    public function ensurePublicShareToken(): string
    {
        if (! $this->public_share_token) {
            $this->update(['public_share_token' => Str::random(48)]);
        }

        return $this->public_share_token;
    }
}
