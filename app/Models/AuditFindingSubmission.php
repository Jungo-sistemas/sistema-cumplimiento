<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una entrega del responsable, con la revisión del auditor: la causa raíz con su plan de acciones
 * (kind = causa) o la evidencia del seguimiento (kind = evidencia).
 */
class AuditFindingSubmission extends Model
{
    public const KIND_ROOT_CAUSE = 'causa';
    public const KIND_EVIDENCE   = 'evidencia';

    public const STATUS_IN_REVIEW = 'en_revision';
    public const STATUS_ACCEPTED  = 'aceptada';
    public const STATUS_REJECTED  = 'rechazada';

    protected $fillable = ['audit_finding_id', 'user_id', 'kind', 'root_cause', 'actions', 'comment', 'status', 'review_comment', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AuditFindingFile::class);
    }

    public function isRootCause(): bool
    {
        return $this->kind === self::KIND_ROOT_CAUSE;
    }

    public function kindLabel(): string
    {
        return $this->isRootCause() ? 'Causa raíz y acciones' : 'Evidencia';
    }
}
