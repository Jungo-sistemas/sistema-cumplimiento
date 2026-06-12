<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

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
        'code',
        'name',
        'details',
        'is_active',
        'created_by',
        'impact_level',
        'approval_status',
    ];

    protected $casts = [
        'details' => 'array',
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

        if (! $version) {
            return 'yellow';
        }

        if ($version->valid_until && $version->valid_until->isPast()) {
            return 'red';
        }

        if ($version->valid_until && $version->valid_until->lte(now()->addDays(60))) {
            return 'yellow';
        }

        return 'green';
    }

    public function statusLabel(): string
    {
        return match ($this->statusColor()) {
            'red'    => 'Vencido',
            'yellow' => $this->currentVersion ? 'Por vencer' : 'Pendiente',
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
}
