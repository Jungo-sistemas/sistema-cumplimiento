<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Días hábiles para los plazos de auditorías: lunes a viernes, sin los días de descanso obligatorio
 * del artículo 74 de la LFT ni los de config('auditorias.dias_inhabiles').
 */
class BusinessDays
{
    /** Fecha (inicio del día) que queda $days días hábiles después de $from. 0 = el mismo día. */
    public static function add(CarbonInterface $from, int $days): Carbon
    {
        $date = Carbon::instance($from)->startOfDay();

        while ($days > 0) {
            $date->addDay();
            if (self::isBusinessDay($date)) {
                $days--;
            }
        }

        return $date;
    }

    public static function isBusinessDay(CarbonInterface $date): bool
    {
        return ! $date->isWeekend() && ! in_array($date->toDateString(), self::holidays($date->year), true);
    }

    /** @return string[] Días inhábiles del año, AAAA-MM-DD. */
    public static function holidays(int $year): array
    {
        $nth = fn (int $month, int $n) => Carbon::create($year, $month, 1)->nthOfMonth($n, CarbonInterface::MONDAY)->toDateString();

        $days = [
            "{$year}-01-01",
            $nth(2, 1),          // 5 de febrero: primer lunes de febrero
            $nth(3, 3),          // 21 de marzo: tercer lunes de marzo
            "{$year}-05-01",
            "{$year}-09-16",
            $nth(11, 3),         // 20 de noviembre: tercer lunes de noviembre
            "{$year}-12-25",
        ];

        // Transmisión del Poder Ejecutivo Federal: 1 de octubre cada seis años (2024, 2030…).
        if ($year >= 2024 && ($year - 2024) % 6 === 0) {
            $days[] = "{$year}-10-01";
        }

        return array_merge($days, array_values((array) config('auditorias.dias_inhabiles', [])));
    }
}
