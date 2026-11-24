<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditFindingEvent;
use App\Models\AuditFindingSubmission;
use App\Models\User;
use App\Notifications\AuditCompletedNotification;
use App\Notifications\AuditFindingAlertNotification;
use App\Notifications\AuditFindingAssignedNotification;
use App\Notifications\AuditFindingReviewedNotification;
use App\Notifications\AuditFindingSubmittedNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Acciones sobre auditorías y hallazgos (flujo en AuditFinding). Del lado del auditado se usan
 * submitRootCause() y submitEvidence(); registrar, revisar y calificar son del auditor (pantallas de
 * Auditorías, vía AuditChecklistService y AuditorController; también los comandos de prueba
 * audits:add-finding, audits:review-finding y audits:complete).
 */
class AuditFindingService
{
    /**
     * El auditor documenta el hallazgo y elige al responsable entre los auditados. Se avisa al
     * responsable, a los responsables del procedimiento y, si es crítico, a quienes reciben los
     * avisos de auditorías.
     */
    public function register(Audit $audit, User $responsible, string $point, string $description, string $severity = 'no_critico', ?int $checklistItemId = null): AuditFinding
    {
        if (! $audit->auditees()->where('users.id', $responsible->id)->exists()) {
            throw new InvalidArgumentException('El responsable tiene que ser uno de los auditados de esta auditoría.');
        }
        if (! array_key_exists($severity, AuditFinding::SEVERITIES)) {
            throw new InvalidArgumentException('Severidad inválida: usa ' . implode(' o ', array_keys(AuditFinding::SEVERITIES)) . '.');
        }

        $finding = DB::transaction(function () use ($audit, $responsible, $point, $description, $severity, $checklistItemId) {
            $finding = $audit->findings()->make([
                'audit_checklist_item_id' => $checklistItemId,
                'responsible_id' => $responsible->id,
                'point'          => $point,
                'description'    => $description,
                'severity'       => $severity,
                'status'         => AuditFinding::STATUS_ROOT_CAUSE_PENDING,
            ]);
            $finding->due_at = $finding->stageDueDate();
            $finding->save();

            $this->log($finding, $audit->auditor, 'registrado');

            return $finding;
        });

        $responsible->notify(new AuditFindingAssignedNotification($finding));

        $owners = $audit->regulation?->responsables()->where('users.status', 'active')->get() ?? collect();
        foreach ($owners->where('id', '!=', $responsible->id) as $owner) {
            $owner->notify(new AuditFindingAssignedNotification($finding, asProcessOwner: true));
        }

        if ($finding->isCritical()) {
            foreach (User::auditAlertRecipients($audit->group_id) as $recipient) {
                $recipient->notify(new AuditFindingAlertNotification($finding, AuditFindingAlertNotification::REASON_CRITICAL));
            }
        }

        return $finding;
    }

    /**
     * El responsable envía la causa raíz y el plan de acciones (archivos de apoyo opcionales). Pasa
     * a validación del auditor.
     *
     * @param  UploadedFile[]  $files
     */
    public function submitRootCause(AuditFinding $finding, User $user, string $rootCause, string $actions, array $files = []): AuditFindingSubmission
    {
        if (! $finding->needsRootCause()) {
            throw new InvalidArgumentException('Este hallazgo no está esperando la causa raíz.');
        }
        if (trim($rootCause) === '' || trim($actions) === '') {
            throw new InvalidArgumentException('Escribe la causa raíz y las acciones.');
        }
        if (count($files) > AuditFinding::MAX_FILES) {
            throw new InvalidArgumentException('Puedes adjuntar como máximo ' . AuditFinding::MAX_FILES . ' archivos.');
        }

        $submission = DB::transaction(function () use ($finding, $user, $rootCause, $actions, $files) {
            $submission = $this->createSubmission($finding, $user, AuditFindingSubmission::KIND_ROOT_CAUSE, $files, [
                'root_cause' => trim($rootCause),
                'actions'    => trim($actions),
            ]);
            $this->moveTo($finding, AuditFinding::STATUS_ROOT_CAUSE_REVIEW);
            $this->log($finding, $user, 'causa_enviada');

            return $submission;
        });

        $finding->audit->auditor?->notify(new AuditFindingSubmittedNotification($finding, $submission));

        return $submission;
    }

    /**
     * Seguimiento: el responsable sube la evidencia de que aplicó las acciones (1 a 5 archivos, más
     * un comentario opcional). Pasa a verificación del auditor.
     *
     * @param  UploadedFile[]  $files
     */
    public function submitEvidence(AuditFinding $finding, User $user, array $files, ?string $comment = null): AuditFindingSubmission
    {
        if (! $finding->needsEvidence()) {
            throw new InvalidArgumentException('Este hallazgo no está esperando evidencia.');
        }
        if (count($files) < 1 || count($files) > AuditFinding::MAX_FILES) {
            throw new InvalidArgumentException('Sube de 1 a ' . AuditFinding::MAX_FILES . ' archivos.');
        }

        $submission = DB::transaction(function () use ($finding, $user, $files, $comment) {
            $submission = $this->createSubmission($finding, $user, AuditFindingSubmission::KIND_EVIDENCE, $files, [
                'comment' => $comment ?: null,
            ]);
            $this->moveTo($finding, AuditFinding::STATUS_EVIDENCE_REVIEW);
            $this->log($finding, $user, 'evidencia_enviada');

            return $submission;
        });

        $finding->audit->auditor?->notify(new AuditFindingSubmittedNotification($finding, $submission));

        return $submission;
    }

    /**
     * El auditor revisa lo que está en revisión:
     *   causa raíz  → aprobada: inicia el seguimiento (evidencia) · devuelta: corregir la causa raíz
     *   evidencia   → aceptada: hallazgo cerrado · rechazada: subir otra evidencia
     * Devolver o rechazar necesita motivo.
     */
    public function review(AuditFinding $finding, bool $accepted, ?string $reason = null, ?User $by = null): void
    {
        $submission = $finding->latestSubmission;
        if (! in_array($finding->status, AuditFinding::NEEDS_AUDITOR, true) || ! $submission) {
            throw new InvalidArgumentException('Este hallazgo no tiene nada en revisión del auditor.');
        }
        if (! $accepted && trim((string) $reason) === '') {
            throw new InvalidArgumentException('Devolver o rechazar necesita un motivo.');
        }

        $rootCause = $finding->status === AuditFinding::STATUS_ROOT_CAUSE_REVIEW;

        DB::transaction(function () use ($finding, $submission, $accepted, $reason, $rootCause, $by) {
            $submission->update([
                'status'         => $accepted ? AuditFindingSubmission::STATUS_ACCEPTED : AuditFindingSubmission::STATUS_REJECTED,
                'review_comment' => trim((string) $reason) ?: null,
                'reviewed_at'    => now(),
            ]);

            if ($rootCause) {
                $this->moveTo($finding, $accepted ? AuditFinding::STATUS_EVIDENCE_PENDING : AuditFinding::STATUS_ROOT_CAUSE_REJECTED);
            } elseif ($accepted) {
                $finding->update(['status' => AuditFinding::STATUS_CLOSED, 'closed_at' => now(), 'due_at' => null, 'overdue_notified_at' => null]);
            } else {
                $this->moveTo($finding, AuditFinding::STATUS_EVIDENCE_REJECTED);
            }

            $action = match (true) {
                $rootCause && $accepted => 'causa_aprobada',
                $rootCause              => 'causa_devuelta',
                $accepted               => 'evidencia_aceptada',
                default                 => 'evidencia_rechazada',
            };
            $this->log($finding, $by ?? $finding->audit->auditor, $action, $reason);
        });

        $finding->responsible?->notify(new AuditFindingReviewedNotification($finding, $submission->fresh()));
    }

    /**
     * Plazos vencidos: un aviso por etapa a quien le toca actuar (responsable o auditor) y a quienes
     * reciben los avisos de auditorías. Lo corre a diario audits:findings-overdue.
     *
     * @return int hallazgos avisados
     */
    public function notifyOverdue(bool $dryRun = false): int
    {
        $findings = AuditFinding::overdueUnnotified()
            ->with(['audit.regulation', 'audit.auditor', 'audit.company', 'responsible'])
            ->get();

        if ($dryRun) {
            return $findings->count();
        }

        foreach ($findings as $finding) {
            $actor = in_array($finding->status, AuditFinding::NEEDS_AUDITOR, true) ? $finding->audit->auditor : $finding->responsible;
            $recipients = User::auditAlertRecipients($finding->audit->group_id)
                ->when($actor, fn ($c) => $c->push($actor))
                ->unique('id');

            foreach ($recipients as $recipient) {
                $recipient->notify(new AuditFindingAlertNotification($finding, AuditFindingAlertNotification::REASON_OVERDUE));
            }

            $finding->update(['overdue_notified_at' => now()]);
            $this->log($finding, null, 'plazo_vencido', $finding->stageLabel());
        }

        return $findings->count();
    }

    /** El auditor califica la auditoría: pasa a Historial con su calificación y se avisa a los auditados. */
    public function complete(Audit $audit, string $result): void
    {
        $audit->update([
            'status'       => Audit::STATUS_COMPLETED,
            'result'       => $result,
            'completed_at' => now(),
        ]);

        foreach ($audit->auditees()->where('status', 'active')->get() as $auditee) {
            $auditee->notify(new AuditCompletedNotification($audit));
        }
    }

    /** Cambia de etapa: el plazo vuelve a contar completo desde hoy. */
    private function moveTo(AuditFinding $finding, string $status): void
    {
        $finding->status = $status;
        $finding->due_at = $finding->stageDueDate();
        $finding->overdue_notified_at = null;
        $finding->save();
    }

    /** @param UploadedFile[] $files */
    private function createSubmission(AuditFinding $finding, User $user, string $kind, array $files, array $attributes): AuditFindingSubmission
    {
        $submission = $finding->submissions()->create($attributes + [
            'user_id' => $user->id,
            'kind'    => $kind,
            'status'  => AuditFindingSubmission::STATUS_IN_REVIEW,
        ]);

        foreach ($files as $file) {
            $submission->files()->create([
                'path'          => $file->store("audit-findings/{$finding->id}/{$submission->id}", 'private'),
                'original_name' => $file->getClientOriginalName(),
                'mime_type'     => $file->getClientMimeType(),
                'size'          => $file->getSize() ?: 0,
            ]);
        }

        $finding->unsetRelation('latestSubmission');

        return $submission;
    }

    private function log(AuditFinding $finding, ?User $user, string $action, ?string $comment = null): void
    {
        $finding->events()->create([
            'user_id' => $user?->id,
            'action'  => $action,
            'comment' => trim((string) $comment) ?: null,
        ]);
    }
}
