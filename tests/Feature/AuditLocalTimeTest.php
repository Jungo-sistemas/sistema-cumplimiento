<?php

namespace Tests\Feature;

use App\Models\Audit;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * La fecha de la auditoría se escribe en hora de México y el sistema corre en UTC (6 h adelante):
 * "hoy a las 9:30" a las 9:14 de México no puede salir como "ya pasó".
 */
class AuditLocalTimeTest extends TestCase
{
    public function test_la_hora_de_la_auditoria_se_compara_con_la_hora_de_mexico(): void
    {
        config(['app.timezone' => 'UTC', 'auditorias.zona_horaria' => 'America/Mexico_City']);
        $this->travelTo(Carbon::create(2026, 11, 25, 15, 14, 0, 'UTC')); // 9:14 en México

        $this->assertSame('2026-11-25 09:14', Audit::localNow()->format('Y-m-d H:i'));
        $this->assertFalse(Audit::isPastLocal(Carbon::parse('2026-11-25 09:30'))); // en 16 minutos: se puede
        $this->assertTrue(Audit::isPastLocal(Carbon::parse('2026-11-25 09:00')));  // ya pasó
        $this->assertFalse(Audit::isPastLocal(Carbon::parse('2026-11-25 14:00'))); // antes lo rechazaba

        // Ya es "hoy" para el auditor: lista para iniciar.
        $audit = new Audit(['status' => Audit::STATUS_PLANNED, 'scheduled_at' => Carbon::parse('2026-11-25 09:30')]);
        $this->assertSame(Audit::STAGE_READY, $audit->auditorStage());
    }
}
