<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Código de verificación</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto; background: #f9fafb;">

    <div style="background: #1e40af; padding: 20px 24px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px;">VIGIA — Sistema de Cumplimiento</h1>
    </div>

    <div style="padding: 24px; background: #ffffff;">

        <h2 style="font-size: 18px; color: #1e40af; margin-top: 0;">Código de verificación</h2>

        <p>Hola, <strong>{{ $user->name }}</strong>.</p>

        <p>Usa el siguiente código para completar tu inicio de sesión. Expira en <strong>10 minutos</strong>.</p>

        <div style="text-align: center; margin: 28px 0;">
            <div style="display: inline-block; background: #f3f4f6; border: 2px solid #1e40af;
                        border-radius: 8px; padding: 16px 32px;">
                <span style="font-size: 36px; font-weight: bold; letter-spacing: 10px; color: #1e40af;">
                    {{ $code }}
                </span>
            </div>
        </div>

        <div style="background: #fef2f2; border-left: 4px solid #dc2626; padding: 10px 14px; font-size: 13px;">
            <strong style="color: #991b1b;">Importante:</strong> Si no fuiste tú quien inició sesión,
            cambia tu contraseña de inmediato.
        </div>

        <p style="margin-top: 20px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No respondas a este correo.
        </p>
    </div>

    <div style="background: #f3f4f6; padding: 12px 24px; text-align: center; font-size: 11px; color: #9ca3af;">
        VIGIA — Sistema de Cumplimiento Normativo
    </div>

</body>
</html>
