<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hallazgo que el auditor registra en un punto del proceso durante la auditoría. Para el
 * responsable es una tarea: subir evidencia; el auditor la acepta (cierra) o la rechaza con motivo.
 */
class AuditFinding extends Model
{
    public const STATUS_PENDING   = 'pendiente';    // espera evidencia
    public const STATUS_IN_REVIEW = 'en_revision';  // evidencia enviada, la revisa el auditor
    public const STATUS_REJECTED  = 'rechazada';    // el auditor rechazó la evidencia: subir otra
    public const STATUS_CLOSED    = 'cerrada';

    /** Estados en los que el responsable tiene que actuar (subir evidencia). */
    public const NEEDS_EVIDENCE = [self::STATUS_PENDING, self::STATUS_REJECTED];

    public const SEVERITIES = [
        'critica' => 'Crítica',
        'mayor'   => 'Mayor',
        'menor'   => 'Menor',
    ];

    public const MAX_FILES = 5;

    protected $fillable = ['audit_id', 'responsible_id', 'point', 'description', 'severity', 'due_at', 'status', 'closed_at'];

    protected function casts(): array
    {
        return [
            'due_at'    => 'date',
            'closed_at' => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AuditFindingSubmission::class)->latest('id');
    }

    public function latestSubmission(): HasOne
    {
        return $this->hasOne(AuditFindingSubmission::class)->latestOfMany();
    }

    public function scopeForResponsible(Builder $query, User $user): Builder
    {
        return $query->where('responsible_id', $user->id);
    }

    /** Por atender = todo lo que no está cerrado (incluye "en revisión", que espera al auditor). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function needsEvidence(): bool
    {
        return in_array($this->status, self::NEEDS_EVIDENCE, true);
    }

    public function severityLabel(): ?string
    {
        return self::SEVERITIES[$this->severity ?? ''] ?? null;
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->status !== self::STATUS_CLOSED && $this->due_at->lt(today());
    }
}
