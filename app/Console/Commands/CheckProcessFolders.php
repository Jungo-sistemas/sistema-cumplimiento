<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\Regulation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Revisa que cada proceso esté en una carpeta de Procesos: en una empresa visible en Procesos
 * (o la carpeta DIRECCIÓN) y con un tipo de proceso activo de su mismo grupo. Los que no
 * cumplen no aparecen en ninguna carpeta. Solo consulta; no cambia nada. Incluye los
 * confidenciales y los obsoletos; los de la papelera no.
 */
class CheckProcessFolders extends Command
{
    protected $signature = 'procesos:revisar-carpetas {--group= : Solo este grupo}';

    protected $description = 'Lista los procesos que no quedaron en ninguna carpeta (empresa oculta o sin tipo de proceso válido)';

    public function handle(): int
    {
        $types     = DB::table('process_types')->get()->keyBy('id');
        $companies = Company::withoutGlobalScopes()->get()->keyBy('id');

        $regulations = Regulation::withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->when($this->option('group'), fn ($q, $groupId) => $q->where('group_id', $groupId))
            ->orderBy('company_id')->orderBy('code')
            ->get();

        $loose = $regulations->map(function (Regulation $regulation) use ($types, $companies) {
            $company = $companies->get($regulation->company_id);
            $type    = $regulation->process_type_id ? $types->get($regulation->process_type_id) : null;
            $reasons = [];

            if (! $company) {
                $reasons[] = 'Sin empresa';
            } elseif ($company->otras || ! $company->show_in_processes) {
                $reasons[] = 'Empresa oculta en Procesos';
            }

            if ($regulation->process_type_id === null) {
                $reasons[] = 'Sin tipo de proceso';
            } elseif ($type === null) {
                $reasons[] = 'Su tipo ya no existe';
            } elseif ((int) $type->group_id !== (int) $regulation->group_id) {
                $reasons[] = "Tipo de otro grupo ({$type->name})";
            } elseif (! $type->is_active) {
                $reasons[] = "Tipo oculto ({$type->name})";
            }

            return $reasons ? [
                $regulation->id,
                $company?->procesos_name ?? '—',
                $regulation->code ?? '—',
                mb_strimwidth((string) $regulation->name, 0, 45, '…'),
                $regulation->is_annex ? 'Anexo' : 'Proceso',
                implode(' · ', $reasons) . ($regulation->obsoleted_at ? ' · en Obsoleto' : ''),
            ] : null;
        })->filter()->values();

        $this->info("Revisados: {$regulations->count()} procesos y anexos (sin contar la papelera).");

        if ($loose->isEmpty()) {
            $this->info('Todos están en una carpeta.');

            return self::SUCCESS;
        }

        $this->warn("{$loose->count()} no están en ninguna carpeta:");
        $this->table(['ID', 'Empresa', 'Código', 'Nombre', 'Es', 'Motivo'], $loose->all());
        $this->line('Para acomodarlos: elige un tipo del catálogo al editarlos, o muévelos a una empresa visible en Procesos.');

        return self::FAILURE;
    }
}
