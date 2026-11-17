<?php

namespace App\Services;

use App\Models\Audit;
use App\Models\AuditFinding;
use App\Models\AuditFindingSubmission;
use App\Models\User;
use App\Notifications\AuditCompletedNotification;
use App\Notifications\AuditFindingAssignedNotification;
use App\Notifications\AuditFindingEvidenceSubmittedNotification;
use App\Notifications\AuditFindingReviewedNotification;
use Carbon\CarbonInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Acciones sobre auditorías y hallazgos. Del lado del auditado solo se usa submitEvidence();
 * registrar, revisar y calificar son del auditor — por ahora se llaman desde comandos de prueba
 * (audits:add-finding, audits:review-evidence, audits:complete) hasta que exista su pantalla.
 */
class AuditFindingService
{
    /** El auditor registra un hallazgo y elige al responsable entre los auditados. */
    public function register(Audit $audit, User $responsible, string $point, string $description, ?string $severity = null, ?CarbonInterface $dueAt = null): AuditFinding
    {
        if (! $audit->auditees()->where('users.id', $responsible->id)->exists()) {
            throw new InvalidArgumentException('El responsable tiene que ser uno de los auditados de esta auditoría.');
        }
        if ($severity !== null && ! array_key_exists($severity, AuditFinding::SEVERITIES)) {
            throw new InvalidArgumentException('Severidad inválida: usa ' . implode(', ', array_keys(AuditFinding::SEVERITIES)) . '.');
        }

        $finding = $audit->findings()->create([
            'responsible_id' => $responsible->id,
            'point'          => $point,
            'description'    => $description,
            'severity'       => $severity,
            'due_at'         => $dueAt,
            'status'         => AuditFinding::STATUS_PENDING,
        ]);

        $responsible->notify(new AuditFindingAssignedNotification($finding));

        return $finding;
    }

    /**
     * El responsable sube su evidencia (1 a 5 archivos, más un comentario opcional). Queda en
     * revisión del auditor.
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
            $submission = $finding->submissions()->create([
                'user_id' => $user->id,
                'comment' => $comment ?: null,
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

            $finding->update(['status' => AuditFinding::STATUS_IN_REVIEW]);

            return $submission;
        });

        $finding->audit->auditor?->notify(new AuditFindingEvidenceSubmittedNotification($finding, $submission));

        return $submission;
    }

    /** El auditor acepta (cierra el hallazgo) o rechaza con motivo (el responsable vuelve a subir). */
    public function review(AuditFinding $finding, bool $accepted, ?string $reason = null): void
    {
        $submission = $finding->latestSubmission;
        if ($finding->status !== AuditFinding::STATUS_IN_REVIEW || ! $submission) {
            throw new InvalidArgumentException('Este hallazgo no tiene evidencia en revisión.');
        }
        if (! $accepted && trim((string) $reason) === '') {
            throw new InvalidArgumentException('El rechazo necesita un motivo.');
        }

        DB::transaction(function () use ($finding, $submission, $accepted, $reason) {
            $submission->update([
                'status'         => $accepted ? AuditFindingSubmission::STATUS_ACCEPTED : AuditFindingSubmission::STATUS_REJECTED,
                'review_comment' => $reason ?: null,
                'reviewed_at'    => now(),
            ]);
            $finding->update($accepted
                ? ['status' => AuditFinding::STATUS_CLOSED, 'closed_at' => now()]
                : ['status' => AuditFinding::STATUS_REJECTED]);
        });

        $finding->responsible?->notify(new AuditFindingReviewedNotification($finding, $submission->fresh()));
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
}
