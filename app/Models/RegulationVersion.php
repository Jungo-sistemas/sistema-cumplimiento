<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegulationVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'regulation_id',
        'version_number',
        'change_description',
        'file_path',
        'original_name',
        'disk',
        'mime_type',
        'responsible_name',
        'issued_at',
        'valid_until',
        'is_current',
        'uploaded_by',
        'editing_by',
        'editing_expires_at',
        'draft_html',
        'draft_saved_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'          => 'date',
            'valid_until'        => 'date',
            'is_current'         => 'boolean',
            'editing_by'         => 'integer',
            'editing_expires_at' => 'datetime',
            'draft_saved_at'     => 'datetime',
        ];
    }

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isNearExpiration(int $days = 60): bool
    {
        return $this->valid_until
            && $this->valid_until->lte(now()->addDays($days))
            && ! $this->valid_until->isPast();
    }
}
