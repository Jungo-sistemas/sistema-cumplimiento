<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessType extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'name', 'sort_order', 'is_active'];

    public function regulations()
    {
        return $this->hasMany(Regulation::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
