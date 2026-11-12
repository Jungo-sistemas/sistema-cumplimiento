<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Group;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'company_id',
        'group_id',
        'scope_level',
        'role_id',
        'module_access',
        'notification_modules',
        'status',
        'login_count',
        'last_login_at',
        'procesos_guide_completed_at',
        'invite_token',
        'invite_expires_at',
        'invitation_accepted_at',
        'invited_by',
        'email_verified_at',
        'two_factor_code',
        'two_factor_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invite_token',
        'two_factor_code',
    ];

    protected $with = ['role', 'company'];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'invite_expires_at'      => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'two_factor_expires_at'  => 'datetime',
            'last_login_at'          => 'datetime',
            'procesos_guide_completed_at' => 'datetime',
            'password'               => 'hashed',
        ];
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function tasks()
    {
        return $this->belongsToMany(Task::class);
    }

    public function invitedBy()
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function jobPositions()
    {
        return $this->belongsToMany(JobPosition::class, 'user_job_positions');
    }

    public function companies()
    {
        return $this->belongsToMany(Company::class, 'user_companies');
    }

    public function isAdmin(): bool
    {
        return in_array($this->role?->slug, ['admin', 'superadmin']);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->slug === 'superadmin';
    }

    public function isGlobalScope(): bool
    {
        return $this->scope_level === 'global';
    }

    public function isOperative(): bool
    {
        return $this->role?->slug === 'operative';
    }

    public function isReadOnly(): bool
    {
        return $this->role?->slug === 'readonly';
    }

    public function isAuditor(): bool
    {
        return $this->role?->slug === 'auditor';
    }

    public function isInvited(): bool
    {
        return $this->status === 'invited';
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function hasGroupScope(): bool
    {
        return $this->scope_level === 'group';
    }

    public function hasCompanyScope(): bool
    {
        return $this->scope_level === 'company';
    }

    public function hasMultipleCompanies(): bool
    {
        return $this->scope_level === 'companies';
    }

    /**
     * IDs de las empresas que este usuario puede tocar, SIN el caso de alcance global (que
     * significa "todas, sin filtrar" y se resuelve aparte en cada sitio que lo usa) — pensado
     * para los lugares que hoy filtran `where('company_id', $user->company_id)` y necesitan
     * volverse `whereIn('company_id', $user->accessibleCompanyIds())` para también cubrir el
     * caso de varias empresas específicas.
     */
    public function accessibleCompanyIds(): array
    {
        if ($this->hasGroupScope()) {
            return Company::where('group_id', $this->group_id)->pluck('id')->all();
        }

        // Consejo y los admins de solo Procesos también tienen la carpeta confidencial DIRECCIÓN.
        $restricted = $this->canSeeRestrictedProcesses() ? Company::restrictedIds($this->group_id) : [];

        if ($this->hasMultipleCompanies()) {
            return array_values(array_unique([...$this->companies->pluck('id')->all(), ...$restricted]));
        }

        return array_values(array_unique([...($this->company_id ? [$this->company_id] : []), ...$restricted]));
    }

    /**
     * En Procesos elige empresa (pantalla de empresas y selector): alcance de grupo o de varias
     * empresas, y también Consejo aunque tenga una sola empresa, para que siempre llegue a la
     * carpeta confidencial DIRECCIÓN.
     */
    public function picksProcessCompany(): bool
    {
        return $this->hasGroupScope() || $this->hasProcessCompanyList();
    }

    /** En Procesos se limita a una lista de empresas (accessibleCompanyIds), no a todo el grupo. */
    public function hasProcessCompanyList(): bool
    {
        return $this->hasMultipleCompanies() || ($this->hasCompanyScope() && $this->canSeeRestrictedProcesses());
    }

    public function canAccessCompany(?Company $company): bool
    {
        if (! $company) {
            return false;
        }

        if ($this->isGlobalScope()) {
            return true;
        }

        if ($company->is_restricted) {
            return $this->group_id === $company->group_id && $this->canSeeRestrictedProcesses();
        }

        if ($this->hasGroupScope()) {
            return $this->group_id === $company->group_id;
        }

        if ($this->hasMultipleCompanies()) {
            return $this->companies->contains('id', $company->id);
        }

        return $this->company_id === $company->id;
    }

    public function canAccessGroup(Group $group): bool
    {
        if ($this->isGlobalScope()) {
            return true;
        }

        return $this->group_id === $group->id;
    }

    public function canAccessModule(string $module): bool
    {
        $access = $this->module_access ?? 'all';
        return $access === 'all' || $access === $module;
    }

    public function accessibleModules(): array
    {
        $access = $this->module_access ?? 'all';
        return $access === 'all' ? ['cumplimiento', 'procesos'] : [$access];
    }

    /*
    |--------------------------------------------------------------------------
    | Administración de usuarios por área (nov 2026)
    |--------------------------------------------------------------------------
    | Cada admin solo da o quita lo de los módulos que él mismo tiene, para que un admin de
    | Cumplimiento no intervenga en lo de los admins de Procesos (ni al revés). El superadmin, todo.
    */

    /** Módulos que este admin puede dar o quitar. */
    public function grantableModules(): array
    {
        return $this->isSuperAdmin() ? ['cumplimiento', 'procesos'] : $this->accessibleModules();
    }

    /** Valores de module_access que puede asignar al crear o editar. */
    public function assignableModuleAccess(): array
    {
        return count($this->grantableModules()) === 2
            ? ['all', 'cumplimiento', 'procesos']
            : $this->grantableModules();
    }

    /** Puestos (Líder…Consejo) y el rol Auditor son de Procesos: solo los asigna quien tiene Procesos. */
    public function canAssignProcesosAccess(): bool
    {
        return in_array('procesos', $this->grantableModules(), true);
    }

    /**
     * Puede editar, eliminar o reenviar la invitación a $target: solo si todo lo que tiene $target
     * es de su área (a un usuario de "Ambos módulos" solo lo toca un admin de ambos). A otros
     * admins solo el superadmin.
     */
    public function canManageUser(User $target): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        if ($target->isAdmin()) {
            return false;
        }

        return array_diff($target->accessibleModules(), $this->grantableModules()) === [];
    }

    /**
     * Preferencia de notificaciones, independiente de module_access: un admin puede tener acceso
     * a ambos módulos pero solo querer avisos de uno. null/'all' = recibe de ambas áreas; 'none' =
     * de ninguna, para dejar a un solo admin del área como quien recibe solicitudes de acceso,
     * escalamientos, etc. Solo aplica a esos avisos de administración: los personales (su turno de
     * aprobar, su documento aprobado/rechazado, vencimientos de sus activos) le siguen llegando.
     */
    public function wantsNotificationsFor(string $module): bool
    {
        $pref = $this->notification_modules ?? 'all';
        return $pref === 'all' || $pref === $module;
    }

    /**
     * La guía de Procesos es obligatoria hasta que se completa una vez (sin importar cuántos
     * logins pasen mientras tanto) — por eso esto se recalcula en cada carga de página de Procesos
     * en vez de depender de un conteo de logins, que se podría evadir cerrando la pestaña.
     */
    public function needsProcesosGuide(): bool
    {
        return $this->canAccessModule('procesos') && is_null($this->procesos_guide_completed_at);
    }

    /**
     * La "Vista de auditado" (Mi espacio) va con la licencia de Procesos, igual que Auditorías. La
     * tienen todos, también quien es "Solo Cumplimiento": ahí ve sus procedimientos, aprueba lo que
     * le toca y ve sus auditorías (nov 2026).
     */
    public function canUseAuditeeView(): bool
    {
        return ! $this->isSuperAdmin() && $this->canUseProcesos();
    }

    /**
     * Puede entrar al módulo Procesos y Auditorías en alguna de sus vistas: basta con que la
     * licencia lo incluya. Si entra a Gestión o solo a Mi espacio lo decide hasProcesosGestion().
     * Se memoriza porque el layout lo consulta en cada página y la licencia cuesta una consulta.
     */
    public function canUseProcesos(): bool
    {
        return $this->canUseProcesosCache ??= $this->isSuperAdmin()
            || app(\App\Services\LicenseService::class)->hasProcesosAccessForUser($this);
    }

    /**
     * Rol con vista de Gestión: admins, auditores y operativos (los operativos crean y editan
     * procesos en línea). Los de solo lectura solo tienen Mi espacio. En Procesos y Auditorías
     * además hace falta tener acceso a Procesos: ver hasProcesosGestion().
     */
    public function hasGestion(): bool
    {
        return $this->isAdmin() || $this->isAuditor() || $this->isOperative();
    }

    /**
     * Entra a la Gestión de Procesos y Auditorías (crear y editar procesos, flujos, auditorías).
     * Un admin u operativo "Solo Cumplimiento" no: en ese módulo solo tiene Mi espacio, para que
     * no intervenga en lo que hacen los admins de Procesos.
     */
    public function hasProcesosGestion(): bool
    {
        return $this->isSuperAdmin() || ($this->hasGestion() && $this->canAccessModule('procesos'));
    }

    /** Admin de Procesos: admin con acceso a Procesos (los admins "Solo Cumplimiento" no lo son). */
    public function isProcessAdmin(): bool
    {
        return $this->isSuperAdmin() || ($this->isAdmin() && $this->canAccessModule('procesos'));
    }

    /** Puede crear, editar y compartir procesos: admin u operativo con acceso a Procesos. */
    public function isProcessEditor(): bool
    {
        return $this->isProcessAdmin() || ($this->isOperative() && $this->canAccessModule('procesos'));
    }

    /**
     * Toda la aplicación es Mi espacio para él: rol sin Gestión (solo lectura) con acceso a
     * Procesos. Un solo lectura "Solo Cumplimiento" sigue en Cumplimiento y solo ve Mi espacio
     * cuando entra a Procesos y Auditorías.
     */
    public function isAuditeeOnly(): bool
    {
        return ! $this->hasGestion() && $this->canAccessModule('procesos') && $this->canUseAuditeeView();
    }

    /**
     * Puede ver la carpeta confidencial DIRECCIÓN (Company::is_restricted): quien tiene el
     * puesto Consejo y los admins cuyo único acceso es Procesos. Ni Dirección ni los admins con
     * acceso a todas las áreas, a propósito: así lo pidió el cliente para limitar esa carpeta.
     */
    public function canSeeRestrictedProcesses(): bool
    {
        return $this->canSeeRestrictedCache ??= ($this->isAdmin() && ! $this->isSuperAdmin() && $this->module_access === 'procesos')
            || $this->jobPositions()->where('slug', 'consejo')->exists();
    }

    private ?bool $canUseProcesosCache = null;

    private ?bool $canSeeRestrictedCache = null;

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}