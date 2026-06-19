<?php

namespace Database\Seeders;

use App\Models\Group;
use App\Models\JobPosition;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class JobPositionSeeder extends Seeder
{
    /**
     * Puestos jerárquicos de menor a mayor.
     * El orden bottom-up define los pasos del flujo de aprobación.
     */
    private const POSITIONS = [
        ['slug' => 'lider',     'name' => 'Líder',      'sort_order' => 1],
        ['slug' => 'jefe',      'name' => 'Jefe',        'sort_order' => 2],
        ['slug' => 'gerente',   'name' => 'Gerente',     'sort_order' => 3],
        ['slug' => 'direccion', 'name' => 'Dirección',   'sort_order' => 4],
    ];

    /** Slugs obsoletos que se renombran al nuevo slug. */
    private const RENAMES = [
        'ejecutivo_reglamentos' => 'jefe',
        'direccion_general'     => 'direccion',
    ];

    /** Slugs que se eliminan (sus usuarios se mueven a RENAMES destino si aplica). */
    private const REMOVE = ['director_finanzas'];

    public function run(): void
    {
        foreach (Group::all() as $group) {

            // 1. Renombrar puestos existentes al nuevo slug/name/sort_order
            foreach (self::RENAMES as $oldSlug => $newSlug) {
                $target = collect(self::POSITIONS)->firstWhere('slug', $newSlug);
                if (! $target) {
                    continue;
                }

                $existing = JobPosition::where('group_id', $group->id)->where('slug', $oldSlug)->first();
                if ($existing) {
                    $existing->update([
                        'slug'       => $target['slug'],
                        'name'       => $target['name'],
                        'sort_order' => $target['sort_order'],
                    ]);
                }
            }

            // 2. Eliminar puestos obsoletos (mover usuarios a 'direccion' si corresponde)
            foreach (self::REMOVE as $removeSlug) {
                $obsolete = JobPosition::where('group_id', $group->id)->where('slug', $removeSlug)->first();
                if (! $obsolete) {
                    continue;
                }

                $destination = JobPosition::where('group_id', $group->id)->where('slug', 'direccion')->first();
                if ($destination) {
                    $alreadyIn = DB::table('user_job_positions')
                        ->where('job_position_id', $destination->id)
                        ->pluck('user_id')
                        ->all();

                    DB::table('user_job_positions')
                        ->where('job_position_id', $obsolete->id)
                        ->whereNotIn('user_id', $alreadyIn)
                        ->update(['job_position_id' => $destination->id]);
                }

                DB::table('user_job_positions')->where('job_position_id', $obsolete->id)->delete();
                $obsolete->delete();
            }

            // 3. Crear o actualizar los 4 puestos finales
            foreach (self::POSITIONS as $position) {
                JobPosition::updateOrCreate(
                    ['group_id' => $group->id, 'slug' => $position['slug']],
                    ['name' => $position['name'], 'sort_order' => $position['sort_order'], 'is_active' => true]
                );
            }
        }
    }
}
