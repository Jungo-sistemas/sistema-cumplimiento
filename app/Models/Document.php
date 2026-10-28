<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Document extends Model
{
    use HasFactory, SoftDeletes;

    // En orden alfabético para que los selectores se lean fácil; "Otros" va siempre al final.
    public const DOCUMENT_TYPES = [
        'Acta constitutiva',
        'Acta de asamblea',
        'Actas',
        'Acuerdos',
        'Aportaciones',
        'Aumento de capital',
        'Cambio de administración',
        'Cambio de consejo',
        'Cambio de razón social',
        'Cartas',
        'Cesión de acciones',
        'Cesión de usufructo',
        'Compraventa',
        'Compraventa de acciones',
        'Constancias del IMPI',
        'Contratos',
        'Convenios',
        'Escrituras',
        'Escrituras de terrenos',
        'Libro de variaciones',
        'Libros de accionistas',
        'Libros sociales',
        'Nombramiento de consejo',
        'Objeto social',
        'Pagaré',
        'Poderes',
        'Ratificación',
        'Recibos',
        'Reducción de capital',
        'Reforma de estatutos',
        'Registros de UMA',
        'Sesión',
        'Títulos de concesión',
        'Transmisión de acciones',
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

    /**
     * Los responsables se guardan como texto en responsible_name, separados por coma. Los que
     * llegaron del importador pueden venir separados por "/" o ";" (ej. "Aurelia Cruz / Martha
     * Lara"), así que se aceptan los tres separadores al leerlos.
     *
     * @return array<int, string>
     */
    public function responsibleNames(): array
    {
        return self::splitResponsibleNames($this->responsible_name);
    }

    /** @return array<int, string> */
    public static function splitResponsibleNames(?string $value): array
    {
        return collect(preg_split('/[,;\/]/', (string) $value))
            ->map(fn ($name) => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();
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