<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiToken extends Model
{
    protected $fillable = ['group_id', 'name', 'token', 'last_used_at'];

    protected $hidden = ['token'];

    protected $casts = ['last_used_at' => 'datetime'];

    public function group()
    {
        return $this->belongsTo(\App\Models\Group::class);
    }
}
