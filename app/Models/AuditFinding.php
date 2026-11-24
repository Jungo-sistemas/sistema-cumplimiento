<?php

namespace App\Models;

use App\Support\BusinessDays;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Hallazgo que el auditor registra en un punto del proceso durante la auditoría. Flujo (lámina del
 * cliente, nov 2026):
 *
 *   1. Documentar (auditor)          → se avisa al responsable y a los responsables del proceso
 *   2. Causa raíz y acciones (auditado)   causa_pendiente / causa_rechazada
 *   3. Validar (auditor)                  causa_en_revision   → aprueba o la devuelve a 2
 *   4. Corregir: seguimiento (auditado)   evidencia_pendiente / evidencia_rechazada
 *   5. Verificar y cerrar (auditor)       evidencia_en_revision → cierra o la devuelve a 4
 *
 * Cada etapa tiene su plazo en días hábiles según la severidad (config auditorias.plazos_hallazgos);
 * due_at es el de la etapa actual. La bitácora (events) queda como historial del responsable.
 */
class AuditFinding extends Model
{
    public const STATUS_ROOT_CAUSE_PENDING  = 'causa_pendiente';
    public const STATUS_ROOT_CAUSE_REVIEW   = 'causa_en_revision';
    public const STATUS_ROOT_CAUSE_REJECTED = 'causa_rechazada';
    public const STATUS_EVIDENCE_PENDING    = 'evidencia_pendiente';
    public const STATUS_EVIDENCE_REVIEW     = 'evidencia_en_revision';
    public const STATUS_EVIDENCE_REJECTED   = 'evidencia_rechazada';
    public const STATUS_CLOSED              = 'cerrada';

    /** Estados en los que le toca actuar al responsable (causa raíz o evidencia). */
    public const NEEDS_AUDITEE = [
        self::STATUS_ROOT_CAUSE_PENDING, self::STATUS_ROOT_CAUSE_REJECTED,
        self::STATUS_EVIDENCE_PENDING, self::STATUS_EVIDENCE_REJECTED,
    ];

    /** Estados en los que le toca al auditor (validar la causa o verificar la evidencia). */
    public const NEEDS_AUDITOR = [self::STATUS_ROOT_CAUSE_REVIEW, self::STATUS_EVIDENCE_REVIEW];

    /** Etapa de la lámina (config auditorias.plazos_hallazgos) de cada estado abierto. */
    public const STAGES = [
        self::STATUS_ROOT_CAUSE_PENDING  => 'causa',
        self::STATUS_ROOT_CAUSE_REJECTED => 'causa',
        self::STATUS_ROOT_CAUSE_REVIEW   => 'validar',
        self::STATUS_EVIDENCE_PENDING    => 'corregir',
        self::STATUS_EVIDENCE_REJECTED   => 'corregir',
        self::STATUS_EVIDENCE_REVIEW     => 'verificar',
    ];

    public const STAGE_LABELS = [
        'causa'     => 'Causa raíz y acciones',
        'validar'   => 'Validación del auditor',
        'corregir'  => 'Seguimiento: evidencia',
        'verificar' => 'Verificación del auditor',
    ];

    public const SEVERITY_CRITICAL = 'critico';

    public const SEVERITIES = [
        'critico'    => 'Crítico',
        'no_critico' => 'No crítico',
    ];

    public const MAX_FILES = 5;

    protected $fillable = ['audit_id', 'audit_checklist_item_id', 'responsible_id', 'point', 'description', 'severity', 'due_at', 'overdue_notified_at', 'status', 'closed_at'];

    protected function casts(): array
    {
        return [
            'due_at'              => 'date',
            'overdue_notified_at' => 'datetime',
            'closed_at'           => 'datetime',
        ];
    }

    public function audit(): BelongsTo
    {
        return $this->belongsTo(Audit::class);
    }

    public function checklistItem(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id');
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

    /** Última causa raíz enviada (la vigente, si ya se aprobó). */
    public function latestRootCause(): HasOne
    {
        return $this->hasOne(AuditFindingSubmission::class)->ofMany(['id' => 'max'], fn ($q) => $q->where('kind', AuditFindingSubmission::KIND_ROOT_CAUSE));
    }

    public function events(): HasMany
    {
        return $this->hasMany(AuditFindingEvent::class)->orderBy('id');
    }

    public function scopeForResponsible(Builder $query, User $user): Builder
    {
        return $query->where('responsible_id', $user->id);
    }

    /** Por atender = todo lo que no está cerrado (incluye lo que espera al auditor). */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    /** Abiertos con el plazo de su etapa vencido y sin aviso enviado por esta etapa. */
    public function scopeOverdueUnnotified(Builder $query, ?CarbonInterface $today = null): Builder
    {
        return $query->open()
            ->whereNotNull('due_at')
            ->whereDate('due_at', '<', ($today ?? today())->toDateString())
            ->whereNull('overdue_notified_at');
    }

    public function needsAuditee(): bool
    {
        return in_array($this->status, self::NEEDS_AUDITEE, true);
    }

    public function needsRootCause(): bool
    {
        return in_array($this->status, [self::STATUS_ROOT_CAUSE_PENDING, self::STATUS_ROOT_CAUSE_REJECTED], true);
    }

    public function needsEvidence(): bool
    {
        return in_array($this->status, [self::STATUS_EVIDENCE_PENDING, self::STATUS_EVIDENCE_REJECTED], true);
    }

    public function isCritical(): bool
    {
        return $this->severity === self::SEVERITY_CRITICAL;
    }

    public function severityLabel(): ?string
    {
        return self::SEVERITIES[$this->severity ?? ''] ?? null;
    }

    /** Etapa actual (causa, validar, corregir, verificar) o null si está cerrado. */
    public function stage(): ?string
    {
        return self::STAGES[$this->status] ?? null;
    }

    public function stageLabel(): ?string
    {
        return self::STAGE_LABELS[$this->stage() ?? ''] ?? null;
    }

    /** Días hábiles que tiene una etapa con esta severidad. */
    public static function stageDays(string $severity, string $stage): int
    {
        $plazos = config('auditorias.plazos_hallazgos');

        return (int) ($plazos[$severity][$stage] ?? $plazos['no_critico'][$stage] ?? 0);
    }

    /** Plazo de la etapa en la que queda el hallazgo, contado desde $from (hoy por defecto). */
    public function stageDueDate(?CarbonInterface $from = null): ?CarbonInterface
    {
        $stage = $this->stage();

        return $stage ? BusinessDays::add($from ?? now(), self::stageDays($this->severity ?? 'no_critico', $stage)) : null;
    }

    /**
     * Días hábiles en que se cierra todo el hallazgo, como lo comunica la lámina del cliente (para
     * mostrar "se cierra en N días hábiles"). En el crítico incluye el día de la contención.
     */
    public static function totalDays(string $severity): int
    {
        return (int) config("auditorias.cierre_hallazgos.{$severity}", array_sum(config("auditorias.plazos_hallazgos.{$severity}", [])));
    }

    public function isOverdue(): bool
    {
        return $this->due_at !== null && $this->status !== self::STATUS_CLOSED && $this->due_at->lt(today());
    }
}
