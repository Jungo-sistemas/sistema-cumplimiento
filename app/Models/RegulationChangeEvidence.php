<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Evidencia de apoyo de la síntesis de cambios: imagen o PDF que ven los aprobadores junto a la
 * síntesis (ver Regulation::syncChangeEvidences()).
 */
class RegulationChangeEvidence extends Model
{
    protected $table = 'regulation_change_evidences';

    protected $fillable = [
        'regulation_id',
        'path',
        'original_name',
        'mime_type',
        'uploaded_by',
    ];

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }
}
