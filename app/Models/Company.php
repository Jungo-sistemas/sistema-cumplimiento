<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Group;


class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'process_name', 'group_id', 'show_in_processes', 'asset_limit', 'otras', 'is_active'];

    protected $casts = [
        'otras'     => 'boolean',
        'is_active' => 'boolean',
    ];

    // Orden por el nombre que se ve en Procesos (process_name si lo tiene).
    public const PROCESOS_NAME_SQL = "COALESCE(NULLIF(process_name, ''), name)";

    /**
     * Nombre de la empresa en Procesos: algunas se muestran distinto ahí que en Cumplimiento
     * (VIDENCI → SAVSA, MIGAR CAPITAL → MIGAR…). Toda pantalla, correo o documento de Procesos
     * usa este; Cumplimiento usa `name`.
     */
    public function getProcesosNameAttribute(): string
    {
        return $this->process_name ?: $this->name;
    }

    public function licenses()
    {
        return $this->morphMany(License::class, 'licensable');
    }

    public function activeLicense()
    {
        return $this->morphOne(License::class, 'licensable')
            ->where('status', 'active')
            ->latest('activated_at');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function assetTypes()
    {
        return $this->hasMany(AssetType::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function requirementTemplates()
    {
        return $this->hasMany(RequirementTemplate::class);
    }

    public function assetRequirements()
    {
        return $this->hasMany(AssetRequirement::class);
    }

    public function assetObligations()
    {
        return $this->hasMany(AssetObligation::class);
    }
    public function regulations()
    {
        return $this->hasMany(Regulation::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}

