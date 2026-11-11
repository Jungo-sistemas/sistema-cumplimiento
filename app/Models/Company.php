<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\Group;


class Company extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'process_name', 'group_id', 'show_in_processes', 'is_restricted', 'asset_limit', 'otras', 'is_active'];

    protected $casts = [
        'otras'         => 'boolean',
        'is_active'     => 'boolean',
        'is_restricted' => 'boolean',
    ];

    protected static function booted(): void
    {
        // La carpeta confidencial DIRECCIÓN es una empresa con is_restricted: para quien no puede
        // verla (User::canSeeRestrictedProcesses) no existe en ninguna lista. Sin usuario
        // (comandos, colas) no se filtra.
        //
        // Auth::hasUser() y no Auth::user(): el usuario se carga con su empresa ($with en User), y
        // preguntar Auth::user() mientras el guard lo está cargando lo volvía a cargar sin fin
        // (memoria agotada tras la doble verificación, producción 11-nov-2026). En esa carga solo
        // sale la empresa del propio usuario, así que no filtrarla ahí no expone nada.
        static::addGlobalScope('restricted', function (Builder $query) {
            if (Auth::hasUser() && Auth::user()->canSeeRestrictedProcesses() === false) {
                $query->where($query->qualifyColumn('is_restricted'), false);
            }
        });
    }

    /** IDs de las empresas confidenciales (la carpeta DIRECCIÓN de cada grupo). */
    public static function restrictedIds(?int $groupId = null): array
    {
        return static::withoutGlobalScope('restricted')
            ->where('is_restricted', true)
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->pluck('id')->all();
    }

    // Orden por el nombre que se ve en Procesos (process_name si lo tiene).
    public const PROCESOS_NAME_SQL = "COALESCE(NULLIF(process_name, ''), name)";

    /**
     * Nombre de la empresa en Procesos: algunas se muestran distinto ahí que en Cumplimiento
     * (VIDENCI → SAVSA, MIGAR CAPITAL → MIGAR…). Toda pantalla, correo o documento de Procesos
     * usa este; Cumplimiento usa `name`.
     */
    public function getProcesosNameAttribute(): string
    {
        return $this->process_name ?: $this->name;
    }

    public function licenses()
    {
        return $this->morphMany(License::class, 'licensable');
    }

    public function activeLicense()
    {
        return $this->morphOne(License::class, 'licensable')
            ->where('status', 'active')
            ->latest('activated_at');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function assetTypes()
    {
        return $this->hasMany(AssetType::class);
    }

    public function assets()
    {
        return $this->hasMany(Asset::class);
    }

    public function requirementTemplates()
    {
        return $this->hasMany(RequirementTemplate::class);
    }

    public function assetRequirements()
    {
        return $this->hasMany(AssetRequirement::class);
    }

    public function assetObligations()
    {
        return $this->hasMany(AssetObligation::class);
    }
    public function regulations()
    {
        return $this->hasMany(Regulation::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}

