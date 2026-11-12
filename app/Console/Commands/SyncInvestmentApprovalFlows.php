<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\InvestmentApprovalStep;
use App\Models\InvestmentEntity;
use App\Models\User;
use App\Services\InvestmentApprovalService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Carga la lista de razones sociales de Anexos 8 y su flujo fijo de aprobación desde
 * database/seeders/data/investment_approval_flows.php. Se puede correr las veces que haga falta:
 * actualiza cada razón social (empresa del sistema, orden), rehace su flujo y liga cada puesto con
 * el usuario que tenga ese nombre (o correo) en el grupo. Las razones sociales que ya no estén en
 * el archivo se ocultan de la lista (no se borran: puede haber solicitudes que las usen).
 */
class SyncInvestmentApprovalFlows extends Command
{
    protected $signature = 'investments:sync-approval-flows
        {--group= : ID del grupo (por omisión, el de las empresas del archivo)}
        {--dry-run : Solo muestra lo que haría, sin guardar}';

    protected $description = 'Carga las razones sociales de Anexos 8 y su flujo fijo de aprobación';

    public function handle(): int
    {
        $rows   = require database_path('seeders/data/investment_approval_flows.php');
        $dryRun = (bool) $this->option('dry-run');

        $allCompanies = Company::withoutGlobalScope('restricted')->get();
        $groupId = $this->option('group') ? (int) $this->option('group') : $this->guessGroup($rows, $allCompanies);

        if (! $groupId) {
            $this->error('No se encontró el grupo: ninguna empresa del archivo existe. Usa --group=ID.');

            return self::FAILURE;
        }

        $companies = $allCompanies->where('group_id', $groupId)
            ->groupBy(fn (Company $c) => $this->normalizeCompany($c->name));
        $users = User::query()->where('group_id', $groupId)->get(['id', 'name', 'email', 'group_id', 'module_access', 'role_id']);

        $table = [];
        $warnings = [];
        $keptIds = [];

        foreach (array_values($rows) as $index => $row) {
            $company = null;

            if ($row['company']) {
                $matches = $companies->get($this->normalizeCompany($row['company']), collect());

                if ($matches->count() > 1) {
                    $warnings[] = "\"{$row['company']}\" coincide con {$matches->count()} empresas (ids {$matches->pluck('id')->implode(', ')}); se usa la primera.";
                } elseif ($matches->isEmpty()) {
                    $warnings[] = "La empresa \"{$row['company']}\" de \"{$row['name']}\" no existe en el grupo: se muestra sin empresa.";
                }

                $company = $matches->first();
            }

            $entity = InvestmentEntity::firstOrNew(['group_id' => $groupId, 'name' => $row['name']]);
            $entity->fill([
                'company_id' => $company?->id,
                'holding'    => $row['holding'] ?? null,
                'unit'       => $row['unit'] ?? null,
                'sort_order' => $index + 1,
                'is_active'  => true,
            ]);

            $steps = $this->buildSteps($row, $entity, $users, $warnings);

            if (! $dryRun) {
                DB::transaction(function () use ($entity, $steps) {
                    $entity->save();
                    InvestmentApprovalStep::where('investment_entity_id', $entity->id)->delete();
                    foreach ($steps as $step) {
                        InvestmentApprovalStep::create($step + ['investment_entity_id' => $entity->id]);
                    }
                });
                $keptIds[] = $entity->id;
            }

            $table[] = [
                $row['name'],
                $company ? "{$company->name} (#{$company->id})" : '— (grupo)',
                collect($steps)->map(fn ($s) => "{$s['position']}: " . ($s['user_id'] ? '✓ ' : '✗ ') . $s['approver_name'])->implode(' → '),
            ];
        }

        if (! $dryRun) {
            InvestmentEntity::where('group_id', $groupId)->whereNotIn('id', $keptIds)->update(['is_active' => false]);
        }

        $this->table(['Razón social', 'Empresa del sistema', 'Flujo (✓ con usuario, ✗ sin usuario)'], $table);

        foreach (array_unique($warnings) as $warning) {
            $this->warn($warning);
        }

        $this->info($dryRun ? 'Simulación: no se guardó nada.' : 'Flujos guardados.');

        return self::SUCCESS;
    }

    // El grupo al que pertenecen más empresas del archivo.
    private function guessGroup(array $rows, Collection $companies): ?int
    {
        $names = collect($rows)->pluck('company')->filter()->map(fn ($n) => $this->normalizeCompany($n))->all();

        return $companies
            ->filter(fn (Company $c) => in_array($this->normalizeCompany($c->name), $names, true))
            ->countBy('group_id')
            ->sortDesc()
            ->keys()
            ->first();
    }

    /** @return array<int, array{sequence_order:int, position:string, approver_name:string, user_id:?int}> */
    private function buildSteps(array $row, InvestmentEntity $entity, Collection $users, array &$warnings): array
    {
        // Ligas que ya estaban (p. ej. hechas a mano en la base) se respetan si el nombre no cambió.
        $previous = $entity->exists
            ? InvestmentApprovalStep::where('investment_entity_id', $entity->id)->whereNotNull('user_id')->get()
                ->keyBy(fn ($s) => $s->position . '|' . $s->approver_name)
            : collect();

        $steps = [];

        foreach (InvestmentApprovalService::POSITIONS as $position) {
            $name = trim((string) ($row[$position] ?? ''));

            if ($name === '' || strtoupper($name) === 'NA') {
                continue;
            }

            $userId = $previous->get("{$position}|{$name}")?->user_id
                ?? $this->resolveUser($name, $users, $warnings)?->id;

            $steps[] = [
                'sequence_order' => count($steps) + 1,
                'position'       => $position,
                'approver_name'  => $name,
                'user_id'        => $userId,
            ];
        }

        return $steps;
    }

    private function resolveUser(string $name, Collection $users, array &$warnings): ?User
    {
        $matches = str_contains($name, '@')
            ? $users->filter(fn (User $u) => strcasecmp($u->email, $name) === 0)
            : $users->filter(fn (User $u) => $this->normalizePerson($u->name) === $this->normalizePerson($name));

        if ($matches->count() > 1) {
            $warnings[] = "Hay {$matches->count()} usuarios llamados \"{$name}\"; usa su correo en el archivo para elegir uno.";

            return null;
        }

        // Sin acceso a Cumplimiento también puede aprobar: lo ve desde Procesos y Auditorías.
        return $matches->first();
    }

    private function normalizeCompany(string $name): string
    {
        $name = Str::upper(Str::ascii($name));
        $name = preg_replace('/\bS\.?\s*A\.?\s*(DE\s*C\.?\s*V\.?)?$/', '', trim($name));

        return trim(preg_replace('/[^A-Z0-9]+/', ' ', $name));
    }

    private function normalizePerson(string $name): string
    {
        return trim(preg_replace('/[^A-Z0-9]+/', ' ', Str::upper(Str::ascii($name))));
    }
}
