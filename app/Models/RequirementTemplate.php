<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class RequirementTemplate extends Model
{
    use HasFactory;

    // Valid categories: expediente | alta | modificacion | baja
    const CATEGORIES = [
        'expediente' => 'Expediente',
        'alta'       => 'Alta / Modificación',
        'baja'       => 'Baja',
    ];

    protected $fillable = [
        'asset_type_id',
        'name',
        'description',
        'authority',
        'compliance_scope',
        'category',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }

    public function assetRequirements()
    {
        return $this->hasMany(AssetRequirement::class);
    }
}

