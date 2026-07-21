<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'is_active',
        'asset_limit',
    ];

    public function companies()
    {
        return $this->hasMany(Company::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
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
}