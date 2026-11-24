<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Soporte (archivo o foto) que el auditor adjunta a un criterio del checklist. */
class AuditChecklistFile extends Model
{
    protected $fillable = ['audit_checklist_item_id', 'path', 'original_name', 'mime_type', 'size'];

    public function item(): BelongsTo
    {
        return $this->belongsTo(AuditChecklistItem::class, 'audit_checklist_item_id');
    }
}
