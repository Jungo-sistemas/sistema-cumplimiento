<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RegulationShare extends Model
{
    protected $fillable = [
        'regulation_id', 'sent_by', 'user_id',
        'token', 'sent_at', 'viewed_at', 'viewed_ip',
    ];

    protected $casts = [
        'sent_at'   => 'datetime',
        'viewed_at' => 'datetime',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hasBeenViewed(): bool
    {
        return $this->viewed_at !== null;
    }
}
