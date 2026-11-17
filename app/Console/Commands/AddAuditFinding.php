<?php

namespace App\Console\Commands;

use App\Models\Audit;
use App\Models\User;
use App\Services\AuditFindingService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Registra un hallazgo como lo hará el auditor durante la auditoría (su pantalla viene después).
 *
 *   php artisan audits:add-finding 2 ana@empresa.com "Paso 4: bitácora" "No se llenó la bitácora de descarga" --severidad=mayor --vence=2026-12-01
 */
class AddAuditFinding extends Command
{
    protected $signature = 'audits:add-finding {audit : id de la auditoría} {responsable : correo del responsable (uno de los auditados)}
                            {punto : punto del proceso} {descripcion : lo que anotó el auditor}
                            {--severidad= : critica, mayor o menor} {--vence= : fecha límite AAAA-MM-DD}';

    protected $description = 'Registra un hallazgo de auditoría y avisa al responsable (simula al auditor)';

    public function handle(AuditFindingService $service): int
    {
        $audit = Audit::find($this->argument('audit'));
        $responsible = User::where('email', $this->argument('responsable'))->first();

        if (! $audit || ! $responsible) {
            $this->error(! $audit ? 'No existe esa auditoría.' : 'No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        try {
            $finding = $service->register(
                $audit, $responsible, $this->argument('punto'), $this->argument('descripcion'),
                $this->option('severidad') ?: null,
                $this->option('vence') ? Carbon::createFromFormat('Y-m-d', $this->option('vence'))->startOfDay() : null,
            );
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Hallazgo #{$finding->id} registrado; se avisó a {$responsible->name}.");

        return self::SUCCESS;
    }
}
