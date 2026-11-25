<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Http\Request;

/**
 * Vista del Jefe de Auditoría (JD-924): planea el programa anual de auditorías. Por ahora solo el
 * Inicio lee datos reales (las auditorías de su grupo); las demás pantallas siguen "en construcción".
 */
class JefeAuditoriaController extends Controller
{
    public function home(Request $request)
    {
        $user = $request->user();
        // Las fechas se capturan en hora de México: "hoy" también, para no cambiar de mes antes de tiempo.
        $hoy = now('America/Mexico_City');

        // Cuándo toca cada auditoría: el día acordado o, si aún no lo hay, el mes del plan. Al cambiar
        // la fecha no se actualiza planned_for; por eso manda scheduled_at.
        $fecha = fn (Audit $audit) => $audit->scheduled_at ?? $audit->planned_for;

        // Las del año en su grupo, sin las canceladas.
        $delAnio = Audit::where('group_id', $user->group_id)
            ->where('status', '!=', Audit::STATUS_CANCELLED)
            ->with(['regulation', 'company', 'auditor'])
            ->get()
            ->filter(fn (Audit $audit) => $fecha($audit)->year === $hoy->year);

        $realizadas = $delAnio->where('status', Audit::STATUS_COMPLETED)->count();

        return view('auditorias.jefe.home', [
            'anio'       => $hoy->year,
            'mes'        => $hoy->locale('es')->isoFormat('MMMM [de] YYYY'),
            'total'      => $delAnio->count(),
            'realizadas' => $realizadas,
            'avance'     => $delAnio->isEmpty() ? 0 : (int) round($realizadas * 100 / $delAnio->count()),
            'sinFecha'   => $delAnio->where('status', Audit::STATUS_PLANNED)->whereNull('scheduled_at')->count(),
            'delMes'     => $delAnio->filter(fn (Audit $audit) => $fecha($audit)->month === $hoy->month)
                ->sortBy(fn (Audit $audit) => $fecha($audit)->timestamp)
                ->values(),
        ]);
    }
}
