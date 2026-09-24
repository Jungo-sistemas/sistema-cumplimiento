<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\DocumentFolder;
use App\Models\Group;
use App\Models\JobPosition;
use App\Models\ProcessType;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * INTERA quedó dado de alta como un grupo (group_id) totalmente aparte de VIGIA, en vez de sus
 * empresas vivir dentro del grupo VIGIA como corresponde organizacionalmente. Como nadie tiene
 * group_id=INTERA, sus empresas son invisibles en Procesos (el listado de empresas siempre filtra
 * por el grupo del usuario) — este comando las reasigna al grupo VIGIA y limpia el grupo INTERA
 * (y sus catálogos de Tipo de Proceso/Puestos, duplicados y sin uso) una vez que se queda vacío.
 *
 * Seguro de correr más de una vez: si el grupo INTERA ya no existe, no hace nada.
 */
class MergeInteraGroupIntoVigia extends Command
{
    protected $signature = 'groups:merge-intera-into-vigia {--dry-run : Muestra lo que se haría sin escribir en base de datos}';

    protected $description = 'Mueve las empresas del grupo INTERA al grupo VIGIA y elimina el grupo INTERA (vacío) y sus catálogos huérfanos.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $intera = Group::where('name', 'INTERA')->first();
        if (! $intera) {
            $this->info('No existe un grupo "INTERA" — no hay nada que hacer (¿ya se corrió este comando antes?).');
            return self::SUCCESS;
        }

        $vigia = Group::where('name', 'VIGIA')->first();
        if (! $vigia) {
            $this->error('No existe un grupo "VIGIA" — no se puede continuar.');
            return self::FAILURE;
        }

        $companies = Company::where('group_id', $intera->id)->get();
        $usersInIntera = User::where('group_id', $intera->id)->count();

        $this->info("Empresas en INTERA (id {$intera->id}) a mover a VIGIA (id {$vigia->id}):");
        foreach ($companies as $c) {
            $this->line("  - #{$c->id} {$c->name}");
        }

        if ($usersInIntera > 0) {
            $this->error("Hay {$usersInIntera} usuario(s) todavía asignados al grupo INTERA — revisa eso a mano antes de continuar (este comando no los mueve, para no reasignar acceso de nadie sin que se decida explícitamente).");
            return self::FAILURE;
        }

        $processTypes = ProcessType::where('group_id', $intera->id)->get();
        $jobPositions = JobPosition::where('group_id', $intera->id)->get();

        // Carpetas raíz de "Documentos generales" — duplicadas del mismo catálogo estándar que
        // ya tiene VIGIA (mismos nombres, otros IDs). Solo se eliminan si de verdad están vacías
        // (sin documentos ni subcarpetas propias) — si algún día alguien les agrega contenido
        // antes de correr esto, hay que decidir aparte qué hacer con ellas.
        $folders = DocumentFolder::where('group_id', $intera->id)->get();
        foreach ($folders as $folder) {
            $hasChildren = DocumentFolder::where('parent_id', $folder->id)->exists();
            $hasDocuments = DB::table('documents')->where('document_folder_id', $folder->id)->exists();
            if ($hasChildren || $hasDocuments) {
                $this->error("La carpeta \"{$folder->name}\" (id {$folder->id}) del grupo INTERA ya no está vacía — revísala a mano antes de continuar.");
                return self::FAILURE;
            }
        }

        if ($dryRun) {
            $this->info('[DRY RUN] Se moverían ' . $companies->count() . ' empresa(s), y se eliminarían '
                . $processTypes->count() . ' tipo(s) de proceso, ' . $jobPositions->count() . ' puesto(s), '
                . $folders->count() . ' carpeta(s) de documentos y el grupo INTERA — nada de esto se escribió.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($companies, $vigia, $processTypes, $jobPositions, $folders, $intera) {
            foreach ($companies as $c) {
                $c->update(['group_id' => $vigia->id]);
            }

            ProcessType::whereIn('id', $processTypes->pluck('id'))->delete();
            JobPosition::whereIn('id', $jobPositions->pluck('id'))->delete();
            DocumentFolder::whereIn('id', $folders->pluck('id'))->delete();
            $intera->delete();
        });

        $this->info('Listo: ' . $companies->count() . ' empresa(s) movidas a VIGIA, catálogos huérfanos y grupo INTERA eliminados.');

        return self::SUCCESS;
    }
}
