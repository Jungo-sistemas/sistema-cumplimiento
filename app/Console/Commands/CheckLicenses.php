<?php

namespace App\Console\Commands;

use App\Models\License;
use App\Models\Role;
use App\Models\User;
use App\Notifications\LicenseExpiredNotification;
use App\Notifications\LicenseExpiringNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class CheckLicenses extends Command
{
    protected $signature = 'licenses:check
                            {--dry-run : Muestra qué pasaría sin enviar correos ni quitar accesos}';

    protected $description = 'Manda recordatorios de vencimiento de licencia (7, 3 y 1 día) y quita el acceso a las que ya vencieron';

    // Días exactos en los que se dispara un recordatorio, y la columna que evita duplicarlo en el mismo ciclo.
    private const THRESHOLDS = [
        7 => 'reminder_7_sent_at',
        3 => 'reminder_3_sent_at',
        1 => 'reminder_1_sent_at',
    ];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $superadmins = User::whereHas('role', fn ($q) => $q->where('slug', 'superadmin'))->get();

        $this->expireOverdueLicenses($superadmins, $dryRun);
        $this->sendUpcomingReminders($superadmins, $dryRun);

        return self::SUCCESS;
    }

    private function expireOverdueLicenses($superadmins, bool $dryRun): void
    {
        $overdue = License::query()
            ->where('status', 'active')
            ->where('expires_at', '<=', now())
            ->with('licensable')
            ->get();

        foreach ($overdue as $license) {
            $name = $license->licensable?->name ?? "#{$license->licensable_id}";

            if ($dryRun) {
                $this->line("  [dry-run] EXPIRA → {$name} (license #{$license->id})");
                continue;
            }

            $license->update(['status' => 'expired']);
            $license->licensable?->update(['is_active' => false]);

            Notification::send($superadmins, new LicenseExpiredNotification($license));
            $this->info("Licencia vencida, acceso retirado: {$name}");
        }
    }

    private function sendUpcomingReminders($superadmins, bool $dryRun): void
    {
        $today = now()->startOfDay();

        foreach (self::THRESHOLDS as $days => $reminderColumn) {
            $targetDate = $today->copy()->addDays($days)->toDateString();

            $licenses = License::query()
                ->where('status', 'active')
                ->whereDate('expires_at', $targetDate)
                ->whereNull($reminderColumn)
                ->with('licensable')
                ->get();

            foreach ($licenses as $license) {
                $name = $license->licensable?->name ?? "#{$license->licensable_id}";

                if ($dryRun) {
                    $this->line("  [dry-run] {$days}d → {$name} (license #{$license->id})");
                    continue;
                }

                Notification::send($superadmins, new LicenseExpiringNotification($license, $days));
                $license->update([$reminderColumn => now()]);
            }

            if (!$dryRun && $licenses->isNotEmpty()) {
                $this->info("Recordatorio {$days}d enviado: " . $licenses->count() . " licencia(s)");
            }
        }
    }
}
