<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Enums\TaskStatus;


class Task extends Model
{
    use HasFactory;

    public const TYPE_MANUAL = 'manual';
    public const TYPE_INITIAL = 'initial';
    public const TYPE_RENEWAL = 'renewal';
    public const TYPE_CHECKOUT = 'checkout';
    public const TYPE_CHECKIN = 'checkin';
    public const TYPE_REVIEW = 'review';

    protected $fillable = [
        'asset_requirement_id',
        'title',
        'description',
        'status',
        'type',
        'due_date',
        'completed_at',
        'completed_by',
        'requires_document',
    ];

    protected $casts = [
        'status' => \App\Enums\TaskStatus::class,
        'due_date' => 'datetime',
        'completed_at' => 'datetime',
        'requires_document' => 'boolean',
    ];

    public function requirement()
    {
        return $this->belongsTo(AssetRequirement::class, 'asset_requirement_id');
    }

    public function users()
    {
        return $this->belongsToMany(User::class);
    }

    public function documents()
    {
        return $this->hasMany(TaskDocument::class);
    }
}

