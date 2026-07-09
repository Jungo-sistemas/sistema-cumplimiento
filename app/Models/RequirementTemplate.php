<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;


class RequirementTemplate extends Model
{
    use HasFactory;

    // Valid categories: expediente | alta | modificacion | baja
    const CATEGORIES = [
        'expediente' => 'Expediente',
        'alta'       => 'Alta / Modificación',
        'baja'       => 'Baja',
    ];

    // Tipo de normativa/documento (independiente de la categoría de ciclo de vida).
    // Define el orden en que se agrupan los requerimientos al listarlos.
    const SUBTYPE_ORDER = [
        'identificacion',
        'permiso',
        'licencia',
        'contrato',
        'poliza',
        'recipiente',
        'ubicacion',
        'combustible',
        'nom',
        'dictamen',
        'aviso',
        'estudio',
        'fotografia',
        'otro',
    ];

    const SUBTYPES = [
        'identificacion' => 'Identificación',
        'permiso'        => 'Permiso',
        'licencia'       => 'Licencia',
        'contrato'       => 'Contrato',
        'poliza'         => 'Póliza de seguro',
        'recipiente'     => 'Recipiente',
        'ubicacion'      => 'Ubicación',
        'combustible'    => 'Combustible',
        'nom'            => 'NOM',
        'dictamen'       => 'Dictamen',
        'aviso'          => 'Aviso',
        'estudio'        => 'Estudio',
        'fotografia'     => 'Fotografía',
        'otro'           => 'Otro',
    ];

    protected $fillable = [
        'asset_type_id',
        'name',
        'description',
        'authority',
        'compliance_scope',
        'category',
        'subtype',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function assetType()
    {
        return $this->belongsTo(AssetType::class);
    }

    public function assetRequirements()
    {
        return $this->hasMany(AssetRequirement::class);
    }

    public function getSubtypeRankAttribute(): int
    {
        $rank = array_search($this->subtype, self::SUBTYPE_ORDER, true);

        return $rank === false ? count(self::SUBTYPE_ORDER) : $rank;
    }
}

