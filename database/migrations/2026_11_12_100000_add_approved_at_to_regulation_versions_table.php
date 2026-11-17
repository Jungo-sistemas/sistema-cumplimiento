<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Qué versión de cada proceso quedó aprobada. Al editar un proceso aprobado, la versión nueva
     * pasa a ser la "actual" (is_current) mientras se revisa; Mi espacio debe seguir mostrando la
     * última aprobada, no la que está en cambios.
     *
     * Llenado de lo que ya existe (no había registro de qué versión se aprobó):
     * - Procesos aprobados o desactualizados (documentos viejos de referencia): su versión actual.
     * - Procesos en revisión o rechazados: la versión anterior más reciente que ya tenía vigencia
     *   (valid_until solo se asigna al aprobar; las versiones nuevas la heredan de la anterior).
     */
    public function up(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            $table->timestamp('approved_at')->nullable()->after('is_current');
        });

        $regulations = DB::table('regulations')->get(['id', 'approval_status', 'direct_approved_at', 'updated_at']);

        foreach ($regulations as $regulation) {
            $versions = DB::table('regulation_versions')
                ->where('regulation_id', $regulation->id)
                ->whereNull('deleted_at')
                ->orderByDesc('version_number')
                ->get(['id', 'is_current', 'valid_until', 'updated_at']);

            if (in_array($regulation->approval_status, ['approved', 'outdated'], true)) {
                $approved = $versions->firstWhere('is_current', true) ?? $versions->first();
                $when = $regulation->direct_approved_at ?? $regulation->updated_at;
            } else {
                $approved = $versions->first(fn ($v) => ! $v->is_current && $v->valid_until !== null);
                $when = $approved?->updated_at;
            }

            if ($approved) {
                DB::table('regulation_versions')->where('id', $approved->id)->update(['approved_at' => $when ?? now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('regulation_versions', function (Blueprint $table) {
            $table->dropColumn('approved_at');
        });
    }
};
