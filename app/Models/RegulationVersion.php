<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class RegulationVersion extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'regulation_id',
        'version_number',
        'change_description',
        'change_justification',
        'changes_table',
        'body_html',
        'keeps_docx_layout',
        'file_path',
        'original_name',
        'disk',
        'mime_type',
        'responsible_name',
        'issued_at',
        'valid_until',
        'is_current',
        'uploaded_by',
        'editing_by',
        'editing_expires_at',
        'draft_html',
        'draft_saved_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_at'          => 'date',
            'valid_until'        => 'date',
            'is_current'         => 'boolean',
            'keeps_docx_layout'  => 'boolean',
            'changes_table'      => 'array',
            'editing_by'         => 'integer',
            'editing_expires_at' => 'datetime',
            'draft_saved_at'     => 'datetime',
        ];
    }

    public function regulation()
    {
        return $this->belongsTo(Regulation::class);
    }

    /**
     * Su formato vive en el .docx, no en body_html: un Word subido (sin body_html) o una edición
     * que lo parchó (DocxLayoutPatcher). Editarla parcha este .docx en vez de reconstruirlo, y
     * "Ver" muestra el .docx real.
     */
    public function hasDocxLayout(): bool
    {
        return $this->keeps_docx_layout || ! $this->body_html;
    }

    /**
     * Las rutas /regulation-versions/{version} no pasan por {regulation}: sin esto, una versión de
     * un proceso confidencial se podría abrir por URL (whereHas aplica el filtro de Regulation).
     */
    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where($field ?? $this->getRouteKeyName(), $value)
            ->whereHas('regulation', fn ($q) => $q->withTrashed())
            ->first();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast();
    }

    public function isNearExpiration(int $days = 60): bool
    {
        return $this->valid_until
            && $this->valid_until->lte(now()->addDays($days))
            && ! $this->valid_until->isPast();
    }

    /**
     * La versión inmediata anterior a esta (por número de versión) — null si esta es la versión 1.
     * Usado para dar contexto de "antes/después" al aprobador (correo y "Mis aprobaciones").
     */
    public function previousVersion(): ?self
    {
        return static::where('regulation_id', $this->regulation_id)
            ->where('version_number', '<', $this->version_number)
            ->orderByDesc('version_number')
            ->first();
    }
}
