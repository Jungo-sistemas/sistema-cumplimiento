<?php

namespace App\Console\Commands;

use App\Models\Audit;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Registra el día y la hora de una auditoría — lo que hará el auditor desde su pantalla
 * cuando exista el lado del auditor. Mientras tanto sirve para probar la vista del auditado.
 *
 *   php artisan audits:set-date 12 "2026-11-18 10:00"
 */
class SetAuditDate extends Command
{
    protected $signature = 'audits:set-date {audit : id de la auditoría} {fecha : "AAAA-MM-DD HH:MM"}';

    protected $description = 'Registra (o cambia) la fecha y hora de una auditoría (el aviso al auditado sale días antes)';

    public function handle(): int
    {
        $audit = Audit::with('regulation')->find($this->argument('audit'));
        if (! $audit) {
            $this->error('No existe esa auditoría.');

            return self::FAILURE;
        }

        if ($audit->status !== Audit::STATUS_PLANNED) {
            $this->error("La auditoría ya está {$audit->status}; no se le puede cambiar la fecha.");

            return self::FAILURE;
        }

        try {
            $at = Carbon::createFromFormat('Y-m-d H:i', $this->argument('fecha'));
        } catch (\Throwable) {
            $this->error('Fecha inválida. Usa el formato "AAAA-MM-DD HH:MM", p. ej. "2026-11-18 10:00".');

            return self::FAILURE;
        }

        $audit->schedule($at);

        $this->info(sprintf('%s: fecha registrada para el %s.', $audit->regulation?->code ?? "Auditoría #{$audit->id}", $audit->scheduledLabel()));
        $audit->refresh();
        if ($audit->notice_sent_at === null) {
            $this->line(sprintf('Todavía no se avisa: al auditado le llegará el aviso %d días antes de la fecha.', Audit::noticeDays()));
        }

        return self::SUCCESS;
    }
}
