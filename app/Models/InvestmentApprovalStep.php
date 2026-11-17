<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Un lugar del flujo fijo de aprobación de Anexos 8 de una razón social. Sin user_id, la persona
 * todavía no tiene usuario y la razón social no puede arrancar solicitudes.
 */
class InvestmentApprovalStep extends Model
{
    protected $fillable = [
        'investment_entity_id',
        'sequence_order',
        'position',
        'approver_name',
        'user_id',
    ];

    public function entity()
    {
        return $this->belongsTo(InvestmentEntity::class, 'investment_entity_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
