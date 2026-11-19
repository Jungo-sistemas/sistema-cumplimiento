<?php

namespace Tests\Feature;

use App\Support\EmailPreviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** /dev/correos: vista previa de los correos con datos de ejemplo, solo en desarrollo. */
class EmailPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_correo_se_genera_con_la_plantilla_de_jungo_sin_enviar_ni_guardar_nada(): void
    {
        Mail::fake();
        Notification::fake();
        $tables = ['users', 'regulations', 'regulation_approvals', 'audits', 'audit_findings', 'investment_requests', 'licenses', 'asset_requirements', 'regulation_shares'];
        $before = collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

        $previews = app(EmailPreviews::class);
        $keys = array_keys($previews->catalog());
        $this->assertGreaterThanOrEqual(29, count($keys));

        foreach ($keys as $key) {
            ['subject' => $subject, 'html' => $html] = $previews->render($key);

            $this->assertNotSame('', $subject, "El correo {$key} no tiene asunto.");
            $this->assertStringContainsString('alt="Jungo Cumple"', $html, "El correo {$key} no usa la plantilla de Jungo.");
            $this->assertStringNotContainsString('VIGIA', $html, "El correo {$key} todavía dice VIGIA.");
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
        foreach ($tables as $table) {
            $this->assertSame($before[$table], DB::table($table)->count(), "Se escribió en {$table}.");
        }
    }

    public function test_fuera_de_desarrollo_la_ruta_no_existe(): void
    {
        $this->assertFalse(app()->environment('local'));
        $this->get('/dev/correos')->assertNotFound();
        $this->get('/dev/correos/approval-requested')->assertNotFound();
    }
}
