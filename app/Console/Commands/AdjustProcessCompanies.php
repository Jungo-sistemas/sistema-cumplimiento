<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Ajuste de empresas en Procesos que pidió el cliente (nov 2026): oculta algunas, les cambia el
 * nombre a otras solo en Procesos (process_name; en Cumplimiento siguen igual) y agrega JUNGO y
 * PACTO. Busca cada empresa por nombre porque en producción los nombres pueden variar un poco
 * ("REAL STATE"/"REAL ESTATE"); si un nombre no encuentra exactamente una empresa, la salta y
 * avisa, para no tocar la que no es. Ocultar no borra nada: solo show_in_processes = false.
 */
class AdjustProcessCompanies extends Command
{
    protected $signature = 'procesos:ajustar-empresas
                            {--group= : Grupo a ajustar (por defecto, el de la empresa VIDENCI)}
                            {--dry-run : Muestra qué cambiaría sin guardar nada}';

    protected $description = 'Oculta, renombra (solo en Procesos) y agrega las empresas que pidió el cliente en Procesos';

    /** Patrones LIKE (en mayúsculas) de las empresas que dejan de verse en Procesos. */
    private const HIDE = [
        'COMBUSTIBLES TG',
        'FISCAL',
        'VERITAS%',
        '%VILLA DE REYES%',
        'TERRENOS TODOGAS',
        'TI',
        'TOTAL PROPANE',
        'MIGAR REAL%',
    ];

    /** Patrón LIKE => nombre que se muestra en Procesos. */
    private const RENAME = [
        'VIDENCI'       => 'SAVSA',
        'MIGAR CAPITAL' => 'MIGAR',
        'ASESOR%DAVAL'  => 'DAVAL',
    ];

    /** Empresas que deben verse en Procesos; se crean si no existen. */
    private const ADD = ['JUNGO', 'PACTO'];

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $groupId = (int) ($this->option('group') ?: $this->companies()->whereRaw('UPPER(name) = ?', ['VIDENCI'])->value('group_id'));

        if (! $groupId) {
            $this->error('No encontré la empresa VIDENCI para saber el grupo. Pásalo con --group=ID.');

            return self::FAILURE;
        }

        $this->info(($dryRun ? '[Prueba] ' : '') . "Grupo {$groupId}");

        foreach (self::HIDE as $pattern) {
            if ($company = $this->findOne($groupId, $pattern)) {
                $this->line("Ocultar: {$company->name}" . ($company->show_in_processes ? '' : ' (ya estaba oculta)'));
                $dryRun || $company->update(['show_in_processes' => false]);
            }
        }

        foreach (self::RENAME as $pattern => $processName) {
            if ($company = $this->findOne($groupId, $pattern)) {
                $this->line("Renombrar en Procesos: {$company->name} → {$processName}");
                $dryRun || $company->update(['process_name' => $processName, 'show_in_processes' => true]);
            }
        }

        foreach (self::ADD as $name) {
            $company = $this->companies($groupId)->whereRaw('UPPER(name) = ?', [$name])->first();

            if ($company) {
                $this->line("Mostrar en Procesos: {$company->name}" . ($company->show_in_processes ? ' (ya se mostraba)' : ''));
                $dryRun || $company->update(['show_in_processes' => true]);
                continue;
            }

            $this->line("Crear empresa: {$name}");
            $dryRun || Company::create(['name' => $name, 'group_id' => $groupId, 'show_in_processes' => true, 'is_active' => true]);
        }

        $this->info($dryRun ? 'No se guardó nada. Corre sin --dry-run para aplicar.' : 'Listo.');

        return self::SUCCESS;
    }

    private function companies(?int $groupId = null): Builder
    {
        return Company::query()
            ->when($groupId, fn ($q) => $q->where('group_id', $groupId))
            ->where('otras', false);
    }

    /** La única empresa del grupo cuyo nombre coincide; si hay 0 o varias, avisa y no regresa ninguna. */
    private function findOne(int $groupId, string $pattern): ?Company
    {
        $matches = $this->companies($groupId)->whereRaw('UPPER(TRIM(name)) LIKE ?', [$pattern])->get();

        if ($matches->count() === 1) {
            return $matches->first();
        }

        $this->warn($matches->isEmpty()
            ? "No encontré ninguna empresa como «{$pattern}»: se salta."
            : "«{$pattern}» coincide con varias empresas (" . $matches->pluck('name')->implode(', ') . '): se salta, ajústala a mano.');

        return null;
    }
}
