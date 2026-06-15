<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class AssetType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'priority_level',
        'warning_days',
        'danger_days',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }
}

