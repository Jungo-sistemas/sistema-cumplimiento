<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DocumentFolder extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'company_id',
        'parent_id',
        'name',
        'level',
        'sort_order',
        'is_active',
        'admin_only',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function parent()
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function documents()
    {
        return $this->hasMany(Document::class)
            ->orderBy('name');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isFolder(): bool
    {
        return $this->level === 'folder';
    }

    public function isCategory(): bool
    {
        return $this->level === 'category';
    }
}