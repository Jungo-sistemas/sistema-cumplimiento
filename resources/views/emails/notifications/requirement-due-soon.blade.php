<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Requisitos próximos a vencer</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">

    <div style="background: #1e40af; padding: 20px 24px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px;">VIGIA — Sistema de Cumplimiento</h1>
    </div>

    <div style="padding: 24px;">

        @if($daysUntilDue <= 1)
            <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #dc2626;">¡Alerta! Los siguientes requisitos vencen MAÑANA.</strong>
            </div>
        @elseif($daysUntilDue <= 7)
            <div style="background: #fff7ed; border-left: 4px solid #ea580c; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #ea580c;">Los siguientes requisitos vencen en {{ $daysUntilDue }} días.</strong>
            </div>
        @elseif($daysUntilDue <= 30)
            <div style="background: #fefce8; border-left: 4px solid #ca8a04; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #ca8a04;">Aviso: los siguientes requisitos vencen en menos de 30 días.</strong>
            </div>
        @else
            <div style="background: #eff6ff; border-left: 4px solid #2563eb; padding: 12px 16px; margin-bottom: 20px;">
                <strong style="color: #2563eb;">Recordatorio: los siguientes requisitos vencen en menos de 60 días.</strong>
            </div>
        @endif

        <p>Hola, <strong>{{ $notifiable->name }}</strong>.</p>
        <p>Tienes <strong>{{ $requirements->count() }}</strong> requisito(s) próximos a vencer que requieren tu atención:</p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 16px; font-size: 14px;">
            <thead>
                <tr style="background: #f3f4f6;">
                    <th style="text-align: left; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">Activo</th>
                    <th style="text-align: left; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">Requisito</th>
                    <th style="text-align: left; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">Fecha de vencimiento</th>
                    <th style="text-align: left; padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">Estado</th>
                </tr>
            </thead>
            <tbody>
                @foreach($requirements as $req)
                    @php
                        $daysLeft = now()->diffInDays($req->due_date, false);
                        $rowColor = $daysLeft <= 1 ? '#fef2f2' : ($daysLeft <= 7 ? '#fff7ed' : '#ffffff');
                    @endphp
                    <tr style="background: {{ $rowColor }};">
                        <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">
                            {{ $req->asset->name ?? '—' }}
                        </td>
                        <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">
                            {{ $req->template->name ?? $req->type }}
                        </td>
                        <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">
                            {{ $req->due_date->format('d/m/Y') }}
                        </td>
                        <td style="padding: 8px 12px; border-bottom: 1px solid #e5e7eb;">
                            {{ $req->status->label() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="margin-top: 24px;">
            Ingresa al sistema para gestionar estos requisitos antes de su fecha de vencimiento.
        </p>

        <p style="margin-top: 8px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No responder a este correo.
        </p>
    </div>

</body>
</html>