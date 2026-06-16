<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'company_id',
        'document_folder_id',
        'name',
        'document_type',
        'reference',
        'authorized_access_notes',
        'responsible_name',
        'is_required',
        'is_active',
        'uploaded_by',
        'deleted_by',
        'permanently_delete_at',
    ];

    protected $casts = [
        'permanently_delete_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function folder()
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersion()
    {
        return $this->hasOne(DocumentVersion::class)
            ->where('is_current', true)
            ->latestOfMany();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasFile(): bool
    {
        return $this->currentVersion()->exists();
    }

    public function isExpired(): bool
    {
        $version = $this->currentVersion;

        return $version?->valid_until && $version->valid_until->isPast();
    }

    public function isNearExpiration(int $days = 60): bool
    {
        $version = $this->currentVersion;

        return $version?->valid_until
            && $version->valid_until->lte(now()->addDays($days))
            && !$version->valid_until->isPast();
    }
}