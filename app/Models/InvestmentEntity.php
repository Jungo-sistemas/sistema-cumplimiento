<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Razón social que se elige al capturar un Anexo 8. Tiene su flujo fijo de aprobación y, si ya
 * existe, la empresa del sistema a la que pertenece (company_id vacío = solicitud a nivel grupo).
 */
class InvestmentEntity extends Model
{
    protected $fillable = [
        'group_id',
        'company_id',
        'holding',
        'unit',
        'name',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function steps()
    {
        return $this->hasMany(InvestmentApprovalStep::class)->orderBy('sequence_order');
    }
}
