<?php

namespace App\Models;

use App\Enums\InvestmentRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InvestmentRequest extends Model
{
    use HasFactory;

    // Los Excel no se pueden mostrar en el navegador; solo se descargan.
    public const PREVIEWABLE_MIME_TYPES = ['application/pdf', 'image/jpeg', 'image/png'];

    protected $fillable = [
        'group_id',
        'company_id',
        'concept',
        'amount',
        'deadline_at',
        'requested_by',
        'status',
        'file_path',
        'original_name',
        'mime_type',
        'file_size',
        'uploaded_by',
    ];

    protected $casts = [
        'deadline_at' => 'date',
        'amount'      => 'decimal:2',
        'status'      => InvestmentRequestStatus::class,
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function approvals()
    {
        return $this->hasMany(InvestmentRequestApproval::class)->orderBy('sequence_order');
    }

    // Quien tiene el turno ahora mismo (solo hay uno a la vez).
    public function currentApproval()
    {
        return $this->hasOne(InvestmentRequestApproval::class)->where('status', 'pending');
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasFile(): bool
    {
        return ! empty($this->file_path);
    }

    public function isPreviewable(): bool
    {
        return in_array($this->mime_type, self::PREVIEWABLE_MIME_TYPES, true);
    }
}
