<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AuditFindingFile extends Model
{
    protected $fillable = ['audit_finding_submission_id', 'path', 'original_name', 'mime_type', 'size'];

    protected static function booted(): void
    {
        // El archivo físico se va con su registro.
        static::deleting(fn (AuditFindingFile $file) => Storage::disk('private')->delete($file->path));
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(AuditFindingSubmission::class, 'audit_finding_submission_id');
    }
}
