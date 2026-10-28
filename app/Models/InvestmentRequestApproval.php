<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvestmentRequestApproval extends Model
{
    protected $fillable = [
        'investment_request_id',
        'user_id',
        'sequence_order',
        'status',
        'comments',
        'decided_at',
    ];

    protected $casts = [
        'decided_at' => 'datetime',
    ];

    public function investmentRequest()
    {
        return $this->belongsTo(InvestmentRequest::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'pending'   => 'Su turno',
            'waiting'   => 'En espera',
            'approved'  => 'Aprobó',
            'rejected'  => 'Rechazó',
            'cancelled' => 'Cancelado',
            default     => $this->status,
        };
    }
}
