<?php

namespace App\Services;

use App\Models\JobPosition;
use App\Models\Regulation;
use App\Models\RegulationApproval;
use App\Models\User;
use App\Notifications\ApprovalRequestedNotification;
use App\Notifications\ApprovalStepUnassignedNotification;
use App\Notifications\RegulationApprovedNotification;
use App\Notifications\RegulationAccessGrantedNotification;
use App\Notifications\RegulationAccessRequestedNotification;
use App\Notifications\RegulationReadyToResubmitNotification;
use App\Notifications\RegulationRejectedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ApprovalFlowService
{
    /**
     * Flujos definidos por nivel de impacto.
     * Cada paso es un array de posición-slug => lógica.
     * 'requires_all' => true  = AND: todos los usuarios de TODOS los puestos deben aprobar.
     * 'requires_all' => false = OR:  cualquier usuario de cualquier puesto en el paso basta.
     */
    /**
     * Flujos bottom-up: cada paso espera a que el anterior se complete.
     * Jerarquía: lider (1) → jefe (2) → gerente (3) → direccion/consejo (4, mismo nivel: basta uno)
     */
    private const FLOWS = [
        'bajo' => [
            1 => ['requires_all' => true, 'positions' => ['lider']],
        ],
        'medio' => [
            1 => ['requires_all' => true,  'positions' => ['lider']],
            2 => ['requires_all' => false, 'positions' => ['jefe', 'gerente']],
        ],
        'medio_alto' => [
            1 => ['requires_all' => true,  'positions' => ['lider']],
            2 => ['requires_all' => true,  'positions' => ['gerente']],
            3 => ['requires_all' => false, 'positions' => ['direccion', 'consejo']],
        ],
        'alto' => [
            1 => ['requires_all' => true,  'positions' => ['lider']],
            2 => ['requires_all' => true,  'positions' => ['jefe']],
            3 => ['requires_all' => true,  'positions' => ['gerente']],
            4 => ['requires_all' => false, 'positions' => ['direccion', 'consejo']],
        ],
    ];

    // Procesos confidenciales (tipo restringido): sin importar el impacto, solo aprueba Consejo,
    // porque nadie más del flujo normal (líder, jefe, gerente, dirección) los puede ver.
    public const RESTRICTED_FLOW = [
        1 => ['requires_all' => false, 'positions' => ['consejo']],
    ];

    public static function getFlowSteps(string $level): array
    {
        return self::FLOWS[$level] ?? [];
    }

    /** Pasos que de verdad sigue este reglamento (el flujo de su impacto, o el de Consejo si es confidencial). */
    public static function flowFor(Regulation $regulation): array
    {
        if (! $regulation->impact_level) {
            return [];
        }

        return $regulation->isRestricted() ? self::RESTRICTED_FLOW : self::getFlowSteps($regulation->impact_level);
    }

    /**
     * Flujos por nivel para los modales de asignación. Con $restricted, todos los niveles muestran
     * el flujo de Consejo (proceso confidencial); '__restricted' lo lleva siempre para el listado,
     * donde el mismo modal sirve para procesos normales y confidenciales.
     */
    public static function getAllFlows(bool $restricted = false): array
    {
        $flows = $restricted ? array_map(fn () => self::RESTRICTED_FLOW, self::FLOWS) : self::FLOWS;

        return $flows + ['__restricted' => self::RESTRICTED_FLOW];
    }

    /**
     * Inicializa el flujo de aprobación al crear un reglamento.
     *
     * $notify en false crea los mismos registros de aprobación (el estado sigue siendo
     * "en revisión" real y accionable) pero sin mandar los correos de "tienes que aprobar esto" —
     * lo usa la carga masiva de documentos legado (ImportLegacyRegulations) para no
     * bombardear a todos los líderes/jefes/gerentes con un correo por cada documento histórico
     * que se importa de golpe.
     */
    public function initFlow(Regulation $regulation, array $userMap = [], bool $notify = true): void
    {
        $step = DB::transaction(function () use ($regulation, $userMap, $notify) {
            $regulation->approvals()->delete();

            return $this->openFirstStep($regulation, $userMap, $notify);
        });

        if ($notify && $step) {
            $this->notifyPendingApprovers($regulation, $step);
        }
    }

    /**
     * Abre el primer paso del flujo que tenga aprobadores (ver openStepFrom()) y, si por saltar
     * pasos vacíos arranca después del 1, lo deja "en autorización" en vez de "en revisión".
     */
    private function openFirstStep(Regulation $regulation, array $userMap, bool $notify = true): ?int
    {
        $step = $this->openStepFrom($regulation, 1, $userMap, $notify);

        // Ningún paso tiene aprobadores (p. ej. los elegidos ya no existen): en vez de quedar
        // atorado en silencio, el último paso avisa a los admins que falta asignar.
        if ($step === null && ($flow = self::flowFor($regulation)) !== []) {
            $this->createStepRecords($regulation, array_key_last($flow), $userMap, $notify);
        }

        if ($step !== null && $step > 1 && $regulation->approval_status === 'pending_review') {
            $regulation->update(['approval_status' => 'pending_authorization']);
        }

        return $step;
    }

    /**
     * Crea los registros del paso $from o, si el admin eligió aprobadores para el flujo
     * (flow_user_map) y en ese paso no eligió a nadie, se lo salta y sigue con el siguiente: a
     * veces no se necesitan líderes y el flujo va directo con gerentes y dirección. El nivel de
     * aprobación no cambia; solo se omiten los pasos sin aprobador elegido.
     * Sin ningún aprobador elegido se queda como siempre: entra todo el puesto.
     *
     * @return int|null el paso que quedó abierto, o null si ya no queda ninguno con aprobadores.
     */
    private function openStepFrom(Regulation $regulation, int $from, array $userMap, bool $notify = true): ?int
    {
        $flow = self::flowFor($regulation);

        for ($step = $from; isset($flow[$step]); $step++) {
            $hasUsers = $this->resolveStepUsers($regulation, $flow[$step], $userMap)->isNotEmpty();

            if (! $hasUsers && $userMap !== []) {
                continue;
            }

            $this->createStepRecords($regulation, $step, $userMap, $notify);

            return $step;
        }

        return null;
    }

    /**
     * Procesa la decisión de un aprobador (approve / reject).
     */
    public function processApproval(RegulationApproval $approval, string $status, ?string $comments = null): void
    {
        DB::transaction(function () use ($approval, $status, $comments) {
            $approval->update([
                'status'     => $status,
                'comments'   => $comments,
                'decided_at' => now(),
            ]);

            $regulation = $approval->regulation;
            $userMap = $regulation->flow_user_map ?? [];

            if ($status === 'rejected') {
                // "waiting" incluido: si alguien a media fila de un paso secuencial rechaza, no
                // debe quedar el resto de la fila colgado en waiting para siempre.
                $regulation->approvals()
                    ->whereIn('status', ['pending', 'waiting'])
                    ->update(['status' => 'cancelled']);
                $regulation->update(['approval_status' => 'rejected']);
                $this->notifyCreator($regulation, 'rejected', $comments, $approval->user);
                return;
            }

            if (! $approval->requires_all) {
                $regulation->approvalStep($approval->step_number)
                    ->where('status', 'pending')
                    ->update(['status' => 'cancelled']);
            }

            // Paso secuencial ("requires_all" con varios aprobadores): si queda alguien esperando
            // su turno en este mismo paso, le toca ahora — el paso no avanza hasta que también
            // decida (por eso se corta aquí y no se evalúa isStepComplete todavía).
            if ($this->promoteNextWaitingApprover($regulation, $approval->step_number)) {
                return;
            }

            if ($this->isStepComplete($regulation, $approval->step_number)) {
                // El siguiente paso con aprobadores (los que no tienen a nadie elegido se saltan).
                $nextStep = $this->openStepFrom($regulation, $approval->step_number + 1, $userMap);

                if ($nextStep !== null) {
                    $regulation->update(['approval_status' => 'pending_authorization']);
                    $this->notifyPendingApprovers($regulation, $nextStep);
                } else {
                    $regulation->update(['approval_status' => 'approved']);

                    // La vigencia real (1 año) se asigna hasta este momento — la "fecha de
                    // elaboración" capturada en el wizard ya no determina el vencimiento.
                    $regulation->currentVersion?->update([
                        'valid_until' => now()->addYear(),
                    ]);

                    $this->notifyCreator($regulation, 'approved');
                    $this->notifyApprovers($regulation);
                }
            }
        });
    }

    /**
     * Reinicia el flujo desde el paso 1. Se usa en dos casos: (1) un admin re-envía manualmente
     * un reglamento rechazado tras corregirlo (RegulationApprovalController::resubmit()), y (2)
     * automáticamente cuando se guarda una edición sobre un reglamento que ya estaba 'approved'
     * (saveEdit()/confirmEditDraft()/store() en los controladores de Regulation) — el contenido
     * cambió, así que ya no se puede considerar aprobado sin que alguien lo revise de nuevo.
     */
    public function resubmit(Regulation $regulation, bool $notify = true): void
    {
        $userMap = $regulation->flow_user_map ?? [];

        $step = DB::transaction(function () use ($regulation, $userMap) {
            $regulation->approvals()->delete();
            $regulation->update([
                'approval_status'    => 'pending_review',
                'direct_approved_by' => null,
                'direct_approved_at' => null,
            ]);

            return $this->openFirstStep($regulation, $userMap);
        });

        if ($notify && $step) {
            $this->notifyPendingApprovers($regulation, $step);
        }
    }

    /**
     * Avisa a los aprobadores del paso 1 de un flujo reiniciado sin notificar (cuando un admin
     * editó un aprobado y en el modal de revisión decidió mantener el flujo).
     */
    public function notifyRestartedFlow(Regulation $regulation): void
    {
        if (! in_array($regulation->approval_status, ['pending_review', 'pending_authorization'], true)) {
            return;
        }

        // El flujo puede arrancar después del paso 1 si se saltaron pasos sin aprobador elegido.
        $step = $regulation->approvals()->reorder()->where('status', 'pending')->min('step_number');

        if ($step) {
            $this->notifyPendingApprovers($regulation, (int) $step);
        }
    }

    /**
     * "Aprobado (sin flujo)": un admin da el documento por aprobado sin pasar por los aprobadores.
     * Se quitan las aprobaciones abiertas (las decididas quedan como historial) y se conserva el
     * nivel de impacto, para que la próxima edición vuelva a ofrecer el mismo flujo.
     */
    public function approveDirectly(Regulation $regulation, User $admin): void
    {
        DB::transaction(function () use ($regulation, $admin) {
            $regulation->approvals()->whereIn('status', ['pending', 'waiting'])->delete();
            $regulation->update([
                'approval_status'    => 'approved',
                'direct_approved_by' => $admin->id,
                'direct_approved_at' => now(),
            ]);

            // Igual que al aprobar por flujo, pero sin mover una vigencia que ya existía: el cambio
            // directo suele ser menor y no reinicia el año.
            $version = $regulation->currentVersion;
            if ($version && ! $version->valid_until) {
                $version->update(['valid_until' => now()->addYear()]);
            }
        });
    }

    /**
     * Cancela las aprobaciones que siguen abiertas (pending/waiting) cuando el reglamento se
     * envía a la papelera, para que no sigan apareciendo en "Mis aprobaciones" ni en los
     * recordatorios. Las ya decididas se conservan como historial.
     */
    public function cancelOpenApprovals(Regulation $regulation): void
    {
        $regulation->approvals()
            ->whereIn('status', ['pending', 'waiting'])
            ->update(['status' => 'cancelled']);
    }

    /**
     * Devuelve true si el usuario tiene algún registro pending en el reglamento.
     */
    public function userHasPendingApproval(Regulation $regulation, int $userId): bool
    {
        return $regulation->pendingApprovals()->where('user_id', $userId)->exists();
    }

    /**
     * Devuelve el registro pending del usuario en el reglamento, o null.
     */
    public function getPendingApprovalForUser(Regulation $regulation, int $userId): ?RegulationApproval
    {
        return $regulation->pendingApprovals()->where('user_id', $userId)->first();
    }

    /**
     * Si el reglamento estaba rechazado y se acaba de guardar una corrección (desde el wizard de
     * IA o desde el editor libre), avisa a los admins con acceso a la empresa que ya pueden
     * reiniciar el flujo — solo un admin puede hacerlo (RegulationApprovalController::resubmit()),
     * y sin este aviso nadie se entera de que ya se corrigió hasta que alguien entra a revisar el
     * reglamento por su cuenta. No hace nada si el reglamento no estaba rechazado.
     *
     * Solo se avisa a admins con acceso al módulo de Procesos (module_access 'all' o 'procesos')
     * — los admins de otros módulos (ej. solo Cumplimiento) no deben recibir avisos de un flujo
     * que no les corresponde.
     */
    public function notifyIfCorrectedAfterRejection(Regulation $regulation): void
    {
        if ($regulation->approval_status !== 'rejected') {
            return;
        }

        $admins = $this->processAdminsFor($regulation);

        foreach ($admins as $admin) {
            $admin->notify(new RegulationReadyToResubmitNotification($regulation));
        }
    }

    /**
     * Un operativo sin acceso de edición (no es responsable) solicitó que lo agreguen como
     * responsable de un reglamento — se avisa a los admins con acceso al módulo de Procesos
     * (mismo filtro que notifyIfCorrectedAfterRejection) para que le den acceso si corresponde.
     * Devuelve cuántos admins fueron notificados, para que el controlador pueda avisarle al
     * operativo si no había ninguno a quien avisar.
     */
    public function notifyAdminsOfAccessRequest(Regulation $regulation, User $requester): int
    {
        $admins = $this->processAdminsFor($regulation);

        foreach ($admins as $admin) {
            $admin->notify(new RegulationAccessRequestedNotification($regulation, $requester));
        }

        return $admins->count();
    }

    /**
     * Un admin acaba de agregar a estos usuarios como responsables de un reglamento (les dio
     * acceso de edición) — se les avisa para que sepan que ya pueden entrar a trabajarlo, en vez
     * de que se enteren solo si vuelven a intentar pedir acceso.
     */
    public function notifyAccessGranted(Regulation $regulation, Collection $newResponsables): void
    {
        foreach ($newResponsables as $responsable) {
            $responsable->notify(new RegulationAccessGrantedNotification($regulation));
        }
    }

    /**
     * Mismo filtro de admins que notifyIfCorrectedAfterRejection()/notifyAdminsOfAccessRequest():
     * solo quienes de verdad pueden corregir la asignación de puestos de este reglamento.
     *
     * @param  array<int, string>  $positionSlugs
     */
    private function notifyStepUnassigned(Regulation $regulation, int $step, array $positionSlugs): void
    {
        $names = JobPosition::where('group_id', $regulation->group_id)
            ->whereIn('slug', $positionSlugs)
            ->pluck('name')
            ->all();

        $admins = $this->processAdminsFor($regulation);

        foreach ($admins as $admin) {
            $admin->notify(new ApprovalStepUnassignedNotification($regulation, $step, $names ?: $positionSlugs));
        }
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function createStepRecords(Regulation $regulation, int $step, array $userMap = [], bool $notify = true): void
    {
        $flow = self::flowFor($regulation);
        $stepDef = $flow[$step] ?? null;

        if (! $stepDef) {
            return;
        }

        $users = $this->resolveStepUsers($regulation, $stepDef, $userMap)->values();

        // Ningún puesto del paso tiene usuarios reales (o flow_user_map apunta a usuarios que ya
        // no existen/no están activos) — sin este aviso el reglamento queda en
        // "pending_authorization" para siempre, sin ninguna fila "pending" sobre la cual nadie
        // (ni siquiera el recordatorio automático) pueda actuar. Ver ApprovalStepUnassignedNotification.
        if ($users->isEmpty() && $notify) {
            $this->notifyStepUnassigned($regulation, $step, $stepDef['positions']);
        }

        // Cuando el paso exige que TODOS aprueben y hay más de un aprobador, van uno a la vez en
        // el orden en que se agregaron (flow_user_map conserva ese orden) — no en paralelo: solo
        // el primero arranca "pending", el resto arranca "waiting" hasta que le toque su turno
        // (ver promoteNextWaitingApprover()). Si basta con uno (OR) o solo hay un aprobador, se
        // mantiene el comportamiento de siempre: todos "pending" desde el inicio.
        $sequential = $stepDef['requires_all'] && $users->count() > 1;

        foreach ($users as $index => $user) {
            RegulationApproval::create([
                'regulation_id'   => $regulation->id,
                'step_number'     => $step,
                'job_position_id' => $user->pivot_job_position_id,
                'user_id'         => $user->id,
                'requires_all'    => $stepDef['requires_all'],
                'sequence_order'  => $index,
                'status'          => (! $sequential || $index === 0) ? 'pending' : 'waiting',
            ]);
        }
    }

    /**
     * Resuelve qué usuarios corresponden a un paso del flujo: si el admin asignó usuarios
     * específicos por puesto (flow_user_map) se usan solo esos, si no, todos los usuarios
     * activos de cada puesto involucrado.
     *
     * @return Collection<int, User>  cada User trae "pivot_job_position_id" con el puesto resuelto.
     */
    private function resolveStepUsers(Regulation $regulation, array $stepDef, array $userMap): Collection
    {
        $users = collect();

        foreach ($stepDef['positions'] as $slug) {
            $position = JobPosition::where('group_id', $regulation->group_id)
                ->where('slug', $slug)
                ->first();

            if (! $position) {
                continue;
            }

            // Si se asignaron usuarios específicos para este puesto, usar solo esos.
            if (isset($userMap[$slug])) {
                $userIds = is_array($userMap[$slug])
                    ? array_map('intval', $userMap[$slug])
                    : [(int) $userMap[$slug]];

                foreach (User::whereIn('id', array_filter($userIds))->get() as $user) {
                    $user->pivot_job_position_id = $position->id;
                    $users->push($user);
                }
                continue;
            }

            // Si el admin eligió aprobadores para otros puestos y para este no, este puesto no
            // entra (p. ej. eligió Dirección y no Consejo en el mismo paso). Solo cuando no eligió
            // a nadie en todo el flujo entran todos los usuarios del puesto, como siempre.
            if ($userMap !== []) {
                continue;
            }

            foreach ($position->users as $user) {
                $user->pivot_job_position_id = $position->id;
                $users->push($user);
            }
        }

        // En un proceso confidencial nunca entra al flujo alguien que no lo puede ver.
        if ($regulation->isRestricted()) {
            $users = $users->filter(fn (User $user) => $user->canSeeRestrictedProcesses());
        }

        return $users->unique('id');
    }

    /** Admins de Procesos con acceso a la empresa del reglamento (y, si es confidencial, que lo puedan ver). */
    private function processAdminsFor(Regulation $regulation): Collection
    {
        $restricted = $regulation->isRestricted();

        return User::where('group_id', $regulation->group_id)
            ->whereHas('role', fn ($q) => $q->whereIn('slug', ['admin', 'superadmin']))
            ->get()
            ->filter(fn (User $u) => $u->canAccessCompany($regulation->company))
            ->filter(fn (User $u) => $u->canAccessModule('procesos'))
            ->filter(fn (User $u) => $u->wantsNotificationsFor('procesos'))
            ->filter(fn (User $u) => ! $restricted || $u->canSeeRestrictedProcesses());
    }

    private function isStepComplete(Regulation $regulation, int $step): bool
    {
        return ! $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->exists();
    }

    /**
     * En un paso secuencial (ver createStepRecords()), le pasa el turno al siguiente aprobador
     * en la fila (el "waiting" con menor sequence_order) y lo notifica. Devuelve false si no hay
     * nadie esperando turno en este paso — o porque el paso nunca fue secuencial, o porque ya
     * decidieron todos.
     */
    private function promoteNextWaitingApprover(Regulation $regulation, int $step): bool
    {
        $next = $regulation->approvalStep($step)
            ->where('status', 'waiting')
            ->orderBy('sequence_order')
            ->first();

        if (! $next) {
            return false;
        }

        $next->update(['status' => 'pending']);
        $next->user?->notify(new ApprovalRequestedNotification($regulation));

        return true;
    }

    private function notifyPendingApprovers(Regulation $regulation, int $step): void
    {
        $users = $regulation->approvalStep($step)
            ->where('status', 'pending')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id');

        foreach ($users as $user) {
            $user->notify(new ApprovalRequestedNotification($regulation));
        }
    }

    private function notifyCreator(Regulation $regulation, string $outcome, ?string $comments = null, $rejectedBy = null): void
    {
        $creator = $regulation->creator;

        if (! $creator) {
            return;
        }

        match ($outcome) {
            'approved' => $creator->notify(new RegulationApprovedNotification($regulation)),
            'rejected' => $creator->notify(new RegulationRejectedNotification($regulation, $comments ?? '', $rejectedBy)),
            default => null,
        };
    }

    /**
     * Avisa a todos los que votaron (aprobaron) en algún paso del flujo — para llegar a
     * "approved" TODOS tuvieron que aprobar (o, en un paso "basta con uno", quien haya votado),
     * así que esto es exactamente el conjunto de aprobadores reales, sin incluir a quien quedó
     * "cancelled" sin votar. Se excluye al creador porque notifyCreator() ya le avisó aparte
     * (evita mandarle el mismo correo dos veces si además es uno de los aprobadores).
     */
    private function notifyApprovers(Regulation $regulation): void
    {
        $approverIds = $regulation->approvals()
            ->where('status', 'approved')
            ->pluck('user_id')
            ->unique()
            ->reject(fn ($id) => $id === $regulation->created_by);

        User::whereIn('id', $approverIds)->get()->each(
            fn (User $user) => $user->notify(new RegulationApprovedNotification($regulation))
        );
    }
}
