<?php

namespace Database\Seeders;

use App\Models\Asset;
use Illuminate\Database\Seeder;

/**
 * Deja en blanco el responsable de todos los activos energéticos — actualmente
 * quedó precargado con el usuario que los creó, en vez de estar vacío para que
 * se asigne manualmente al editar cada activo.
 */
class ClearAssetResponsibleSeeder extends Seeder
{
    public function run(): void
    {
        $updated = Asset::whereNotNull('responsible_user_id')->update(['responsible_user_id' => null]);

        $this->command?->info("Responsable limpiado en {$updated} activo(s).");
    }
}
