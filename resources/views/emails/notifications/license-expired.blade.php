<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Licencia vencida — acceso suspendido</title>
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

        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 12px 16px; margin-bottom: 20px;">
            <strong style="color: #dc2626;">
                Venció la licencia del {{ $tipo }} «{{ $name }}» — se le quitó el acceso al sistema automáticamente.
            </strong>
        </div>

        <table style="width: 100%; border-collapse: collapse; margin-top: 8px; font-size: 14px;">
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">{{ ucfirst($tipo) }}</td>
                <td style="padding: 6px 0; font-weight: bold;">{{ $name }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Venció</td>
                <td style="padding: 6px 0; font-weight: bold;">{{ $license->expires_at->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td style="padding: 6px 0; color: #6b7280;">Monto pendiente</td>
                <td style="padding: 6px 0; font-weight: bold;">${{ number_format((float) $license->price, 2) }} MXN</td>
            </tr>
        </table>

        <p style="margin-top: 24px;">
            En cuanto el cliente confirme el pago, activa una nueva licencia desde el panel de Superadmin para
            restaurarle el acceso.
        </p>

        <p style="margin-top: 8px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No responder a este correo.
        </p>
    </div>

</body>
</html>
