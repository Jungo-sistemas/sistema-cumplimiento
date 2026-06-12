<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegulationApproval extends Model
{
    protected $fillable = [
        'regulation_id',
        'step_number',
        'job_position_id',
        'user_id',
        'requires_all',
        'status',
        'comments',
        'decided_at',
    ];

    protected $casts = [
        'requires_all' => 'boolean',
        'decided_at'   => 'datetime',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function jobPosition()
    {
        return $this->belongsTo(JobPosition::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
