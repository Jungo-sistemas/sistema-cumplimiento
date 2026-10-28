<?php

namespace App\Enums;

enum InvestmentRequestStatus: string
{
    case PENDING_APPROVAL = 'pending_approval';
    case APPROVED = 'approved';
    case REJECTED = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PENDING_APPROVAL => 'Pendiente de aprobación',
            self::APPROVED         => 'Aprobada',
            self::REJECTED         => 'Rechazada',
        };
    }

    // Clases de Tailwind para la etiqueta de estado en la lista y el detalle.
    public function badgeClasses(): string
    {
        return match ($this) {
            self::PENDING_APPROVAL => 'bg-yellow-100 text-yellow-700 border-yellow-200',
            self::APPROVED         => 'bg-green-100 text-green-700 border-green-200',
            self::REJECTED         => 'bg-red-100 text-red-700 border-red-200',
        };
    }
}
