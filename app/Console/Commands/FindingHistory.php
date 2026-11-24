<?php

namespace App\Console\Commands;

use App\Models\AuditFinding;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Historial de hallazgos de una persona, para que el auditor lo revise antes de la siguiente
 * auditoría (su pantalla viene después). Incluye los cerrados y la bitácora de cada uno.
 *
 *   php artisan audits:historial-hallazgos ana@empresa.com
 */
class FindingHistory extends Command
{
    protected $signature = 'audits:historial-hallazgos {responsable : correo de la persona} {--detalle : Mostrar la bitácora de cada hallazgo}';

    protected $description = 'Lista los hallazgos de auditoría de una persona (abiertos y cerrados) con su bitácora';

    public function handle(): int
    {
        $user = User::where('email', $this->argument('responsable'))->first();
        if (! $user) {
            $this->error('No existe un usuario con ese correo.');

            return self::FAILURE;
        }

        $findings = AuditFinding::forResponsible($user)
            ->with(['audit.regulation', 'events.user:id,name'])
            ->orderByDesc('id')
            ->get();

        if ($findings->isEmpty()) {
            $this->info("{$user->name} no tiene hallazgos.");

            return self::SUCCESS;
        }

        $this->table(
            ['#', 'Fecha', 'Proceso', 'Punto', 'Severidad', 'Estado', 'Devoluciones'],
            $findings->map(fn (AuditFinding $f) => [
                $f->id,
                $f->created_at->format('d/m/Y'),
                $f->audit->regulation?->code ?? '—',
                \Illuminate\Support\Str::limit($f->point, 40),
                $f->severityLabel(),
                $f->status === AuditFinding::STATUS_CLOSED ? 'Cerrado ' . $f->closed_at?->format('d/m/Y') : $f->stageLabel() . ($f->isOverdue() ? ' (vencido)' : ''),
                $f->events->whereIn('action', ['causa_devuelta', 'evidencia_rechazada'])->count(),
            ])
        );

        if ($this->option('detalle')) {
            foreach ($findings as $finding) {
                $this->line("\n#{$finding->id} {$finding->point}");
                foreach ($finding->events as $event) {
                    $this->line(sprintf('  %s · %s: %s%s', $event->created_at->format('d/m/Y H:i'), $event->user?->name ?? 'Sistema', $event->label(), $event->comment ? " — {$event->comment}" : ''));
                }
            }
        }

        return self::SUCCESS;
    }
}
