<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    public const DOCUMENT_TYPES = [
        'Poderes',
        'Escrituras de terrenos',
        'Acta constitutiva',
        'Acta de asamblea',
        'Contratos',
        'Acuerdos',
        'Convenios',
        'Libros sociales',
        'Libros de accionistas',
        'Constancias del IMPI',
        'Títulos de concesión',
        'Registros de UMA',
        'Otros',
    ];

    protected $fillable = [
        'group_id',
        'company_id',
        'document_folder_id',
        'name',
        'description',
        'document_type',
        'reference',
        'bodega',
        'responsible_name',
        'is_required',
        'is_active',
        'uploaded_by',
        'deleted_by',
        'permanently_delete_at',
    ];

    protected $casts = [
        'permanently_delete_at' => 'datetime',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function folder()
    {
        return $this->belongsTo(DocumentFolder::class, 'document_folder_id');
    }

    public function versions()
    {
        return $this->hasMany(DocumentVersion::class)
            ->orderByDesc('version_number');
    }

    public function currentVersions()
    {
        return $this->hasMany(DocumentVersion::class)
            ->where('is_current', true)
            ->orderByDesc('version_number');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function deletedBy()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function authorizedUsers()
    {
        return $this->belongsToMany(User::class, 'document_authorized_users')
            ->withTimestamps();
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function hasFile(): bool
    {
        return $this->currentVersions()->exists();
    }

    /**
     * Un documento puede tener varios archivos vigentes a la vez (ej. la escritura y su anexo),
     * así que "vencido"/"por vencer" se evalúa sobre el conjunto: si CUALQUIERA de los archivos
     * vigentes está vencido o por vencer, el documento completo se marca así (para no esconder
     * que algo necesita atención).
     */
    public function isExpired(): bool
    {
        return $this->currentVersions->contains(fn (DocumentVersion $v) => $v->isExpired());
    }

    public function isNearExpiration(int $days = 60): bool
    {
        return $this->currentVersions->contains(fn (DocumentVersion $v) => $v->isNearExpiration($days));
    }

    public function vigenciaStatus(): string
    {
        if ($this->isExpired()) {
            return 'vencido';
        }

        if ($this->isNearExpiration()) {
            return 'por_vencer';
        }

        if ($this->currentVersions->contains(fn (DocumentVersion $v) => $v->valid_until)) {
            return 'vigente';
        }

        return 'sin_vencimiento';
    }
}