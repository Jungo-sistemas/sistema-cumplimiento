<?php

namespace App\Models;

use App\Notifications\AuditScheduledNotification;
use App\Notifications\AuditUpcomingNotification;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Auditoría de un proceso dentro del plan anual del auditor. Desde el lado del auditado solo es
 * informativa: el auditor fija la fecha y al auditado le llega el aviso unos días antes
 * (config auditorias.aviso_dias_antes); hasta entonces no la ve. La auditoría se hace en vivo por
 * Teams; el checklist sale del proceso.
 */
class Audit extends Model
{
    public const STATUS_PLANNED   = 'programada';
    public const STATUS_COMPLETED = 'realizada';
    public const STATUS_CANCELLED = 'cancelada';

    protected $fillable = [
        'group_id', 'company_id', 'regulation_id', 'auditor_id',
        'planned_for', 'scheduled_at', 'status', 'result', 'completed_at', 'notice_sent_at',
        'score', 'conclusions', 'attendance',
    ];

    protected function casts(): array
    {
        return [
            'planned_for'    => 'date',
            'scheduled_at'   => 'datetime',
            'completed_at'   => 'datetime',
            'notice_sent_at' => 'datetime',
            'attendance'     => 'array',
            'score'          => 'integer',
        ];
    }

    // Estado como lo ve el auditor.
    public const STAGE_UNSCHEDULED = 'Sin fecha';
    public const STAGE_SCHEDULED   = 'Programada';
    public const STAGE_CAPTURE     = 'En captura';
    public const STAGE_DONE        = 'Finalizada';

    public function checklistItems(): HasMany
    {
        return $this->hasMany(AuditChecklistItem::class)->orderBy('position')->orderBy('id');
    }

    /** Auditorías que ve un usuario en Auditorías: el auditor las suyas; un admin, las de su grupo. */
    public function scopeVisibleToAuditor(Builder $query, User $user): Builder
    {
        return $user->isAdmin()
            ? $query->where('group_id', $user->group_id)
            : $query->where('auditor_id', $user->id);
    }

    public function isVisibleToAuditor(User $user): bool
    {
        return $user->isAdmin() ? $this->group_id === $user->group_id : $this->auditor_id === $user->id;
    }

    /** Solo el auditor asignado captura y decide; un admin puede consultar. */
    public function isRunBy(User $user): bool
    {
        return $this->auditor_id === $user->id;
    }

    /** Sin fecha / Programada / En captura (ya llegó el día) / Finalizada. */
    public function auditorStage(): string
    {
        return match (true) {
            $this->status === self::STATUS_COMPLETED => self::STAGE_DONE,
            $this->scheduled_at === null             => self::STAGE_UNSCHEDULED,
            $this->scheduled_at->copy()->startOfDay()->lte(now()) => self::STAGE_CAPTURE,
            default                                  => self::STAGE_SCHEDULED,
        };
    }

    public function isCapturable(): bool
    {
        return $this->status === self::STATUS_PLANNED && $this->auditorStage() === self::STAGE_CAPTURE;
    }

    /** "18 nov 2026" */
    public function shortDateLabel(): string
    {
        return ($this->completed_at ?? $this->scheduled_at)?->locale('es')->isoFormat('DD MMM YYYY') ?? 'Sin fecha';
    }

    /** @return int[] */
    public function attendeeIds(): array
    {
        return array_map('intval', $this->attendance ?? []);
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

    /** Días antes de la fecha en que se avisa al auditado (config auditorias.aviso_dias_antes). */
    public static function noticeDays(): int
    {
        return max(0, (int) config('auditorias.aviso_dias_antes', 3));
    }

    /** Último día que cae dentro del aviso: una auditoría con fecha hasta ese día ya se avisa. */
    private static function noticeLimit(?CarbonInterface $now = null): CarbonInterface
    {
        return ($now ?? now())->copy()->startOfDay()->addDays(self::noticeDays());
    }

    /**
     * El auditado ya puede saber de ella: ya se le avisó, o el auditor fijó una fecha que cae dentro
     * de los días de aviso. Sin fecha, o con la fecha más lejos, para él todavía no existe.
     */
    public function scopeNoticeStarted(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query->where(fn ($q) => $q->whereNotNull('notice_sent_at')
            ->orWhere(fn ($q) => $q->whereNotNull('scheduled_at')->whereDate('scheduled_at', '<=', self::noticeLimit($now))));
    }

    /** Activas para el auditado: programadas y ya avisadas (o dentro de los días de aviso). */
    public function scopeActiveForAuditee(Builder $query, User $user): Builder
    {
        return $query->forAuditee($user)->noticeStarted()->where('status', self::STATUS_PLANNED);
    }

    /** Programadas con fecha dentro de los días de aviso (sin las ya pasadas) y sin aviso enviado. */
    public function scopeNoticeDue(Builder $query, ?CarbonInterface $now = null): Builder
    {
        return $query->where('status', self::STATUS_PLANNED)
            ->whereNull('notice_sent_at')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '>=', ($now ?? now())->copy())
            ->whereDate('scheduled_at', '<=', self::noticeLimit($now));
    }

    public function isNoticeDue(?CarbonInterface $now = null): bool
    {
        return $this->status === self::STATUS_PLANNED
            && $this->notice_sent_at === null
            && $this->scheduled_at !== null
            && $this->scheduled_at->gte($now ?? now())
            && $this->scheduled_at->copy()->startOfDay()->lte(self::noticeLimit($now));
    }

    /** Manda el aviso "tienes una auditoría" a los auditados activos y lo marca como enviado. */
    public function sendNotice(): int
    {
        $auditees = $this->auditees()->where('status', 'active')->get();
        foreach ($auditees as $auditee) {
            $auditee->notify(new AuditUpcomingNotification($this));
        }

        $this->update(['notice_sent_at' => now()]);

        return $auditees->count();
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
     * El auditor fija (o cambia) el día y la hora. El auditado se entera unos días antes
     * (aviso_dias_antes): si la fecha ya cae dentro de ese plazo, el aviso sale ahora; si no, lo
     * manda audits:notify-upcoming cuando llegue. Si ya se le había avisado, se le avisa del cambio.
     */
    public function schedule(CarbonInterface $at): void
    {
        $this->update(['scheduled_at' => Carbon::instance($at)]);

        if ($this->status !== self::STATUS_PLANNED) {
            return;
        }

        if ($this->notice_sent_at !== null) {
            foreach ($this->auditees()->where('status', 'active')->get() as $auditee) {
                $auditee->notify(new AuditScheduledNotification($this, rescheduled: true));
            }

            return;
        }

        if ($this->isNoticeDue()) {
            $this->sendNotice();
        }
    }
}
