<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\RequirementStatus;
use Carbon\Carbon;
use Carbon\CarbonImmutable;

class AssetRequirement extends Model
{
    use HasFactory;
    private const WARNING_DAYS = 60;
    private const DANGER_DAYS = 30;

    protected $fillable = [
        'company_id',
        'asset_id',
        'requirement_template_id',
        'type',
        'status',
        'due_date',
        'compliance_scope',
        'completed_at',
        'issued_at',
        'expires_at',
        'current_document_id',
        'recurrence_interval',
        'recurrence_unit',
        'recurrence_anchor',
    ];

    protected $casts = [
        'status' => \App\Enums\RequirementStatus::class,
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'recurrence_anchor' => 'date',
        'issued_at' => 'date',
        'expires_at' => 'date',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function asset()
    {
        return $this->belongsTo(Asset::class);
    }

    public function requirementTemplate()
    {
        return $this->belongsTo(RequirementTemplate::class, 'requirement_template_id');
    }

    public function template()
    {
        return $this->belongsTo(RequirementTemplate::class, 'requirement_template_id');
    }

    public function tasks()
    {
        return $this->hasMany(Task::class);
    }

    public function refreshExpirationStatus(): void
    {
        if (!$this->due_date) {
            return;
        }

        if (
            $this->due_date->lt(now()) &&
            !in_array($this->status, [
                RequirementStatus::COMPLETED,
                RequirementStatus::CANCELLED,
                RequirementStatus::EXPIRED,
                RequirementStatus::IN_TRANSIT,
            ])
        ) {
            $this->updateQuietly([
                'status' => RequirementStatus::EXPIRED
            ]);
        }
    }

    public function getRiskLevelAttribute(): string
    {
        if (!$this->due_date) {
            return 'normal';
        }

        if ($this->status === RequirementStatus::EXPIRED) {
            return 'expired';
        }

        $today = Carbon::today();
        $daysLeft = $today->diffInDays($this->due_date, false);

        if ($daysLeft < 0) {
            return 'expired';
        }

        if ($daysLeft <= self::DANGER_DAYS) {
            return 'danger';
        }

        if ($daysLeft <= self::WARNING_DAYS) {
            return 'warning';
        }

        return 'normal';
    }

    public function scopeExpired($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', [
                RequirementStatus::COMPLETED,
                RequirementStatus::CANCELLED,
                RequirementStatus::IN_TRANSIT,
            ]);
    }

    public function scopePending($query)
    {
        return $query->where('status', RequirementStatus::PENDING);
    }

    public function scopeDueSoon($query, int $days = self::WARNING_DAYS)
    {
        return $query->whereBetween('due_date', [
            now(),
            now()->addDays($days)
        ])->whereNotIn('status', [
            RequirementStatus::COMPLETED,
            RequirementStatus::CANCELLED,
            RequirementStatus::IN_TRANSIT,
        ]);
    }

    public function scopeCritical($query)
    {
        return $query->whereBetween('due_date', [
            now(),
            now()->addDays(self::DANGER_DAYS)
        ])->whereNotIn('status', [
            RequirementStatus::COMPLETED,
            RequirementStatus::CANCELLED,
            RequirementStatus::IN_TRANSIT,
        ]);
    }

    public function scopeForCompany($query, $company)
    {
        $companyId = $company instanceof \App\Models\Company ? $company->id : $company;
        return $query->where('company_id', $companyId);
    }

    public function getProgressAttribute(): int
    {
        $total = (int) ($this->tasks_total ?? 0);
        $done  = (int) ($this->tasks_done ?? 0);

        if ($total === 0) return 0;

        return (int) round(($done / $total) * 100);
    }

    public function getComputedStatusAttribute(): string
    {
        // Si ya está completado por fecha
        if ($this->completed_at) return 'completed';

        // Respeta tu enum
        if ($this->status === RequirementStatus::COMPLETED) return 'completed';
        if ($this->status === RequirementStatus::CANCELLED) return 'cancelled';
        if ($this->status === RequirementStatus::EXPIRED) return 'expired';
        if ($this->status === RequirementStatus::IN_TRANSIT) return 'in_transit';

        // Estado visual expirado aunque no se haya persistido (in_transit ya fue excluido)
        if ($this->due_date && $this->due_date->lt(now())) return 'expired';

        // Inferencia ligera por tareas
        if (($this->tasks_done ?? 0) > 0) return 'in_progress';

        return 'pending';
    }

    public function canBeMarkedInTransit(): bool
    {
        if (in_array($this->status, [
            RequirementStatus::COMPLETED,
            RequirementStatus::CANCELLED,
            RequirementStatus::IN_TRANSIT,
        ])) {
            return false;
        }

        if (!$this->tasks()->exists()) {
            return false;
        }

        return !$this->tasks()->whereNull('completed_at')->exists();
    }

    public function isRecurrent(): bool
    {
        return !is_null($this->recurrence_interval) && !is_null($this->recurrence_unit);
    }

    public function recurrenceLabel(): ?string
    {
        if (!$this->isRecurrent()) {
            return null;
        }

        return "{$this->recurrence_interval} {$this->recurrence_unit}";
    }

    public function nextDueDate(): ?CarbonImmutable
    {
        if (!$this->isRecurrent()) {
            return null;
        }

        $base = CarbonImmutable::parse($this->due_date);

        // Si quieres usar anchor cuando exista (opcional)
        if ($this->recurrence_anchor) {
            $base = CarbonImmutable::parse($this->recurrence_anchor);
        }

        return match ($this->recurrence_unit) {
            'day' => $base->addDays($this->recurrence_interval),
            'week' => $base->addWeeks($this->recurrence_interval),
            'month' => $base->addMonthsNoOverflow($this->recurrence_interval),
            'year' => $base->addYearsNoOverflow($this->recurrence_interval),
            default => null,
        };
    }

    public function canBeCompleted(): bool
    {
        // Si no tiene tareas, NO debería poder completarse
        if (!$this->tasks()->exists()) {
            return false;
        }

        // Si hay tareas pendientes (no completed_at), no se puede completar
        if ($this->tasks()->whereNull('completed_at')->exists()) {
            return false;
        }

        // Si alguna tarea requiere documento, debe tener al menos 1 documento
        if ($this->tasks()
            ->where('requires_document', true)
            ->whereDoesntHave('documents')
            ->exists()
        ) {
            return false;
        }

        // ✅ NUEVO: Documento oficial obligatorio
        if (!$this->documents()->exists()) {
            return false;
        }

        return true;
    }

    public function documents()
    {
        return $this->hasMany(\App\Models\AssetRequirementDocument::class, 'asset_requirement_id');
    }

    public function latestDocument()
    {
        return $this->hasOne(AssetRequirementDocument::class)->latestOfMany();
    }

    public function hasOfficialDocument(): bool
    {
        // Si ya tienes relación documents() al modelo AssetRequirementDocument
        return $this->documents()->exists();
    }

    public function currentDocument()
    {
        return $this->belongsTo(
            AssetRequirementDocument::class,
            'current_document_id'
        );
    }

    public function renewalTasks()
    {
        return $this->tasks()->where('type', 'renewal');
    }
}
