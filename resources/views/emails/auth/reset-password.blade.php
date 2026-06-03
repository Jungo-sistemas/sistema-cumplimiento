<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restablecer contraseña</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto; background: #f9fafb;">

    <div style="background: #1e40af; padding: 20px 24px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px;">VIGIA — Sistema de Cumplimiento</h1>
    </div>

    <div style="padding: 24px; background: #ffffff;">

        <h2 style="font-size: 18px; color: #1e40af; margin-top: 0;">Restablece tu contraseña</h2>

        <p>Hola, <strong>{{ $user->name }}</strong>.</p>

        <p>Recibimos una solicitud para restablecer la contraseña de tu cuenta en VIGIA. Da clic en el botón para crear una nueva contraseña:</p>

        <div style="text-align: center; margin: 28px 0;">
            <a href="{{ $url }}"
               style="background: #1e40af; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: bold; display: inline-block;">
                Restablecer contraseña
            </a>
        </div>

        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 10px 14px; font-size: 13px;">
            <strong style="color: #991b1b;">Atención:</strong> este enlace es válido por
            <strong>{{ $expiryMinutes }} minutos</strong> y solo puede usarse una vez.
        </div>

        <p style="margin-top: 20px; font-size: 13px; color: #374151;">
            Si no solicitaste restablecer tu contraseña, ignora este correo. Tu contraseña no cambiará.
        </p>

        <p style="font-size: 12px; color: #6b7280; margin-top: 16px;">
            Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
            <span style="word-break: break-all; color: #1e40af;">{{ $url }}</span>
        </p>

        <p style="margin-top: 20px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No respondas a este correo.
        </p>
    </div>

    <div style="background: #f3f4f6; padding: 12px 24px; text-align: center; font-size: 11px; color: #9ca3af;">
        VIGIA — Sistema de Cumplimiento Normativo
    </div>

</body>
</html>
