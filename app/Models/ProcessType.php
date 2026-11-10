<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;

class ProcessType extends Model
{
    use HasFactory;

    protected $fillable = ['group_id', 'name', 'sort_order', 'is_active', 'is_restricted'];

    protected $casts = [
        'is_active'     => 'boolean',
        'is_restricted' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Quien no puede ver procesos confidenciales tampoco ve sus tipos en los filtros ni al crear.
        static::addGlobalScope('restricted', function (Builder $query) {
            if (Auth::user()?->canSeeRestrictedProcesses() === false) {
                $query->where($query->qualifyColumn('is_restricted'), false);
            }
        });
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
