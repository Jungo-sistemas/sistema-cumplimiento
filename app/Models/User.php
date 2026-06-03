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
        'status',
        'invite_token',
        'invite_expires_at',
        'invitation_accepted_at',
        'invited_by',
        'email_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'invite_token',
    ];

    protected $with = ['role', 'company'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invite_expires_at' => 'datetime',
            'invitation_accepted_at' => 'datetime',
            'password' => 'hashed',
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

    public function canAccessCompany(?Company $company): bool
    {
        if (! $company) {
            return false;
        }

        if ($this->isGlobalScope()) {
            return true;
        }

        if ($this->hasGroupScope()) {
            return $this->group_id === $company->group_id;
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

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}