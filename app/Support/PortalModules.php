<?php

namespace App\Support;

use App\Models\User;

/**
 * Módulos del portal Jungo Cumple (nov 2026), en el orden del selector del encabezado:
 *
 *   Cumplimiento Fiscal      Documentos generales, Expediente Fiscal y Legal y Agente InfoSAT/MP12
 *   Cumplimiento Regulatorio Tablero, activos y actividades, marcas y terrenos
 *   Objetivos                Planeación estratégica, cascadeo y Hoshin
 *   Procesos y Auditorías    igual que antes
 *   Flujos                   Inversiones (Anexos 8), compras y gastos de Tarjeta Clara
 *   Cursos
 *
 * El acceso sigue siendo el de siempre en el alta de usuarios (Cumplimiento / Procesos / Ambos):
 * "Cumplimiento" da todo lo que no es Procesos y Auditorías. Auditorías (Mi espacio) le queda a
 * todos con la licencia de Procesos.
 */
class PortalModules
{
    public const LABELS = [
        'fiscal'      => 'Cumplimiento Fiscal',
        'regulatorio' => 'Cumplimiento Regulatorio',
        'objetivos'   => 'Objetivos',
        'procesos'    => 'Procesos y Auditorías',
        'flujos'      => 'Flujos',
        'cursos'      => 'Cursos',
    ];

    /** Secciones que aún no existen: salen en el menú y abren una página de "Próximamente". */
    public const COMING_SOON = [
        'expediente-fiscal'      => ['module' => 'fiscal',      'label' => 'Expediente Fiscal y Legal',          'icon' => 'folder'],
        'mcp-infosat'            => ['module' => 'fiscal',      'label' => 'Agente InfoSAT y MP12',              'icon' => 'chip'],
        'marcas'                 => ['module' => 'regulatorio', 'label' => 'Marcas',                             'icon' => 'tag'],
        'terrenos'               => ['module' => 'regulatorio', 'label' => 'Terrenos',                           'icon' => 'map'],
        'planeacion-estrategica' => ['module' => 'objetivos',   'label' => 'Planeación Estratégica',             'icon' => 'trend'],
        'cascadeo'               => ['module' => 'objetivos',   'label' => 'Cascadeo',                           'icon' => 'layers'],
        'hoshin'                 => ['module' => 'objetivos',   'label' => 'Hoshin',                             'icon' => 'flag'],
        'compras'                => ['module' => 'flujos',      'label' => 'Compras',                            'icon' => 'cart'],
        'tarjeta-clara'          => ['module' => 'flujos',      'label' => 'Administración de Gastos de Tarjeta Clara', 'icon' => 'card'],
        'cursos'                 => ['module' => 'cursos',      'label' => 'Cursos',                             'icon' => 'academic'],
    ];

    /** Página de inicio de cada módulo (nombre de ruta y parámetros). */
    public static function home(string $module): array
    {
        return match ($module) {
            'fiscal'      => ['documents.index', []],
            'regulatorio' => ['dashboard', []],
            'objetivos'   => ['coming-soon', ['section' => 'planeacion-estrategica']],
            'procesos'    => ['gestion.home', []],
            'flujos'      => ['investment-requests.index', []],
            'cursos'      => ['coming-soon', ['section' => 'cursos']],
        };
    }

    /** Con $user: el aprobador de Anexos 8 sin Cumplimiento entra a Flujos por sus aprobaciones. */
    public static function homeUrl(string $module, ?User $user = null): string
    {
        if ($module === 'flujos' && $user && ! $user->isSuperAdmin() && ! $user->canAccessModule('cumplimiento')) {
            return route('investment-requests.my-approvals');
        }

        [$route, $params] = self::home($module);

        return route($route, $params);
    }

    /** Módulo al que pertenece la página actual; null en las que son de todos (Usuarios, Mi perfil…). */
    public static function forCurrentRoute(): ?string
    {
        $request = request();

        return match (true) {
            $request->routeIs('gestion.*', 'auditorias.*', 'processes.*', 'my-approvals.*', 'regulation-versions.*') => 'procesos',
            $request->routeIs('documents.index', 'documents.show', 'documents.trash.*', 'documents.report.*') => 'fiscal',
            $request->routeIs('dashboard', 'assets.*', 'requirements.*', 'tasks.*') => 'regulatorio',
            $request->routeIs('investment-requests.*') => 'flujos',
            $request->routeIs('coming-soon') => self::COMING_SOON[$request->route('section')]['module'] ?? null,
            default => null,
        };
    }

    /**
     * Fuera de Procesos y Auditorías todo va con el acceso "Cumplimiento". Flujos además lo ve quien
     * aprueba Anexos 8 aunque no tenga Cumplimiento (solo con sus aprobaciones).
     */
    public static function canEnter(User $user, string $module, bool $isInvestmentApprover = false): bool
    {
        return match ($module) {
            'procesos' => $user->canUseProcesos(),
            'flujos'   => $user->canAccessModule('cumplimiento') || $isInvestmentApprover,
            default    => $user->canAccessModule('cumplimiento'),
        };
    }
}
