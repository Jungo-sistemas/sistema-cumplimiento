<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Una entrega de evidencia del responsable, con la revisión del auditor. */
class AuditFindingSubmission extends Model
{
    public const STATUS_IN_REVIEW = 'en_revision';
    public const STATUS_ACCEPTED  = 'aceptada';
    public const STATUS_REJECTED  = 'rechazada';

    protected $fillable = ['audit_finding_id', 'user_id', 'comment', 'status', 'review_comment', 'reviewed_at'];

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
}
