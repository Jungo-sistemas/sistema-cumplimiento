<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;



class Asset extends Model
{
    use HasFactory;
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'asset_type_id',
        'parent_asset_id',
        'name',
        'code',
        'location',
        'vault_location',
        'responsible_user_id',
        'status',
        'compliance_start_date',
        'compliance_due_date',
        // Campos para activos tipo vehículo
        'no_economico',
        'numero_serie',
        'marca',
        'modelo',
        'placas',
        'marca_recipiente',
        'capacidad_litros',
        'serie_recipiente',
    ];

    // Asset types that use vehicle-specific fields
    const VEHICLE_TYPES = ['Tracto', 'Semirremolque', 'ATQ', 'Carro tanque', 'Cilindrera'];

    public function isVehicle(): bool
    {
        return in_array($this->assetType?->name, self::VEHICLE_TYPES);
    }

    protected $casts = [
        'compliance_start_date' => 'date',
        'compliance_due_date' => 'date',
    ];

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }


    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function responsible()
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function requirements()
    {
        return $this->hasMany(AssetRequirement::class);
    }

    public function obligations()
    {
        return $this->hasMany(AssetObligation::class);
    }

    public function scopeActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE);
    }

    public function scopeInactive($q)
    {
        return $q->where('status', self::STATUS_INACTIVE);
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function ensureIsActive(): void
    {
        if ($this->isInactive()) {
            abort(423, 'El activo está desactivado. No se permiten cambios.');
        }
    }

    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function type()
    {
        return $this->belongsTo(\App\Models\AssetType::class, 'asset_type_id');
    }

    public function responsibleUser()
    {
        return $this->belongsTo(\App\Models\User::class, 'responsible_user_id');
    }

    public function parent()
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    public function children()
    {
        return $this->hasMany(Asset::class, 'parent_asset_id');
    }

    public function assetRequirements()
    {
        return $this->hasMany(\App\Models\AssetRequirement::class);
    }
}

