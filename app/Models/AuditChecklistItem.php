<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Criterio del checklist de una auditoría, con lo que capturó el auditor. */
class AuditChecklistItem extends Model
{
    public const SOURCE_FIXED   = 'fija';
    public const SOURCE_PROCESS = 'proceso';
    public const SOURCE_AUDITOR = 'auditor';

    public const RESULTS = [
        'cumple'    => 'Cumple',
        'no_cumple' => 'No cumple',
        'parcial'   => 'Parcial',
        'no_aplica' => 'No aplica',
    ];

    /** No cumple y Parcial necesitan al menos un hallazgo. */
    public const NEEDS_FINDING = ['no_cumple', 'parcial'];

    protected $fillable = [
        'audit_id', 'position', 'source', 'text', 'description', 'evidence', 'activity',
        'result', 'observations', 'draft_findings',
    ];

    protected function casts(): array
    {
        return [
            'activity'       => 'array',
            'draft_findings' => 'array',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(AuditChecklistFile::class);
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    public function isFixed(): bool
    {
        return $this->source === self::SOURCE_FIXED;
    }

    /** Puntos que vale según su resultado (config auditorias.ponderaciones); No aplica no cuenta. */
    public function points(): ?int
    {
        return $this->result && $this->result !== 'no_aplica'
            ? (int) (config('auditorias.ponderaciones')[$this->result] ?? 0)
            : null;
    }

    public function resultLabel(): ?string
    {
        return self::RESULTS[$this->result] ?? null;
    }
}
