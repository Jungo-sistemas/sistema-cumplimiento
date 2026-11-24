<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitácora de un hallazgo: quién hizo qué y cuándo. Junto con responsible_id es el historial de
 * hallazgos de cada persona, para que el auditor lo revise en la siguiente auditoría.
 */
class AuditFindingEvent extends Model
{
    public const LABELS = [
        'registrado'          => 'Registró el hallazgo',
        'causa_enviada'       => 'Envió la causa raíz y las acciones',
        'causa_aprobada'      => 'Aprobó la causa raíz y las acciones: inicia el seguimiento',
        'causa_devuelta'      => 'Devolvió la causa raíz con observaciones',
        'evidencia_enviada'   => 'Subió la evidencia del seguimiento',
        'evidencia_aceptada'  => 'Verificó la evidencia y cerró el hallazgo',
        'evidencia_rechazada' => 'Rechazó la evidencia',
        'plazo_vencido'       => 'Se venció el plazo',
    ];

    protected $fillable = ['audit_finding_id', 'user_id', 'action', 'comment'];

    public function finding(): BelongsTo
    {
        return $this->belongsTo(AuditFinding::class, 'audit_finding_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function label(): string
    {
        return self::LABELS[$this->action] ?? $this->action;
    }
}
