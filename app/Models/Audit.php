<?php

namespace App\Models;

use App\Notifications\AuditScheduledNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Auditoría de un proceso dentro del plan anual del auditor. Desde el lado del auditado solo es
 * informativa: le llega el aviso del mes (sin día) y, cuando el auditor captura la fecha
 * acordada, la ve aquí. La auditoría se hace en vivo por Teams; el checklist sale del proceso.
 */
class Audit extends Model
{
    public const STATUS_PLANNED   = 'programada';
    public const STATUS_COMPLETED = 'realizada';
    public const STATUS_CANCELLED = 'cancelada';

    protected $fillable = [
        'group_id', 'company_id', 'regulation_id', 'auditor_id',
        'planned_for', 'scheduled_at', 'status', 'result', 'completed_at', 'month_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'planned_for'       => 'date',
            'scheduled_at'      => 'datetime',
            'completed_at'      => 'datetime',
            'month_notified_at' => 'datetime',
        ];
    }

    public function regulation(): BelongsTo
    {
        return $this->belongsTo(Regulation::class)->withoutGlobalScopes();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function auditees(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withTimestamps();
    }

    public function findings(): HasMany
    {
        return $this->hasMany(AuditFinding::class);
    }

    /** Auditorías donde el usuario es auditado. */
    public function scopeForAuditee(Builder $query, User $user): Builder
    {
        return $query->whereHas('auditees', fn ($q) => $q->where('users.id', $user->id));
    }

    /** Ya empezó su mes: antes de eso el auditado no debe enterarse de que existe. */
    public function scopeMonthStarted(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query->whereDate('planned_for', '<=', ($now ?? now())->copy()->startOfMonth());
    }

    /** Activas para el auditado: programadas y con su mes ya empezado. */
    public function scopeActiveForAuditee(Builder $query, User $user): Builder
    {
        return $query->forAuditee($user)->monthStarted()->where('status', self::STATUS_PLANNED);
    }

    public function hasMonthStarted(?CarbonInterface $now = null): bool
    {
        return $this->planned_for->lte(($now ?? now())->copy()->startOfMonth());
    }

    /** "noviembre 2026" */
    public function plannedMonthLabel(): string
    {
        return $this->planned_for->locale('es')->isoFormat('MMMM YYYY');
    }

    /** "18 de noviembre de 2026, 10:00" */
    public function scheduledLabel(): ?string
    {
        // isoFormat y no translatedFormat: con las letras escapadas de "de" translatedFormat se
        // quedaba en un ciclo infinito.
        return $this->scheduled_at?->locale('es')->isoFormat('D [de] MMMM [de] YYYY, HH:mm');
    }

    /**
     * El auditor registra (o cambia) el día y la hora acordados. Al auditado se le avisa solo si
     * su mes ya empezó; si no, se entera con el aviso del mes y ya ve la fecha en la tarjeta.
     */
    public function schedule(CarbonInterface $at): void
    {
        $rescheduled = $this->scheduled_at !== null;
        $this->update(['scheduled_at' => Carbon::instance($at)]);

        if ($this->status !== self::STATUS_PLANNED || ! $this->hasMonthStarted()) {
            return;
        }

        foreach ($this->auditees()->where('status', 'active')->get() as $auditee) {
            $auditee->notify(new AuditScheduledNotification($this, $rescheduled));
        }
    }
}
