<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class License extends Model
{
    protected $fillable = [
        'licensable_type',
        'licensable_id',
        'includes_procesos',
        'price',
        'status',
        'activated_at',
        'expires_at',
        'reminder_7_sent_at',
        'reminder_3_sent_at',
        'reminder_1_sent_at',
        'activated_by',
    ];

    protected function casts(): array
    {
        return [
            'includes_procesos'  => 'boolean',
            'price'               => 'decimal:2',
            'activated_at'        => 'datetime',
            'expires_at'          => 'datetime',
            'reminder_7_sent_at'  => 'datetime',
            'reminder_3_sent_at'  => 'datetime',
            'reminder_1_sent_at'  => 'datetime',
        ];
    }

    public function licensable(): MorphTo
    {
        return $this->morphTo();
    }

    public function activatedBy()
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function daysUntilExpiration(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }
}
