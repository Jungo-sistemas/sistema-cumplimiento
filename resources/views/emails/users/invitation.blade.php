<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invitación a VIGIA</title>
</head>
<body style="font-family: Arial, sans-serif; color: #1f2937; max-width: 600px; margin: 0 auto; background: #f9fafb;">

    <div style="background: #1e40af; padding: 20px 24px;">
        <h1 style="color: #ffffff; margin: 0; font-size: 20px;">VIGIA — Sistema de Cumplimiento</h1>
    </div>

    <div style="padding: 24px; background: #ffffff;">

        <h2 style="font-size: 18px; color: #1e40af; margin-top: 0;">Has sido invitado a VIGIA</h2>

        <p>Hola, <strong>{{ $user->name }}</strong>.</p>

        <p>Un administrador te ha registrado en la plataforma VIGIA con los siguientes datos:</p>

        <table style="width: 100%; font-size: 14px; margin: 16px 0; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6b7280; width: 130px;">Empresa:</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $user->company->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Rol:</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $user->role->name ?? 'N/A' }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6b7280;">Correo:</td>
                <td style="padding: 8px 0; font-weight: bold;">{{ $user->email }}</td>
            </tr>
        </table>

        <p>Da clic en el botón para activar tu cuenta y crear tu contraseña:</p>

        <div style="text-align: center; margin: 28px 0;">
            <a href="{{ route('invitation.accept', $user->invite_token) }}"
               style="background: #1e40af; color: #ffffff; padding: 12px 28px; text-decoration: none; border-radius: 6px; font-size: 15px; font-weight: bold; display: inline-block;">
                Activar cuenta
            </a>
        </div>

        <div style="background: #fefce8; border-left: 4px solid #ca8a04; padding: 10px 14px; margin-top: 8px; font-size: 13px;">
            <strong style="color: #92400e;">Importante:</strong> este enlace expira el
            <strong>{{ optional($user->invite_expires_at)->format('d/m/Y \a \l\a\s H:i') }}</strong>.
            Si no activas tu cuenta antes de esa fecha, solicita una nueva invitación al administrador.
        </div>

        <p style="margin-top: 20px; font-size: 13px; color: #374151;">
            Si no esperabas este correo o crees que fue un error, puedes ignorarlo sin problema.
        </p>

        <p style="margin-top: 4px; font-size: 12px; color: #6b7280;">
            Este es un mensaje automático generado por VIGIA. No respondas a este correo.
        </p>
    </div>

    <div style="background: #f3f4f6; padding: 12px 24px; text-align: center; font-size: 11px; color: #9ca3af;">
        VIGIA — Sistema de Cumplimiento Normativo
    </div>

</body>
</html>
