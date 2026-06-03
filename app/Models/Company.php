<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Group;


class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'group_id', 'show_in_processes', 'asset_limit', 'otras'];

    protected $casts = [
        'otras' => 'boolean',
    ];

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

