<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Licencia próxima a vencer</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto;">

    <div style="background: #1e40af; padding: 20px 24px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px;">VIGIA — Sistema de Cumplimiento</h1>
    </div>

    <div style="padding: 24px;">
        @php
            $name = $license->licensable->name ?? 'Cliente';
            $tipo = $license->licensable_type === \App\Models\Group::class ? 'grupo' : 'empresa';
        @endphp

        <div style="background: {{ $daysUntilExpiration <= 1 ? '#fef2f2' : '#fff7ed' }}; border-left: 4px solid {{ $daysUntilExpiration <= 1 ? '#dc2626' : '#ea580c' }}; padding: 12px 16px; margin-bottom: 20px;">
            <strong style="color: {{ $daysUntilExpiration <= 1 ? '#dc2626' : '#ea580c' }};">
                @if($daysUntilExpiration <= 1)
                    La licencia del {{ $tipo }} «{{ $name }}» vence MAÑANA.
                @else
                    La licencia del {{ $tipo }} «{{ $name }}» vence en {{ $daysUntilExpiration }} días.
                @endif
            </strong>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px;">
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">{{ ucfirst($tipo) }}</td>
                <td style="padding: 6px 0; font-weight: bold;">{{ $name }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Vence</td>
                <td style="padding: 6px 0; font-weight: bold;">{{ $license->expires_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Incluye Procesos</td>
                <td style="padding: 6px 0;">{{ $license->includes_procesos ? 'Sí' : 'No' }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Monto a cobrar</td>
                <td style="padding: 6px 0; font-weight: bold;">${{ number_format((float) $license->price, 2) }} MXN</td>
            </tr>
        </table>

        <p style="margin-top: 24px;">
            Da seguimiento al pago con el cliente. Si no se activa una nueva licencia antes de la fecha de vencimiento,
            el sistema le quitará el acceso automáticamente.
        </p>

        <p style="margin-top: 8px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No responder a este correo.
        </p>
    </div>

</body>
</html>
