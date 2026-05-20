<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Regulation extends Model
{
    use HasFactory, SoftDeletes;

    public const DOCUMENT_TYPES = [
        'Procedimiento',
        'Política',
        'Instructivo',
        'Formato',
        'Registro',
    ];

    protected $fillable = [
        'group_id',
        'company_id',
        'process_type_id',
        'document_type',
        'code',
        'name',
        'is_active',
        'created_by',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function processType()
    {
        return $this->belongsTo(ProcessType::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions()
    {
        return $this->hasMany(RegulationVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->hasOne(RegulationVersion::class)
            ->where('is_current', true)
            ->latestOfMany();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Green = active and valid
     * Yellow = near expiry (≤ 60 days) or no version yet
     * Red    = expired
     */
    public function statusColor(): string
    {
        $version = $this->currentVersion;

        if (! $version) {
            return 'yellow';
        }

        if ($version->valid_until && $version->valid_until->isPast()) {
            return 'red';
        }

        if ($version->valid_until && $version->valid_until->lte(now()->addDays(60))) {
            return 'yellow';
        }

        return 'green';
    }

    public function statusLabel(): string
    {
        return match ($this->statusColor()) {
            'red'    => 'Vencido',
            'yellow' => $this->currentVersion ? 'Por vencer' : 'Pendiente',
            default  => 'Vigente',
        };
    }

    public function daysUntilExpiry(): ?int
    {
        $version = $this->currentVersion;

        if (! $version?->valid_until) {
            return null;
        }

        return (int) now()->diffInDays($version->valid_until, false);
    }
}
